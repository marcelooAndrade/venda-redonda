<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inutilizacao extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $table = 'inutilizacoes';

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
}
