<?php

namespace App\Http\Controllers;

use App\Models\Emitente;
use App\Support\EmitenteAtual;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Troca a empresa/emitente em foco.
 *
 * Busca sem o escopo de tenant de propósito: o alvo pode estar numa empresa
 * diferente da que está em foco agora, e é justamente essa travessia que a
 * troca serve. Quem confirma que o login pode mesmo ir para lá é
 * `EmitenteAtual::escolher()`, via `User::podeAcessar()`.
 */
class EscolherEmitenteController extends Controller
{
    public function __invoke(Request $request, EmitenteAtual $emitenteAtual): RedirectResponse
    {
        $dados = $request->validate(['emitente_id' => ['required', 'integer']]);

        $emitente = Emitente::withoutGlobalScope('tenant')->findOrFail($dados['emitente_id']);

        try {
            $emitenteAtual->escolher($emitente);
        } catch (AuthorizationException) {
            abort(403);
        }

        return redirect()->route('dashboard');
    }
}
