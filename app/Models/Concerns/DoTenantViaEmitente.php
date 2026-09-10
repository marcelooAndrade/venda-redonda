<?php

namespace App\Models\Concerns;

use App\Support\TenantAtual;
use Illuminate\Database\Eloquent\Builder;

/**
 * Escopo de tenant para models que pertencem a um emitente. O vínculo com o
 * tenant é indireto, então o filtro passa pela tabela de emitentes.
 */
trait DoTenantViaEmitente
{
    public static function bootDoTenantViaEmitente(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $id = app(TenantAtual::class)->id();
            $tabela = $query->getModel()->getTable();

            $query->whereIn("{$tabela}.emitente_id", function ($sub) use ($id): void {
                $sub->select('id')->from('emitentes')->where('tenant_id', $id ?? -1);
            });
        });
    }
}
