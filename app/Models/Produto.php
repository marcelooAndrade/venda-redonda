<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use App\Support\Gtin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produto extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fator_conversao' => 'decimal:6',
            'preco_venda' => 'decimal:4',
            'custo' => 'decimal:4',
            'peso_liquido' => 'decimal:3',
            'peso_bruto' => 'decimal:3',
            'estoque_minimo' => 'decimal:4',
            'controla_estoque' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Produto $produto): void {
            // A NF-e espera o literal "SEM GTIN" quando não há código de barras.
            $produto->gtin = Gtin::normalizar($produto->gtin);
            $produto->gtin_tributavel = Gtin::normalizar($produto->gtin_tributavel ?: $produto->gtin);
            $produto->ncm = preg_replace('/\D/', '', (string) $produto->ncm);
        });
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function perfilFiscal(): BelongsTo
    {
        return $this->belongsTo(PerfilFiscal::class);
    }

    /** Unidade tributável diferente da comercial exige converter a quantidade. */
    public function convertePara(float $quantidadeComercial): float
    {
        return round($quantidadeComercial * (float) $this->fator_conversao, 4);
    }
}
