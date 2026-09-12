<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Nfse\NfseStatus;
use App\Models\Emitente;
use App\Models\EmitenteNfse;
use App\Models\FaturaParcela;
use App\Models\NotaServico;
use App\Models\ServicoNfse;
use App\Models\User;
use App\Services\Nfse\FalhaDeComunicacaoNfse;
use App\Services\Nfse\NfseEmissor;
use App\Services\Nfse\RespostaNfse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('fiscal');
    $this->travelTo('2026-09-12 10:00:00');

    $this->emitente = emitenteComNfse();
    $this->servico = ServicoNfse::create([
        'emitente_id' => $this->emitente->id, 'nome' => 'Consultoria', 'codigo_servico' => '17.01.00',
        'codigo_nbs' => '1.1406.20.00', 'c_class_trib' => '000001', 'ind_op' => '050101', 'aliquota_iss_bp' => 200,
    ]);
    $this->parcela = parcelaParaNfse($this->emitente);
    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
});

function emitirNota(FaturaParcela $parcela, ServicoNfse $servico, ?User $user = null, string $descricao = 'Consultoria de setembro'): NotaServico
{
    return app(NfseEmissor::class)->emitir($parcela, $servico, $descricao, $user);
}

function mensagemDe(Closure $acao): string
{
    try {
        $acao();
    } catch (ValidationException $e) {
        return implode(' ', $e->errors()['nfse'] ?? []);
    }

    return 'nenhum erro';
}

describe('caminho feliz', function () {
    it('autoriza a nota, grava numero, codigo, arquivos e autor, e consome o rps do ambiente', function () {
        comGatewayNfse(['emitir' => nfseAutorizada()]);

        $nota = emitirNota($this->parcela, $this->servico, $this->user);

        expect($nota->status)->toBe(NfseStatus::Autorizada)
            ->and($nota->numero_nfse)->toBe('700')
            ->and($nota->serie_nfse)->toBe('NFE')
            ->and($nota->codigo_verificacao)->toBe('ABC123')
            ->and($nota->numero_rps)->toBe(1)
            ->and($nota->serie_rps)->toBe('1')
            ->and($nota->ambiente)->toBe(Ambiente::Homologacao)
            ->and($nota->valor_centavos)->toBe(150000)
            ->and($nota->emitida_por)->toBe($this->user->id)
            ->and($nota->emitida_em)->not->toBeNull()
            ->and($nota->motivo_rejeicao)->toBeNull();

        Storage::disk('fiscal')->assertExists($nota->xml_envio_path);
        Storage::disk('fiscal')->assertExists($nota->xml_retorno_path);
        expect(Storage::disk('fiscal')->get($nota->xml_envio_path))->toContain('<rps>1</rps>')->toContain('<cnpj_cpf_prestador>11222333000181</cnpj_cpf_prestador>')
            ->and(Storage::disk('fiscal')->get($nota->xml_retorno_path))->toContain('<numero_nf>700</numero_nf>');

        $config = $this->emitente->nfse->fresh();
        expect($config->proximo_rps_homologacao)->toBe(2)->and($config->proximo_rps_producao)->toBe(1);
    });

    it('congela os campos do servico na nota: editar o catalogo depois nao muda o que foi enviado', function () {
        comGatewayNfse(['emitir' => nfseAutorizada()]);
        $nota = emitirNota($this->parcela, $this->servico);

        $this->servico->update(['aliquota_iss_bp' => 500, 'codigo_servico' => '01.01.00']);

        expect($nota->fresh()->aliquota_iss_bp)->toBe(200)
            ->and($nota->fresh()->codigo_servico)->toBe('17.01.00');
    });

    it('em producao consome o rps de producao e deixa o de homologacao quieto', function () {
        $this->emitente->nfse->update(['ambiente' => Ambiente::Producao, 'senha_producao' => 'segredo-prod']);
        comGatewayNfse(['emitir' => nfseAutorizada()]);

        $nota = emitirNota($this->parcela->fresh(), $this->servico);

        expect($nota->ambiente)->toBe(Ambiente::Producao)
            ->and($this->emitente->nfse->fresh()->proximo_rps_producao)->toBe(2)
            ->and($this->emitente->nfse->fresh()->proximo_rps_homologacao)->toBe(1);
    });
});

describe('rejeição e erro', function () {
    it('rejeicao fica gravada com o motivo e a nova tentativa reaproveita o rps e o registro', function () {
        comGatewayNfse(['emitir' => new RespostaNfse(false, motivo: 'Alíquota inválida', bruto: '<notafiscal><erro>Alíquota inválida</erro></notafiscal>')]);

        expect(mensagemDe(fn () => emitirNota($this->parcela, $this->servico)))->toContain('Alíquota inválida');

        $rejeitada = NotaServico::query()->sole();
        expect($rejeitada->status)->toBe(NfseStatus::Rejeitada)
            ->and($rejeitada->numero_rps)->toBe(1)
            ->and($rejeitada->motivo_rejeicao)->toBe('Alíquota inválida')
            ->and($this->emitente->nfse->fresh()->proximo_rps_homologacao)->toBe(2);
        Storage::disk('fiscal')->assertExists($rejeitada->xml_retorno_path);

        comGatewayNfse(['emitir' => nfseAutorizada('701')]);
        $autorizada = emitirNota($this->parcela->fresh(), $this->servico);

        expect($autorizada->id)->toBe($rejeitada->id)
            ->and($autorizada->numero_rps)->toBe(1)
            ->and($autorizada->status)->toBe(NfseStatus::Autorizada)
            ->and($autorizada->numero_nfse)->toBe('701')
            ->and($autorizada->motivo_rejeicao)->toBeNull()
            ->and($this->emitente->nfse->fresh()->proximo_rps_homologacao)->toBe(2)
            ->and(NotaServico::query()->count())->toBe(1);
    });

    it('falha de comunicacao vira erro com a orientacao de conferir no portal, e libera nova tentativa com o mesmo rps', function () {
        comGatewayNfse(['emitir' => new FalhaDeComunicacaoNfse('Falha de comunicação com o SIGISS ao emitir a NFS-e: timeout')]);

        expect(mensagemDe(fn () => emitirNota($this->parcela, $this->servico)))
            ->toContain('timeout')
            ->toContain('Confira no portal do SIGISS');

        $nota = NotaServico::query()->sole();
        expect($nota->status)->toBe(NfseStatus::Erro)->and($nota->numero_rps)->toBe(1)->and($nota->xml_retorno_path)->toBeNull();

        comGatewayNfse(['emitir' => nfseAutorizada()]);
        expect(emitirNota($this->parcela->fresh(), $this->servico)->numero_rps)->toBe(1);
    });
});

describe('bloqueios', function () {
    it('nao reemite parcela com nota autorizada, e nao chama o gateway', function () {
        comGatewayNfse(['emitir' => nfseAutorizada()]);
        emitirNota($this->parcela, $this->servico);

        $fake = comGatewayNfse(['emitir' => nfseAutorizada()]);
        expect(mensagemDe(fn () => emitirNota($this->parcela->fresh(), $this->servico)))->toContain('já tem NFS-e autorizada')
            ->and($fake->chamadas)->toBeEmpty();
    });

    it('nao reemite nota em processamento recente', function () {
        comGatewayNfse(['emitir' => nfseAutorizada()]);
        emitirNota($this->parcela, $this->servico);
        NotaServico::query()->sole()->forceFill(['status' => NfseStatus::Processando])->save();

        $fake = comGatewayNfse(['emitir' => nfseAutorizada()]);
        expect(mensagemDe(fn () => emitirNota($this->parcela->fresh(), $this->servico)))->toContain('em processamento')
            ->and($fake->chamadas)->toBeEmpty();
    });

    it('processando ha mais de 15 minutos e envio que morreu no meio: libera a nova tentativa', function () {
        comGatewayNfse(['emitir' => nfseAutorizada()]);
        emitirNota($this->parcela, $this->servico);
        $nota = NotaServico::query()->sole();
        $nota->forceFill(['status' => NfseStatus::Processando, 'updated_at' => now()->subMinutes(16)])->saveQuietly();

        comGatewayNfse(['emitir' => nfseAutorizada('702')]);
        $reemitida = emitirNota($this->parcela->fresh(), $this->servico);

        expect($reemitida->id)->toBe($nota->id)->and($reemitida->numero_nfse)->toBe('702');
    });

    it('nao reutiliza nota cancelada', function () {
        comGatewayNfse(['emitir' => nfseAutorizada()]);
        emitirNota($this->parcela, $this->servico);
        NotaServico::query()->sole()->forceFill(['status' => NfseStatus::Cancelada])->save();

        $fake = comGatewayNfse(['emitir' => nfseAutorizada()]);
        expect(mensagemDe(fn () => emitirNota($this->parcela->fresh(), $this->servico)))->toContain('cancelada')
            ->and($fake->chamadas)->toBeEmpty();
    });
});

describe('validação antes de encostar no gateway', function () {
    it('recusa, sem chamar o gateway, quando falta o que a nota exige', function (Closure $preparo, string $trecho) {
        $preparo($this);
        $fake = comGatewayNfse(['emitir' => nfseAutorizada()]);

        expect(mensagemDe(fn () => emitirNota($this->parcela->fresh(), $this->servico->fresh())))->toContain($trecho)
            ->and($fake->chamadas)->toBeEmpty()
            ->and(NotaServico::query()->count())->toBe(0);
    })->with([
        'emissão desligada' => [fn ($t) => $t->emitente->nfse->update(['habilitado' => false]), 'desligada'],
        'fora de Araras' => [fn ($t) => $t->emitente->update(['codigo_municipio' => '3538709', 'municipio' => 'Piracicaba']), 'Araras'],
        'sem inscrição municipal' => [fn ($t) => $t->emitente->update(['inscricao_municipal' => null]), 'inscrição municipal'],
        'sem senha do ambiente' => [fn ($t) => $t->emitente->nfse->update(['senha_homologacao' => null]), 'senha'],
        'parcela cancelada' => [fn ($t) => $t->parcela->forceFill(['status' => 'cancelado'])->save(), 'cancelada'],
        'fatura cancelada' => [fn ($t) => $t->parcela->fatura->update(['status' => 'cancelada']), 'cancelada'],
        'serviço inativo' => [fn ($t) => $t->servico->update(['ativo' => false]), 'Serviço fiscal inválido'],
        'serviço de outro emitente' => [fn ($t) => $t->servico->update(['emitente_id' => Emitente::factory()->create()->id]), 'Serviço fiscal inválido'],
        'fatura sem cliente' => [fn ($t) => $t->parcela->fatura->update(['pessoa_id' => null]), 'tomador'],
    ]);

    it('recusa descricao vazia ou longa demais', function () {
        $fake = comGatewayNfse(['emitir' => nfseAutorizada()]);

        expect(mensagemDe(fn () => emitirNota($this->parcela, $this->servico, null, ' ')))->toContain('descrição')
            ->and(mensagemDe(fn () => emitirNota($this->parcela, $this->servico, null, str_repeat('a', 1001))))->toContain('descrição')
            ->and($fake->chamadas)->toBeEmpty();
    });

    it('sem configuracao nenhuma, recusa com a mesma mensagem de desligada', function () {
        EmitenteNfse::query()->delete();
        $fake = comGatewayNfse(['emitir' => nfseAutorizada()]);

        expect(mensagemDe(fn () => emitirNota($this->parcela->fresh(), $this->servico)))->toContain('desligada')
            ->and($fake->chamadas)->toBeEmpty();
    });
});
