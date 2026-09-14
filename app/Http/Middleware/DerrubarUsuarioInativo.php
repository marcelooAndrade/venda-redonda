<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Conta inativa não continua navegando só porque a sessão já existia.
 *
 * O bloqueio no login em si (`FortifyServiceProvider::authenticateUsing`)
 * cobre quem tenta entrar de novo. Este middleware cobre quem já estava
 * dentro quando um administrador desligou a conta: a próxima requisição
 * autenticada a derruba, em vez de deixá-la ver o sistema até a sessão
 * expirar sozinha.
 */
class DerrubarUsuarioInativo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->ativo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
