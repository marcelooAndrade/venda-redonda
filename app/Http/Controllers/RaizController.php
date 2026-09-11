<?php

namespace App\Http\Controllers;

use App\Support\HostDoProduto;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decide a superfície da raiz pelo host.
 *
 * A apresentação é a exceção: só o domínio nu do produto e o `www` a veem.
 * Todo o resto é aplicação, porque acertar uma exceção é mais fácil do que
 * manter uma lista delas.
 */
class RaizController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (HostDoProduto::eDominioDoProduto($request->getHost())) {
            return response()->view('apresentacao');
        }

        return auth()->check()
            ? redirect()->route('dashboard')
            : redirect()->route('login');
    }
}
