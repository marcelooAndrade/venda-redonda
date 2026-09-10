<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaVolume extends Model
{
    protected $table = 'nota_volumes';

    protected $guarded = ['id'];

    public function nota(): BelongsTo
    {
        return $this->belongsTo(Nota::class);
    }
}
