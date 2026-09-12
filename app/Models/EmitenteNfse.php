<?php

namespace App\Models;

use App\Enums\Fiscal\Ambiente;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Configuração da NFS-e de um emitente: se emite, em que ambiente, com
 * qual senha e a partir de qual RPS.
 *
 * As senhas ficam em `$hidden`, então a auditoria as omite sozinha.
 */
class EmitenteNfse extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'emitente_nfse';

    protected $guarded = ['id'];

    protected $hidden = ['senha_homologacao', 'senha_producao'];

    protected $attributes = [
        'habilitado' => false,
        'ambiente' => 'homologacao',
        'serie_rps' => '1',
        'proximo_rps_homologacao' => 1,
        'proximo_rps_producao' => 1,
    ];

    protected function casts(): array
    {
        return [
            'habilitado' => 'boolean',
            'ambiente' => Ambiente::class,
            // Falha de decriptação estoura, não vira null em silêncio.
            'senha_homologacao' => 'encrypted',
            'senha_producao' => 'encrypted',
            'proximo_rps_homologacao' => 'integer',
            'proximo_rps_producao' => 'integer',
            'producao_ativada_em' => 'datetime',
        ];
    }

    public function senha(Ambiente $ambiente): ?string
    {
        return $ambiente === Ambiente::Producao ? $this->senha_producao : $this->senha_homologacao;
    }

    public function colunaProximoRps(Ambiente $ambiente): string
    {
        return $ambiente === Ambiente::Producao ? 'proximo_rps_producao' : 'proximo_rps_homologacao';
    }

    public function proximoRps(Ambiente $ambiente): int
    {
        return (int) $this->getAttribute($this->colunaProximoRps($ambiente));
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }
}
