<?php

namespace App\Models;

use App\Enums\ModuloApi;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

/**
 * Cliente da plataforma de APIs (Nodo). Sem relação nenhuma com
 * `Tenant`/`User` do sistema fiscal: autentica só por token (Sanctum), sem
 * senha, sem sessão. Implementa `Authenticatable` só porque o guard do
 * Sanctum exige o contrato — não tem `password` nem tela de login nenhuma.
 *
 * Cadastro não é público: só o admin cria cliente, em /admin, e escolhe os
 * módulos na hora. `modulos` decide o que o cliente pode usar — o token em
 * si não carrega habilidade nenhuma, então trocar o módulo de um cliente
 * não obriga a reemitir o token dele.
 *
 * @property array<int, string> $modulos
 */
class ApiCliente extends Model implements AuthenticatableContract
{
    use Authenticatable, HasApiTokens;

    protected $attributes = [
        'ativo' => true,
        'modulos' => '[]',
    ];

    protected $fillable = ['nome', 'email', 'ativo', 'modulos'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean', 'modulos' => 'array'];
    }

    public function temModulo(ModuloApi $modulo): bool
    {
        return in_array($modulo->value, $this->modulos ?? [], true);
    }

    /** @return HasOne<WhatsappInstancia, $this> */
    public function whatsappInstancia(): HasOne
    {
        return $this->hasOne(WhatsappInstancia::class);
    }
}
