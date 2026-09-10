<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PessoaEmail extends Model
{
    protected $fillable = ['pessoa_id', 'email'];

    public function pessoa(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class);
    }
}
