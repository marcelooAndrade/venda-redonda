<?php

use App\Http\Controllers\ApiPlataforma\CadastroController;
use App\Http\Controllers\ApiPlataforma\WhatsappInstanciaController;
use App\Http\Controllers\ApiPlataforma\WhatsappMensagemController;
use Illuminate\Support\Facades\Route;

/**
 * Domínio próprio do Nodo, separado do sistema fiscal: sem sessão,
 * sem os middlewares de tenant, sem nenhuma relação com routes/web.php.
 *
 * Sem API_DOMINIO configurada, este grupo não casa com host nenhum, mesma
 * regra de "sem configuração, recurso desligado" das outras integrações.
 */
if (filled(config('api_plataforma.dominio'))) {
    Route::domain(config('api_plataforma.dominio'))->group(function () {
        Route::get('cadastro', [CadastroController::class, 'show'])->name('api-plataforma.cadastro');
        Route::post('cadastro', [CadastroController::class, 'store'])->name('api-plataforma.cadastro.store');

        Route::middleware(['auth:sanctum', 'ability:whatsapp', 'throttle:whatsapp'])
            ->prefix('whatsapp/v1')
            ->group(function () {
                Route::post('instancia', [WhatsappInstanciaController::class, 'store']);
                Route::get('instancia', [WhatsappInstanciaController::class, 'show']);
                Route::post('instancia/conectar', [WhatsappInstanciaController::class, 'conectar']);
                Route::post('mensagens', [WhatsappMensagemController::class, 'store']);
            });
    });
}
