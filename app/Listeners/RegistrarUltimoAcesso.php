<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Marca a entrada da pessoa.
 *
 * Listener e não middleware: interessa quando ela entrou, não quantas
 * requisições fez. Middleware escreveria no banco a cada clique.
 *
 * `saveQuietly` porque entrar no sistema não é alteração de cadastro e não
 * deve encher a auditoria.
 */
class RegistrarUltimoAcesso
{
    public function handle(Login $evento): void
    {
        if (! $evento->user instanceof User) {
            return;
        }

        $evento->user->forceFill(['ultimo_acesso_em' => now()])->saveQuietly();
    }
}
