<?php

namespace App\Services\Fiscal;

use App\Enums\Fiscal\AmbitoOperacao;
use App\Models\PerfilFiscal;
use App\Models\PerfilFiscalRegra;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Encontra a regra tributária aplicável.
 *
 * A data importa: regra fiscal muda, e uma nota emitida em março precisa
 * continuar refletindo a regra que valia em março, não a de hoje. Por isso a
 * busca é sempre pela data da operação, nunca por "a regra atual".
 */
class ResolverRegraFiscal
{
    public function resolver(
        PerfilFiscal $perfil,
        AmbitoOperacao $ambito,
        string $crt,
        CarbonInterface|string $data,
    ): PerfilFiscalRegra {
        $data = Carbon::parse($data)->startOfDay();

        $candidatas = PerfilFiscalRegra::query()
            ->where('perfil_fiscal_id', $perfil->getKey())
            ->where('ambito', $ambito->value)
            ->whereDate('vigente_de', '<=', $data)
            ->where(fn ($q) => $q->whereNull('vigente_ate')->orWhereDate('vigente_ate', '>=', $data))
            // Regra escrita para o CRT específico ganha da genérica.
            ->orderByRaw('CASE WHEN crt IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('vigente_de')
            ->orderByDesc('id')
            ->get();

        $regra = $candidatas->first(fn (PerfilFiscalRegra $r): bool => $r->crt === $crt)
            ?? $candidatas->first(fn (PerfilFiscalRegra $r): bool => $r->crt === null);

        if ($regra === null) {
            throw new RuntimeException(
                "Não há regra fiscal vigente em {$data->format('d/m/Y')} para o perfil \"{$perfil->nome}\" "
                ."na operação {$ambito->rotulo()}. Peça ao contador para cadastrar a regra antes de emitir."
            );
        }

        return $regra;
    }
}
