<?php

namespace App\Models;

use App\Enums\Fiscal\TipoMovimentoEstoque;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Uma linha do razão de estoque. Imutável por construção.
 *
 * Não usa a trait Auditavel: o próprio movimento já é o registro de
 * auditoria, com autor, documento e horário.
 */
class EstoqueMovimento extends Model
{
    use DoTenantViaEmitente;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimentoEstoque::class,
            'quantidade' => 'decimal:4',
            'custo_unitario' => 'decimal:4',
            'saldo_apos' => 'decimal:4',
            'custo_medio_apos' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException(
                'Movimento de estoque é imutável. Para corrigir, registre um estorno.'
            );
        });

        static::deleting(function (): void {
            throw new RuntimeException(
                'Movimento de estoque é imutável. Para corrigir, registre um estorno.'
            );
        });
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Quantidade com sinal, para leitura no Kardex. */
    public function quantidadeComSinal(): float
    {
        $q = (float) $this->quantidade;

        return $this->tipo->positivo() ? $q : -$q;
    }
}
