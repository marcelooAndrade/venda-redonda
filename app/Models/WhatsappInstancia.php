<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A instância uazapi de um cliente da API. `uazapi_token` é cifrado em
 * repouso: é a credencial que fala com o WhatsApp real de alguém.
 */
class WhatsappInstancia extends Model
{
    protected $fillable = ['api_cliente_id', 'uazapi_instance_id', 'uazapi_token', 'nome', 'status'];

    protected function casts(): array
    {
        return ['uazapi_token' => 'encrypted'];
    }

    /** @return BelongsTo<ApiCliente, $this> */
    public function apiCliente(): BelongsTo
    {
        return $this->belongsTo(ApiCliente::class);
    }
}
