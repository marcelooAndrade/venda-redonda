<?php

namespace App\Models;

use App\Enums\Fiscal\Ambiente;
use App\Enums\Nfse\NfseStatus;
use App\Enums\Nfse\ProvedorNfse;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma NFS-e, ou a tentativa de emitir uma.
 *
 * Nasce em `processando` com o RPS reservado. Rejeitada e erro guardam o
 * RPS para a nova tentativa; autorizada e cancelada são o documento.
 */
class NotaServico extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'notas_servico';

    protected $guarded = ['id'];

    protected $attributes = [
        'provedor' => 'sigiss',
        'iss_retido' => false,
    ];

    protected function casts(): array
    {
        return [
            'provedor' => ProvedorNfse::class,
            'ambiente' => Ambiente::class,
            'status' => NfseStatus::class,
            'numero_rps' => 'integer',
            'aliquota_iss_bp' => 'integer',
            'iss_retido' => 'boolean',
            'valor_centavos' => 'integer',
            'emitida_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    /** "NFS-e 700" quando o SIGISS numerou; "RPS 12" antes disso. */
    public function documento(): string
    {
        return filled($this->numero_nfse) ? "NFS-e {$this->numero_nfse}" : "RPS {$this->numero_rps}";
    }

    public function temDocumento(): bool
    {
        return $this->status->temDocumento() && filled($this->numero_nfse);
    }

    public function parcela(): BelongsTo
    {
        return $this->belongsTo(FaturaParcela::class, 'fatura_parcela_id');
    }

    public function servico(): BelongsTo
    {
        return $this->belongsTo(ServicoNfse::class, 'servico_nfse_id');
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function emitidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitida_por');
    }
}
