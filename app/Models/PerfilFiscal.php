<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupo de tributação reutilizável, escrito pelo contador.
 *
 * Auditável de propósito: mudança de regra fiscal é registro de
 * responsabilidade, não conveniência.
 */
class PerfilFiscal extends Model
{
    use Auditavel, DoTenantViaEmitente;

    /**
     * Defaults também em memória, não só no banco: coluna booleana `true`
     * vem `null` num model recém instanciado, e `null` é falsy.
     */
    protected $attributes = [
        'ativo' => true,
    ];

    protected $table = 'perfis_fiscais';

    protected $fillable = ['emitente_id', 'nome', 'descricao', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function regras(): HasMany
    {
        return $this->hasMany(PerfilFiscalRegra::class)->orderByDesc('vigente_de');
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }
}
