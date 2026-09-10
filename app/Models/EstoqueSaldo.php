<?php

namespace App\Models;

use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstoqueSaldo extends Model
{
    use DoTenantViaEmitente;

    protected $table = 'estoque_saldos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'float',
            'custo_medio' => 'float',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function valorTotal(): float
    {
        return round($this->quantidade * $this->custo_medio, 2);
    }

    public function abaixoDoMinimo(): bool
    {
        return $this->quantidade < (float) $this->produto->estoque_minimo;
    }
}
