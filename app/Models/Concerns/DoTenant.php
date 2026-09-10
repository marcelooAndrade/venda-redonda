<?php

namespace App\Models\Concerns;

use App\Support\TenantAtual;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Escopo de tenant para models que carregam `tenant_id` diretamente.
 *
 * Sem tenant resolvido, a consulta não devolve nada. O padrão seguro é o
 * silêncio: um bug de resolução vira "não encontrei", nunca "olha o dado
 * do vizinho".
 */
trait DoTenant
{
    public static function bootDoTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $id = app(TenantAtual::class)->id();

            $query->where($query->getModel()->getTable().'.tenant_id', $id ?? -1);
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', app(TenantAtual::class)->id());
            }
        });
    }
}
