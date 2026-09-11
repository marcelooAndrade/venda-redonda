<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contas a receber, do jeito da origem: a fatura é o acordo, e o título é a
 * parcela.
 *
 * Uma venda em três vezes são três títulos com vencimentos próprios, e não um
 * título com data única. É a diferença entre cobrar certo e cobrar no chute.
 */
class Fatura extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'faturas';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'ativa'];

    public function parcelas(): HasMany
    {
        return $this->hasMany(FaturaParcela::class)->orderBy('numero');
    }

    public function totalCentavos(): int
    {
        return (int) $this->parcelas()->where('status', '!=', 'cancelado')->sum('valor_centavos');
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }
}
