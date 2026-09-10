<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vínculo entre o código que o fornecedor usa e o produto interno.
 *
 * Salvo uma vez na conciliação, a próxima nota do mesmo fornecedor já entra
 * conciliada. É o que faz a importação deixar de ser trabalho manual.
 */
class ProdutoFornecedor extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'produto_fornecedor';

    protected $guarded = ['id'];

    protected $attributes = [
        'fator_conversao' => 1,
    ];

    protected function casts(): array
    {
        return ['fator_conversao' => 'decimal:6'];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }
}
