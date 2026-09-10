<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaEvento extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'nota_eventos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'homologado_em' => 'datetime',
            'homologada_em' => 'datetime',
        ];
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class);
    }

    public function rotulo(): string
    {
        return match ($this->tipo) {
            '110111' => 'Cancelamento',
            '110110' => 'Carta de correção',
            default => 'Evento '.$this->tipo,
        };
    }
}
