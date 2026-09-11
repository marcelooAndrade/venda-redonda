<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContaPagar extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'contas_pagar';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'pendente'];

    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'vencimento' => 'date',
            'pago_em' => 'datetime',
        ];
    }

    public function estaPendente(): bool
    {
        return $this->status === 'pendente';
    }

    /** Vencido é pendente com vencimento no passado, e não um status gravado. */
    public function estaVencido(?string $hoje = null): bool
    {
        return $this->estaPendente()
            && $this->vencimento->toDateString() < ($hoje ?? today()->toDateString());
    }

    public function centroCusto(): BelongsTo
    {
        return $this->belongsTo(CentroCusto::class, 'centro_custo_id');
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
