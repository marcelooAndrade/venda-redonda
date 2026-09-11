<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O título de contas a receber.
 *
 * Não usa `DoTenantViaEmitente` porque não tem `emitente_id`: o escopo vem da
 * fatura, que já é escopada. Duplicar a coluna criaria duas verdades sobre de
 * quem é a parcela.
 */
class FaturaParcela extends Model
{
    use Auditavel;

    protected $table = 'fatura_parcelas';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'pendente'];

    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'numero' => 'integer',
            'vencimento' => 'date',
            'pago_em' => 'datetime',
        ];
    }

    public function estaPendente(): bool
    {
        return $this->status === 'pendente';
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaFinanceira::class, 'conta_financeira_id');
    }
}
