<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro imutável de ação sobre dado auditável.
 * Nunca guarda valor de campo oculto: ver Auditavel::campoSensivelAuditoria().
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'evento',
        'auditavel_type',
        'auditavel_id',
        'alteracoes',
        'ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'alteracoes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditavel(): MorphTo
    {
        return $this->morphTo();
    }
}
