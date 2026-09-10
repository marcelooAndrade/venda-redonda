<?php

namespace App\Models;

use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmitenteSerie extends Model
{
    use DoTenantViaEmitente;

    protected $table = 'emitente_series';

    protected $guarded = ['id'];

    protected $attributes = [
        'proximo_numero' => 1,
    ];

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }
}
