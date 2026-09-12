<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um serviço do catálogo do emitente, com os códigos que a NFS-e pede.
 *
 * A alíquota é inteiro em centésimos de ponto percentual, pela mesma regra
 * do dinheiro em centavos: 200 é 2,00%, e nada vira float até o XML.
 */
class ServicoNfse extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'servicos_nfse';

    protected $guarded = ['id'];

    protected $attributes = [
        'aliquota_iss_bp' => 0,
        'iss_retido' => false,
        'ativo' => true,
    ];

    protected function casts(): array
    {
        return [
            'aliquota_iss_bp' => 'integer',
            'iss_retido' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    /** Lista de serviços da LC 116, no formato que o SIGISS usa. */
    public static function codigoValido(string $codigo): bool
    {
        return (bool) preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $codigo);
    }

    /** `2,00`, como a pessoa digita e como o XML manda. */
    public function aliquotaFormatada(): string
    {
        return Dinheiro::formatar((int) $this->aliquota_iss_bp);
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }
}
