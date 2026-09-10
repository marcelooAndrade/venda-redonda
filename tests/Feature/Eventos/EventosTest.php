<?php

use App\Enums\Fiscal\NFeStatus;
use App\Models\Emitente;
use App\Models\EstoqueMovimento;
use App\Models\Inutilizacao;
use App\Models\Nota;
use App\Models\NotaEvento;
use App\Models\User;
use App\Services\Fiscal\NFeEventService;
use App\Services\Fiscal\NFeTransmitter;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\Storage;

function notaAutorizada(array $roteiro = []): Nota
{
    comGateway(array_merge(['enviar' => autorizada()], $roteiro));
    $nota = notaPronta(['numero' => null]);
    app(NFeTransmitter::class)->transmitir($nota, User::factory()->create());

    return $nota->fresh();
}

beforeEach(function () {
    Storage::fake('fiscal');
    $this->user = User::factory()->create();
});

/**
 * Resolvido depois de `comGateway`, e não no beforeEach: o container injeta
 * o gateway real se o serviço for construído antes do falso ser registrado.
 */
function eventos(): NFeEventService
{
    return app(NFeEventService::class);
}

describe('cancelamento', function () {
    it('cancela a nota e guarda o protocolo do evento', function () {
        $nota = notaAutorizada(['cancelar' => new RespostaSefaz('135', 'Evento registrado e vinculado a NF-e', '135260000999888')]);

        eventos()->cancelar($nota, 'Erro na descricao do produto informada pelo cliente', $this->user);

        expect($nota->fresh()->status)->toBe(NFeStatus::Cancelada)
            ->and(NotaEvento::where('tipo', '110111')->first()->protocolo)->toBe('135260000999888');
    });

    it('estorna o estoque ao cancelar', function () {
        $nota = notaAutorizada(['cancelar' => new RespostaSefaz('135', 'Evento registrado')]);
        $antes = app(StockService::class)->saldo($nota->itens[0]->produto)->quantidade;

        eventos()->cancelar($nota, 'Cliente desistiu da compra apos o faturamento', $this->user);

        expect(app(StockService::class)->saldo($nota->itens[0]->produto)->quantidade)->toBe($antes + 10.0)
            ->and(EstoqueMovimento::where('tipo', 'estorno')->count())->toBe(1);
    });

    it('exige justificativa de ao menos 15 caracteres', function () {
        $nota = notaAutorizada();

        eventos()->cancelar($nota, 'Errei', $this->user);
    })->throws(RuntimeException::class, '15 caracteres');

    it('recusa cancelar nota que nao esta autorizada', function () {
        comGateway([]);

        eventos()->cancelar(notaPronta(), 'Justificativa suficientemente longa aqui', $this->user);
    })->throws(RuntimeException::class, 'autorizada');

    it('recusa cancelar fora do prazo legal', function () {
        $nota = notaAutorizada();
        $nota->forceFill(['autorizada_em' => now()->subDays(3)])->save();

        eventos()->cancelar($nota->fresh(), 'Justificativa suficientemente longa aqui', $this->user);
    })->throws(RuntimeException::class, 'prazo');

    it('nao cancela nem estorna quando a sefaz recusa o evento', function () {
        $nota = notaAutorizada(['cancelar' => new RespostaSefaz('573', 'Duplicidade de evento')]);

        try {
            eventos()->cancelar($nota, 'Justificativa suficientemente longa aqui', $this->user);
        } catch (RuntimeException) {
            // esperado
        }

        expect($nota->fresh()->status)->toBe(NFeStatus::Autorizada)
            ->and(EstoqueMovimento::where('tipo', 'estorno')->count())->toBe(0);
    });
});

describe('carta de correção', function () {
    it('registra a cce com sequencia um', function () {
        $nota = notaAutorizada(['cartaCorrecao' => new RespostaSefaz('135', 'Evento registrado', '135260000777666')]);

        $evento = eventos()->cartaCorrecao($nota, 'Onde se le peca leia-se peca microfundida em aco inox 316L', $this->user);

        expect($evento->sequencia)->toBe(1)
            ->and($evento->protocolo)->toBe('135260000777666')
            // A nota segue autorizada: carta de correção não muda o status.
            ->and($nota->fresh()->status)->toBe(NFeStatus::Autorizada);
    });

    it('incrementa a sequencia a cada nova carta', function () {
        $nota = notaAutorizada(['cartaCorrecao' => new RespostaSefaz('135', 'Evento registrado')]);

        eventos()->cartaCorrecao($nota, 'Primeira correcao com texto suficientemente longo', $this->user);
        $segunda = eventos()->cartaCorrecao($nota, 'Segunda correcao com texto suficientemente longo', $this->user);

        expect($segunda->sequencia)->toBe(2);
    });

    it('bloqueia depois da vigesima carta', function () {
        $nota = notaAutorizada(['cartaCorrecao' => new RespostaSefaz('135', 'Evento registrado')]);

        for ($i = 1; $i <= 20; $i++) {
            eventos()->cartaCorrecao($nota, "Correcao numero {$i} com texto suficientemente longo", $this->user);
        }

        eventos()->cartaCorrecao($nota, 'Vigesima primeira correcao com texto longo', $this->user);
    })->throws(RuntimeException::class, '20');

    it('exige texto de ao menos 15 caracteres', function () {
        $nota = notaAutorizada();

        eventos()->cartaCorrecao($nota, 'Curto', $this->user);
    })->throws(RuntimeException::class, '15 caracteres');

    it('recusa corrigir valor, que a lei veda', function () {
        $nota = notaAutorizada();

        eventos()->cartaCorrecao(
            $nota,
            'Corrigir o valor total da nota de 100 para 200 reais',
            $this->user,
        );
    })->throws(RuntimeException::class, 'não pode');

    it('recusa corrigir dados do destinatario, que a lei veda', function () {
        $nota = notaAutorizada();

        eventos()->cartaCorrecao(
            $nota,
            'Alterar o CNPJ do destinatario para outro cadastro',
            $this->user,
        );
    })->throws(RuntimeException::class, 'não pode');

    it('recusa corrigir campo de exportacao da DU-E, que a lei veda', function () {
        $nota = notaAutorizada();

        eventos()->cartaCorrecao(
            $nota,
            'Informar o numero da DU-E que faltou no grupo de exportacao',
            $this->user,
        );
    })->throws(RuntimeException::class, 'não pode');

    it('recusa incluir ou alterar parcela, que a lei veda', function () {
        $nota = notaAutorizada();

        eventos()->cartaCorrecao(
            $nota,
            'Incluir a segunda parcela do pagamento que ficou de fora',
            $this->user,
        );
    })->throws(RuntimeException::class, 'não pode');
});

describe('inutilização', function () {
    it('inutiliza a faixa e guarda o protocolo', function () {
        comGateway(['inutilizar' => new RespostaSefaz('102', 'Inutilizacao de numero homologado', '135260000555444')]);
        $emitente = Emitente::factory()->create();

        $inut = eventos()->inutilizar($emitente, 1, 10, 12, 'Falha no sistema durante a emissao das notas', $this->user);

        expect($inut->protocolo)->toBe('135260000555444')
            ->and($inut->homologada_em)->not->toBeNull()
            ->and(Inutilizacao::count())->toBe(1);
    });

    it('recusa faixa invertida', function () {
        comGateway(['inutilizar' => new RespostaSefaz('102', 'ok')]);

        eventos()->inutilizar(Emitente::factory()->create(), 1, 20, 10, 'Justificativa suficientemente longa', $this->user);
    })->throws(RuntimeException::class, 'faixa');

    it('recusa inutilizar numero ja usado', function () {
        $nota = notaAutorizada(['inutilizar' => new RespostaSefaz('102', 'ok')]);

        eventos()->inutilizar(
            $nota->emitente,
            (int) $nota->serie,
            (int) $nota->numero,
            (int) $nota->numero,
            'Justificativa suficientemente longa',
            $this->user,
        );
    })->throws(RuntimeException::class, 'já foi usado');
});
