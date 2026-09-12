<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Nfse\NfseStatus;
use App\Enums\Nfse\ProvedorNfse;
use App\Models\EmitenteNfse;
use App\Models\NotaServico;
use App\Models\ServicoNfse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->emitente = emitenteCompleto();
});

it('guarda a senha do sigiss cifrada e a devolve legivel pelo ambiente', function () {
    $config = EmitenteNfse::create([
        'emitente_id' => $this->emitente->id,
        'senha_homologacao' => 'segredo-hml',
        'senha_producao' => 'segredo-prod',
    ]);

    $bruto = DB::table('emitente_nfse')->where('id', $config->id)->value('senha_homologacao');

    expect($bruto)->not->toContain('segredo-hml')
        ->and($config->fresh()->senha(Ambiente::Homologacao))->toBe('segredo-hml')
        ->and($config->fresh()->senha(Ambiente::Producao))->toBe('segredo-prod')
        ->and($config->colunaProximoRps(Ambiente::Homologacao))->toBe('proximo_rps_homologacao')
        ->and($config->colunaProximoRps(Ambiente::Producao))->toBe('proximo_rps_producao')
        ->and($config->proximoRps(Ambiente::Homologacao))->toBe(1);
});

it('a senha nao aparece na serializacao nem na auditoria', function () {
    $config = EmitenteNfse::create(['emitente_id' => $this->emitente->id, 'senha_homologacao' => 'segredo']);

    expect($config->toArray())->not->toHaveKey('senha_homologacao');
    expect(DB::table('audit_logs')->get()->pluck('dados')->implode(' '))->not->toContain('segredo');
});

it('nasce desligada, em homologacao, com rps 1 nos dois ambientes', function () {
    $config = new EmitenteNfse;

    expect($config->habilitado)->toBeFalse()
        ->and($config->ambiente)->toBe(Ambiente::Homologacao)
        ->and($config->serie_rps)->toBe('1')
        ->and($config->proximo_rps_homologacao)->toBe(1)
        ->and($config->proximo_rps_producao)->toBe(1);
});

it('o emitente alcanca a configuracao e os servicos pelas relacoes', function () {
    EmitenteNfse::create(['emitente_id' => $this->emitente->id]);
    ServicoNfse::create(['emitente_id' => $this->emitente->id, 'nome' => 'Consultoria', 'codigo_servico' => '17.01.00']);

    expect($this->emitente->nfse)->toBeInstanceOf(EmitenteNfse::class)
        ->and($this->emitente->servicosNfse)->toHaveCount(1);
});

it('valida o codigo do servico no formato 00.00.00 e formata a aliquota', function () {
    expect(ServicoNfse::codigoValido('10.08.01'))->toBeTrue()
        ->and(ServicoNfse::codigoValido('1008'))->toBeFalse()
        ->and(ServicoNfse::codigoValido('10.08.1'))->toBeFalse();

    $servico = new ServicoNfse(['aliquota_iss_bp' => 200]);

    expect($servico->aliquotaFormatada())->toBe('2,00')
        ->and($servico->ativo)->toBeTrue()
        ->and($servico->iss_retido)->toBeFalse();
});

it('a nota diz qual documento tem: nfse quando autorizada, rps antes disso', function () {
    $servico = ServicoNfse::create(['emitente_id' => $this->emitente->id, 'nome' => 'Consultoria', 'codigo_servico' => '17.01.00']);
    $parcela = parcelaParaNfse($this->emitente);

    $nota = NotaServico::create([
        'emitente_id' => $this->emitente->id,
        'fatura_parcela_id' => $parcela->id,
        'servico_nfse_id' => $servico->id,
        'ambiente' => Ambiente::Homologacao,
        'status' => NfseStatus::Processando,
        'numero_rps' => 12, 'serie_rps' => '1',
        'codigo_servico' => '17.01.00', 'aliquota_iss_bp' => 0, 'iss_retido' => false,
        'descricao' => 'Consultoria de setembro', 'valor_centavos' => 150000,
    ]);

    expect($nota->documento())->toBe('RPS 12')
        ->and($nota->temDocumento())->toBeFalse()
        ->and($nota->provedor)->toBe(ProvedorNfse::Sigiss)
        ->and($parcela->notasServico)->toHaveCount(1);

    $nota->forceFill(['status' => NfseStatus::Autorizada, 'numero_nfse' => '700'])->save();

    expect($nota->fresh()->documento())->toBe('NFS-e 700')
        ->and($nota->fresh()->temDocumento())->toBeTrue();
});

it('nao aceita duas notas para a mesma parcela no mesmo ambiente', function () {
    $servico = ServicoNfse::create(['emitente_id' => $this->emitente->id, 'nome' => 'Consultoria', 'codigo_servico' => '17.01.00']);
    $parcela = parcelaParaNfse($this->emitente);
    $base = [
        'emitente_id' => $this->emitente->id, 'fatura_parcela_id' => $parcela->id, 'servico_nfse_id' => $servico->id,
        'ambiente' => Ambiente::Homologacao, 'status' => NfseStatus::Rejeitada, 'serie_rps' => '1',
        'codigo_servico' => '17.01.00', 'aliquota_iss_bp' => 0, 'iss_retido' => false,
        'descricao' => 'x', 'valor_centavos' => 100,
    ];

    NotaServico::create($base + ['numero_rps' => 1]);

    expect(fn () => NotaServico::create($base + ['numero_rps' => 2]))->toThrow(QueryException::class);
});

it('rejeitada e erro permitem nova tentativa; as outras nao', function (NfseStatus $status, bool $permite) {
    expect($status->permiteNovaTentativa())->toBe($permite);
})->with([
    [NfseStatus::Rejeitada, true],
    [NfseStatus::Erro, true],
    [NfseStatus::Processando, false],
    [NfseStatus::Autorizada, false],
    [NfseStatus::Cancelada, false],
]);
