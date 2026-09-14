<?php

use App\Http\Middleware\DefinirEmitenteDoContexto;
use App\Http\Middleware\RecusarCadastroEmDominioDeCliente;
use App\Http\Middleware\ResolverTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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

            // Deriva o tenant do emitente resolvido, quando o host ainda
            // não tiver fixado nenhum. Ver o doc comment da própria classe.
            DefinirEmitenteDoContexto::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
