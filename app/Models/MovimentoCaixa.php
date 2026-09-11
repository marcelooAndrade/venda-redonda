<?php

namespace App\Models;

use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Uma linha do razão de caixa. Imutável por construção, como o movimento de
 * estoque.
 *
 * Guarda de qual título veio, em `origem_tipo` e `origem_id`. É isso que torna
 * a conciliação possível: sem a origem, um crédito de R$ 350 no extrato é só um
 * número que ninguém sabe explicar.
 */
class MovimentoCaixa extends Model
{
    use DoTenantViaEmitente;

    public const UPDATED_AT = null;

    protected $table = 'movimentos_caixa';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'ocorrido_em' => 'date',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException(
                'Movimento de caixa é imutável. Para corrigir, registre um lançamento contrário.'
            );
        });

        static::deleting(function (): void {
            throw new RuntimeException(
                'Movimento de caixa é imutável. Para corrigir, registre um lançamento contrário.'
            );
        });
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaFinanceira::class, 'conta_financeira_id');
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }
}
