<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaEntradaItem extends Model
{
    // O pluralizador do Eloquent produziria "nota_entrada_items".
    protected $table = 'nota_entrada_itens';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:4',
            'valor_unitario' => 'decimal:4',
            'valor_total' => 'decimal:2',
            'custo_unitario' => 'decimal:4',
            'fator_conversao' => 'decimal:6',
        ];
    }

    public function notaEntrada(): BelongsTo
    {
        return $this->belongsTo(NotaEntrada::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    /** Quantidade na unidade interna, aplicando a conversão do fornecedor. */
    public function quantidadeInterna(): float
    {
        return round((float) $this->quantidade * (float) $this->fator_conversao, 4);
    }

    /** Custo por unidade interna. */
    public function custoInterno(): float
    {
        $fator = (float) $this->fator_conversao;

        return $fator > 0 ? round((float) $this->custo_unitario / $fator, 4) : (float) $this->custo_unitario;
    }
}
