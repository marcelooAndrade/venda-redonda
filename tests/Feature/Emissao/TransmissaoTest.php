<?php

use App\Enums\Fiscal\NFeStatus;
use App\Models\Emitente;
use App\Models\EstoqueMovimento;
use App\Models\SefazLog;
use App\Models\User;
use App\Services\Fiscal\NFeTransmitter;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Fiscal\SefazGateway;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\Storage;

/** Gateway falso, roteirizado. Guarda o que foi chamado, para as asserções. */
function gatewayFake(array $roteiro): SefazGateway
{
    return new class($roteiro) implements SefazGateway
    {
        public array $chamadas = [];

        public function __construct(private array $roteiro) {}

        public function enviar(Emitente $e, string $xml): RespostaSefaz
        {
            $this->chamadas[] = 'enviar';
            $r = $this->roteiro['enviar'] ?? null;

            if ($r instanceof Throwable) {
                throw $r;
            }

            return $r;
        }

        public function consultarRecibo(Emitente $e, string $recibo): RespostaSefaz
        {
            $this->chamadas[] = 'consultarRecibo';

            return $this->roteiro['consultarRecibo'];
        }

        public function consultarChave(Emitente $e, string $chave): RespostaSefaz
        {
            $this->chamadas[] = 'consultarChave';

            return $this->roteiro['consultarChave'];
        }

        public function statusServico(Emitente $e): RespostaSefaz
        {
            $this->chamadas[] = 'statusServico';

            return $this->roteiro['statusServico'] ?? new RespostaSefaz('107', 'Servico em operacao');
        }
    };
}

function comGateway(array $roteiro): object
{
    $fake = gatewayFake($roteiro);
    app()->instance(SefazGateway::class, $fake);

    return $fake;
}

function autorizada(string $chave = '35260911222333000181550010000014801033717992'): RespostaSefaz
{
    return new RespostaSefaz('100', 'Autorizado o uso da NF-e', '135260000123456', null, '<nfeProc/>', $chave);
}

beforeEach(function () {
    Storage::fake('fiscal');
    $this->user = User::factory()->create();
});

describe('caminho feliz', function () {
    it('autoriza a nota e grava o protocolo', function () {
        comGateway(['enviar' => autorizada()]);
        $nota = notaPronta(['numero' => null]);

        app(NFeTransmitter::class)->transmitir($nota, $this->user);

        $nota = $nota->fresh();
        expect($nota->status)->toBe(NFeStatus::Autorizada)
            ->and($nota->protocolo)->toBe('135260000123456')
            ->and($nota->c_stat)->toBe('100')
            ->and($nota->autorizada_em)->not->toBeNull();
    });

    it('atribui o numero apenas na transmissao', function () {
        comGateway(['enviar' => autorizada()]);
        $nota = notaPronta(['numero' => null]);

        expect($nota->numero)->toBeNull();

        app(NFeTransmitter::class)->transmitir($nota, $this->user);

        expect($nota->fresh()->numero)->toBe(1);
    });

    it('registra a comunicacao no log', function () {
        comGateway(['enviar' => autorizada()]);

        app(NFeTransmitter::class)->transmitir(notaPronta(['numero' => null]), $this->user);

        expect(SefazLog::where('operacao', 'autorizacao')->count())->toBe(1);
    });
});

describe('rejeição', function () {
    it('guarda cStat e motivo e permite corrigir', function () {
        comGateway(['enviar' => new RespostaSefaz('539', 'Duplicidade de NF-e com diferenca na chave de acesso')]);
        $nota = notaPronta(['numero' => null]);

        app(NFeTransmitter::class)->transmitir($nota, $this->user);

        $nota = $nota->fresh();
        expect($nota->status)->toBe(NFeStatus::Rejeitada)
            ->and($nota->c_stat)->toBe('539')
            ->and($nota->x_motivo)->toContain('Duplicidade')
            ->and($nota->editavel())->toBeTrue();
    });

    it('nao baixa estoque quando rejeitada', function () {
        comGateway(['enviar' => new RespostaSefaz('225', 'Falha no Schema XML')]);

        app(NFeTransmitter::class)->transmitir(notaPronta(['numero' => null]), $this->user);

        // A entrada inicial do helper continua lá; o que não pode existir é saída.
        expect(EstoqueMovimento::where('tipo', 'saida')->count())->toBe(0);
    });
});

describe('denegação', function () {
    it('e terminal e nao permite correcao', function () {
        comGateway(['enviar' => new RespostaSefaz('110', 'Uso Denegado')]);
        $nota = notaPronta(['numero' => null]);

        app(NFeTransmitter::class)->transmitir($nota, $this->user);

        $nota = $nota->fresh();
        expect($nota->status)->toBe(NFeStatus::Denegada)
            ->and($nota->status->terminal())->toBeTrue()
            ->and($nota->editavel())->toBeFalse();
    });
});

describe('idempotência', function () {
    it('consulta a chave antes de retransmitir depois de falha de rede', function () {
        $fake = comGateway([
            'enviar' => new RuntimeException('cURL error 28: timeout'),
            'consultarChave' => new RespostaSefaz('217', 'NF-e nao consta na base da SEFAZ'),
        ]);
        $nota = notaPronta(['numero' => null]);

        app(NFeTransmitter::class)->transmitir($nota, $this->user);

        // Antes de qualquer reenvio, perguntou à SEFAZ se ela já tinha recebido.
        expect($fake->chamadas)->toContain('consultarChave');
    });

    it('nao retransmite quando a sefaz ja autorizou', function () {
        $fake = comGateway([
            'enviar' => new RuntimeException('cURL error 28: timeout'),
            'consultarChave' => autorizada(),
        ]);
        $nota = notaPronta(['numero' => null]);

        app(NFeTransmitter::class)->transmitir($nota, $this->user);

        expect($nota->fresh()->status)->toBe(NFeStatus::Autorizada)
            // Enviou uma vez só: a segunda seria duplicidade e queimaria o número.
            ->and(collect($fake->chamadas)->filter(fn ($c) => $c === 'enviar'))->toHaveCount(1);
    });

    it('recusa transmitir nota ja autorizada', function () {
        comGateway(['enviar' => autorizada()]);
        $nota = notaPronta(['numero' => null]);
        app(NFeTransmitter::class)->transmitir($nota, $this->user);

        app(NFeTransmitter::class)->transmitir($nota->fresh(), $this->user);
    })->throws(RuntimeException::class, 'já está autorizada');
});

describe('lote em processamento', function () {
    it('consulta o recibo quando o lote so foi recebido', function () {
        $fake = comGateway([
            'enviar' => new RespostaSefaz('103', 'Lote recebido com sucesso', null, '351260000987654'),
            'consultarRecibo' => autorizada(),
        ]);
        $nota = notaPronta(['numero' => null]);

        app(NFeTransmitter::class)->transmitir($nota, $this->user);

        expect($fake->chamadas)->toContain('consultarRecibo')
            ->and($nota->fresh()->status)->toBe(NFeStatus::Autorizada);
    });
});

describe('estoque', function () {
    it('baixa o estoque na autorizacao', function () {
        comGateway(['enviar' => autorizada()]);
        $nota = notaPronta(['numero' => null]);
        $produto = $nota->itens[0]->produto;
        $antes = app(StockService::class)->saldo($produto)->quantidade;

        app(NFeTransmitter::class)->transmitir($nota, $this->user);

        expect(app(StockService::class)->saldo($produto)->quantidade)->toBe($antes - 10.0);
    });

    it('recusa transmitir sem saldo, antes de queimar numero', function () {
        comGateway(['enviar' => autorizada()]);
        $nota = notaPronta(['numero' => null]);
        // Zera o saldo levando tudo embora.
        app(StockService::class)->saida($nota->itens[0]->produto, 1000, 'Outra saida', $this->user);

        try {
            app(NFeTransmitter::class)->transmitir($nota, $this->user);
            $this->fail('deveria ter recusado');
        } catch (RuntimeException $e) {
            expect($e->getMessage())->toContain('Saldo insuficiente');
        }

        // Nenhum número foi consumido.
        expect($nota->fresh()->numero)->toBeNull();
    });

    it('registra a nota como documento do movimento', function () {
        comGateway(['enviar' => autorizada()]);
        $nota = notaPronta(['numero' => null]);
        app(NFeTransmitter::class)->transmitir($nota, $this->user);

        expect(EstoqueMovimento::where('tipo', 'saida')->first()->documento)->toContain('NF-e');
    });
});
