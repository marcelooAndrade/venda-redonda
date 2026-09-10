<?php

namespace App\Models;

use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SefazLog extends Model
{
    use DoTenantViaEmitente;

    public const UPDATED_AT = null;

    protected $table = 'sefaz_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class);
    }
}
