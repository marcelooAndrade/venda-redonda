<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaEntrada extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'notas_entrada';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_emissao' => 'datetime',
            'confirmada_em' => 'datetime',
            'valor_produtos' => 'decimal:2',
            'valor_frete' => 'decimal:2',
            'valor_ipi' => 'decimal:2',
            'valor_nota' => 'decimal:2',
        ];
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    /** O fornecedor da nota. */
    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NotaEntradaItem::class)->orderBy('numero');
    }

    public function confirmadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmada_por');
    }

    public function totalmenteConciliada(): bool
    {
        return $this->itens->every(fn (NotaEntradaItem $i): bool => $i->produto_id !== null);
    }

    public function confirmada(): bool
    {
        return $this->status === 'confirmada';
    }
}
