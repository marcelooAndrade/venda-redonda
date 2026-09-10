<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Support\TemaMarca;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use Auditavel;

    /**
     * Default também em memória, não só no banco. Um tenant recém-criado
     * precisa já se dizer ativo, senão quem o consulta antes de reler recebe
     * null e o trata como inativo.
     */
    protected $attributes = [
        'ativo' => true,
    ];

    protected $fillable = ['nome', 'nome_curto', 'slug', 'dominio', 'logo_path', 'tema', 'ativo'];

    protected function casts(): array
    {
        return ['tema' => 'array', 'ativo' => 'boolean'];
    }

    public function emitentes(): HasMany
    {
        return $this->hasMany(Emitente::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Null quando o tenant não tem marca própria: vale o padrão do bundle. */
    public function marca(): ?TemaMarca
    {
        return TemaMarca::deArray($this->tema ?? []);
    }

    public function rotulo(): string
    {
        return $this->nome_curto ?: $this->nome;
    }
}
