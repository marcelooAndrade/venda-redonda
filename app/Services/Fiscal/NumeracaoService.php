<?php

namespace App\Services\Fiscal;

use App\Models\Emitente;
use App\Models\EmitenteSerie;
use App\Models\Nota;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Atribui o número da NF-e.
 *
 * A sequência não pode ter buraco nem repetição: número pulado obriga
 * inutilização formal na SEFAZ, e número repetido é rejeição imediata.
 * Por isso a atribuição é atômica e acontece só na hora de transmitir,
 * nunca ao salvar o rascunho.
 */
class NumeracaoService
{
    private const SERIE_MAXIMA = 999;

    public function proximo(Emitente $emitente, int $serie): int
    {
        $this->conferirSerie($serie);

        return DB::transaction(function () use ($emitente, $serie): int {
            $registro = EmitenteSerie::query()->firstOrCreate(
                ['emitente_id' => $emitente->getKey(), 'serie' => $serie],
                ['proximo_numero' => 1],
            );

            // Lock pessimista: sem ele, duas transmissões simultâneas leem o
            // mesmo próximo número e a segunda é rejeitada por duplicidade.
            $registro = EmitenteSerie::query()->lockForUpdate()->find($registro->getKey());

            $numero = (int) $registro->proximo_numero;

            $registro->forceFill(['proximo_numero' => $numero + 1])->save();

            return $numero;
        });
    }

    /** Só consulta, sem consumir. Para a tela mostrar a previsão. */
    public function previsto(Emitente $emitente, int $serie): int
    {
        $this->conferirSerie($serie);

        return (int) (EmitenteSerie::query()
            ->where('emitente_id', $emitente->getKey())
            ->where('serie', $serie)
            ->value('proximo_numero') ?? 1);
    }

    /**
     * Faixas que ficaram sem uso e precisam de inutilização formal.
     *
     * @return array<int, array{de: int, ate: int}>
     */
    public function faixasNaoUtilizadas(Emitente $emitente, int $serie): array
    {
        $usados = Nota::query()
            ->where('emitente_id', $emitente->getKey())
            ->where('serie', $serie)
            ->whereNotNull('numero')
            ->orderBy('numero')
            ->pluck('numero')
            ->map(fn ($n) => (int) $n)
            ->all();

        if (count($usados) < 2) {
            return [];
        }

        $faixas = [];

        for ($i = 1; $i < count($usados); $i++) {
            $anterior = $usados[$i - 1];
            $atual = $usados[$i];

            if ($atual - $anterior > 1) {
                $faixas[] = ['de' => $anterior + 1, 'ate' => $atual - 1];
            }
        }

        return $faixas;
    }

    private function conferirSerie(int $serie): void
    {
        if ($serie < 1 || $serie > self::SERIE_MAXIMA) {
            throw new InvalidArgumentException(
                "A série {$serie} está fora da faixa aceita pela NF-e, que vai de 1 a ".self::SERIE_MAXIMA.'.'
            );
        }
    }
}
