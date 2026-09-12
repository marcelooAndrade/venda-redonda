<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Nfse\NfseStatus;
use App\Models\ServicoNfse;
use App\Services\Nfse\FalhaDeComunicacaoNfse;
use App\Services\Nfse\NfseEmissor;
use App\Services\Nfse\RespostaNfse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('fiscal');
    $this->emitente = emitenteComNfse();
    $this->servico = ServicoNfse::create(['emitente_id' => $this->emitente->id, 'nome' => 'Consultoria', 'codigo_servico' => '17.01.00']);
    $this->parcela = parcelaParaNfse($this->emitente);

    comGatewayNfse(['emitir' => nfseAutorizada()]);
    $this->nota = app(NfseEmissor::class)->emitir($this->parcela, $this->servico, 'Consultoria de setembro');
});

function erroDe(Closure $acao): string
{
    try {
        $acao();
    } catch (ValidationException $e) {
        return implode(' ', $e->errors()['nfse'] ?? []);
    }

    return 'nenhum erro';
}

it('cancela uma nota autorizada e guarda quando e por que', function () {
    $fake = comGatewayNfse(['cancelar' => new RespostaNfse(true, bruto: '<resultado>Nota cancelada com sucesso</resultado>')]);

    $nota = app(NfseEmissor::class)->cancelar($this->nota, 'Serviço cancelado a pedido do cliente.');

    expect($nota->status)->toBe(NfseStatus::Cancelada)
        ->and($nota->cancelada_em)->not->toBeNull()
        ->and($nota->motivo_cancelamento)->toBe('Serviço cancelado a pedido do cliente.')
        ->and($nota->numero_nfse)->toBe('700')
        ->and($fake->chamadas[0]['args']['numero'])->toBe('700')
        ->and($fake->chamadas[0]['args']['serie'])->toBe('NFE');
});

it('exige justificativa entre 15 e 255 caracteres, sem chamar o gateway', function () {
    $fake = comGatewayNfse(['cancelar' => new RespostaNfse(true)]);

    expect(erroDe(fn () => app(NfseEmissor::class)->cancelar($this->nota, 'curta')))->toContain('15 e 255')
        ->and(erroDe(fn () => app(NfseEmissor::class)->cancelar($this->nota, str_repeat('x', 256))))->toContain('15 e 255')
        ->and($fake->chamadas)->toBeEmpty()
        ->and($this->nota->fresh()->status)->toBe(NfseStatus::Autorizada);
});

it('so cancela nota autorizada', function () {
    $this->nota->forceFill(['status' => NfseStatus::Rejeitada, 'numero_nfse' => null])->save();
    $fake = comGatewayNfse(['cancelar' => new RespostaNfse(true)]);

    expect(erroDe(fn () => app(NfseEmissor::class)->cancelar($this->nota->fresh(), 'Serviço cancelado a pedido do cliente.')))->toContain('autorizada')
        ->and($fake->chamadas)->toBeEmpty();
});

it('recusa do sigiss e falha de comunicacao mantem a nota autorizada', function () {
    comGatewayNfse(['cancelar' => new RespostaNfse(false, motivo: 'Erro: prazo de cancelamento expirado')]);
    expect(erroDe(fn () => app(NfseEmissor::class)->cancelar($this->nota, 'Serviço cancelado a pedido do cliente.')))->toContain('prazo de cancelamento');

    comGatewayNfse(['cancelar' => new FalhaDeComunicacaoNfse('Falha de comunicação com o SIGISS ao cancelar a NFS-e: timeout')]);
    expect(erroDe(fn () => app(NfseEmissor::class)->cancelar($this->nota, 'Serviço cancelado a pedido do cliente.')))->toContain('timeout');

    expect($this->nota->fresh()->status)->toBe(NfseStatus::Autorizada)->and($this->nota->fresh()->cancelada_em)->toBeNull();
});

it('devolve o pdf da nota autorizada e da cancelada, e recusa sem numero', function () {
    $fake = comGatewayNfse(['pdf' => '%PDF-1.4 nota']);

    expect(app(NfseEmissor::class)->pdf($this->nota))->toStartWith('%PDF')
        ->and($fake->chamadas[0]['args']['numero'])->toBe('700');

    $this->nota->forceFill(['status' => NfseStatus::Cancelada])->save();
    expect(app(NfseEmissor::class)->pdf($this->nota->fresh()))->toStartWith('%PDF');

    $this->nota->forceFill(['status' => NfseStatus::Rejeitada, 'numero_nfse' => null])->save();
    expect(erroDe(fn () => app(NfseEmissor::class)->pdf($this->nota->fresh())))->toContain('não tem número');
});

it('pdf e cancelamento usam o ambiente gravado na nota, mesmo depois de a configuracao mudar', function () {
    $this->emitente->nfse->update(['ambiente' => Ambiente::Producao, 'senha_producao' => 'segredo-prod']);
    $fake = comGatewayNfse(['pdf' => '%PDF-1.4', 'cancelar' => new RespostaNfse(true)]);

    app(NfseEmissor::class)->pdf($this->nota->fresh());
    app(NfseEmissor::class)->cancelar($this->nota->fresh(), 'Serviço cancelado a pedido do cliente.');

    expect($fake->chamadas[0]['args']['ambiente'])->toBe(Ambiente::Homologacao)
        ->and($fake->chamadas[1]['args']['ambiente'])->toBe(Ambiente::Homologacao);
});
