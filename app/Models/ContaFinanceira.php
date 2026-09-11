<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conta em que o dinheiro de fato está: banco, poupança ou caixa.
 *
 * O saldo nunca é gravado. Ele é a soma do razão sobre o saldo inicial, pelo
 * mesmo motivo do estoque: saldo gravado e razão divergem no primeiro erro, e
 * aí ninguém sabe qual dos dois está certo.
 */
class ContaFinanceira extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'contas_financeiras';

    protected $guarded = ['id'];

    protected $attributes = [
        'tipo' => 'corrente',
        'padrao' => false,
        'ativo' => true,
        'saldo_inicial_centavos' => 0,
    ];

    protected function casts(): array
    {
        return [
            'saldo_inicial_centavos' => 'integer',
            'padrao' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function saldoCentavos(): int
    {
        $creditos = (int) $this->movimentos()->where('sentido', 'credito')->sum('valor_centavos');
        $debitos = (int) $this->movimentos()->where('sentido', 'debito')->sum('valor_centavos');

        return (int) $this->saldo_inicial_centavos + $creditos - $debitos;
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(MovimentoCaixa::class);
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }
}
