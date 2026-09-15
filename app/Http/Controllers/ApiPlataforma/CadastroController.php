<?php

namespace App\Http\Controllers\ApiPlataforma;

use App\Http\Controllers\Controller;
use App\Models\ApiCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cadastro do Nodo. Sem senha, sem sessão: o token aparece uma vez
 * só nesta resposta, e daí em diante o cliente só fala com a API por HTTP,
 * com o próprio token.
 *
 * As rotas deste módulo vivem fora do grupo `web` de propósito (sem sessão,
 * sem CSRF, sem os middlewares de tenant do sistema fiscal), então o erro
 * de validação não pode contar com `$errors`/`old()`, que dependem de
 * sessão: os dois vão explícitos para a view.
 */
class CadastroController extends Controller
{
    public function show(): View
    {
        return view('api-plataforma.cadastro');
    }

    public function store(Request $request): View
    {
        $validador = Validator::make($request->all(), [
            'nome' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique(ApiCliente::class)],
        ]);

        if ($validador->fails()) {
            return view('api-plataforma.cadastro', [
                'erros' => $validador->errors()->all(),
                'antigo' => $request->only(['nome', 'email']),
            ]);
        }

        $dados = $validador->validated();
        $cliente = ApiCliente::create($dados);

        $token = $cliente->createToken($dados['nome'], ['whatsapp'])->plainTextToken;

        return view('api-plataforma.cadastro-sucesso', ['token' => $token]);
    }
}
