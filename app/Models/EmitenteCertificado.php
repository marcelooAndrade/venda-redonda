<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um certificado A1 no histórico do emitente. Só um fica ativo por vez.
 *
 * `senha` e `arquivo_path` estão em $hidden, então além de não aparecerem em
 * serialização, a auditoria os omite automaticamente: ver Auditavel.
 */
class EmitenteCertificado extends Model
{
    use Auditavel;

    protected $fillable = [
        'emitente_id', 'arquivo_path', 'senha', 'titular', 'cnpj', 'serial',
        'fingerprint', 'valido_de', 'valido_ate', 'ativo', 'convertido_de_legado',
        'enviado_por', 'alertas_enviados',
    ];

    protected $hidden = ['arquivo_path', 'senha'];

    protected function casts(): array
    {
        return [
            // Diferente do SafeEncrypted do app-transm: aqui a falha de
            // decriptação precisa estourar, não virar null em silêncio.
            'senha' => 'encrypted',
            'valido_de' => 'datetime',
            'valido_ate' => 'datetime',
            'ativo' => 'boolean',
            'convertido_de_legado' => 'boolean',
            'alertas_enviados' => 'array',
        ];
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function vencido(): bool
    {
        return $this->valido_ate->isPast();
    }

    public function diasParaVencer(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->valido_ate->startOfDay(), false);
    }
}
