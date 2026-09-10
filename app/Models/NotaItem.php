<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaItem extends Model
{
    protected $table = 'nota_itens';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:4',
            'quantidade_tributavel' => 'decimal:4',
            'valor_unitario' => 'decimal:10',
            'valor_produto' => 'decimal:2',
        ];
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}
