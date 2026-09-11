<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plano de contas hierárquico, com código de até três níveis.
 *
 * Portado do projeto Marcelo Andrade, onde o código tem o formato
 * `999.999.999` e os centros se organizam por `parent_id`. O formato é o que
 * permite ao contador reconhecer a estrutura sem precisar aprender a do
 * sistema.
 */
class CentroCusto extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'centros_custo';

    protected $guarded = ['id'];

    protected $attributes = [
        'grupo' => false,
        'ativo' => true,
    ];

    protected function casts(): array
    {
        return ['grupo' => 'boolean', 'ativo' => 'boolean', 'essencial' => 'boolean'];
    }

    /** Um a três grupos de três dígitos, separados por ponto. */
    public static function codigoValido(string $codigo): bool
    {
        return (bool) preg_match('/^\d{3}(\.\d{3}){0,2}$/', $codigo);
    }

    public static function nivelDoCodigo(string $codigo): int
    {
        return self::codigoValido($codigo) ? substr_count($codigo, '.') + 1 : 0;
    }

    /** Agrupa de três em três o que a pessoa digitou, descartando o excedente. */
    public static function formatarCodigo(string $valor): string
    {
        $digitos = substr((string) preg_replace('/\D/', '', $valor), 0, 9);

        return implode('.', str_split($digitos, 3));
    }

    /**
     * Classificação individual vence a do grupo.
     *
     * Nulo aqui não é "não essencial": é "herda de quem está acima". Sem essa
     * distinção não dá para calcular custo essencial mensal, que é a base da
     * reserva e dos meses de sobrevivência.
     */
    public function eEssencial(): bool
    {
        if ($this->essencial !== null) {
            return $this->essencial;
        }

        return $this->pai?->eEssencial() ?? false;
    }

    public function pai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pai_id');
    }

    public function filhos(): HasMany
    {
        return $this->hasMany(self::class, 'pai_id');
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }
}
