<?php

use App\Http\Middleware\DefinirEmitenteDoContexto;
use App\Http\Middleware\DerrubarUsuarioInativo;
use App\Http\Middleware\ExigeModuloApi;
use App\Http\Middleware\RecusarCadastroEmDominioDeCliente;
use App\Http\Middleware\ResolverTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Domínio próprio, sem prefixo `/api`: o host já diz que é API. Vem
        // pela mesma via do `api:` do framework (grupo de middleware `api`,
        // sem sessão nem os middlewares de tenant do `web`), só sem o
        // prefixo padrão.
        api: __DIR__.'/../routes/api_plataforma.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Permissões são escopadas por emitente. Sem definir o time a cada
        // requisição, o usuário chega sem papel algum.
        $middleware->web(prepend: [
            // Precisa vir antes de tudo: o escopo global de dados depende dele.
            ResolverTenant::class,
        ]);

        $middleware->web(append: [
            // Antes do que resolve emitente: aqui o que vale é o tenant que
            // o host resolveu, e é ele que diz se a rota existe.
            RecusarCadastroEmDominioDeCliente::class,

            DerrubarUsuarioInativo::class,

            // Deriva o tenant do emitente resolvido, quando o host ainda
            // não tiver fixado nenhum. Ver o doc comment da própria classe.
            DefinirEmitenteDoContexto::class,
        ]);

        // Usado pelas rotas do Nodo, em routes/api_plataforma.php, para
        // exigir o módulo ligado no cliente autenticado.
        $middleware->alias([
            'modulo' => ExigeModuloApi::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // `is('api/*')` não pega o Nodo: as rotas dele não têm prefixo
            // `/api`, de propósito (o domínio já diz que é API). Sem esta
            // checagem por host, cliente que não manda `Accept:
            // application/json` recebia redirect para o /login do sistema
            // fiscal, em vez de 401 em JSON — medido com um cliente real.
            fn (Request $request) => $request->is('api/*')
                || $request->expectsJson()
                || $request->getHost() === (string) config('api_plataforma.dominio'),
        );
    })->create();
