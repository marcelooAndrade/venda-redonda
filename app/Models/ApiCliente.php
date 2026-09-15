<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cliente da plataforma de APIs (Painel API). Sem relação nenhuma com
 * `Tenant`/`User` do sistema fiscal: autentica só por token (Sanctum), sem
 * senha, sem sessão. Implementa `Authenticatable` só porque o guard do
 * Sanctum exige o contrato — não tem `password` nem tela de login nenhuma.
 */
class ApiCliente extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens;

    protected $attributes = [
        'ativo' => true,
    ];

    protected $fillable = ['nome', 'email', 'ativo'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    /** @return HasOne<WhatsappInstancia, $this> */
    public function whatsappInstancia(): HasOne
    {
        return $this->hasOne(WhatsappInstancia::class);
    }
}
