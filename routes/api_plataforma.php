<?php

use App\Http\Controllers\ApiPlataforma\WhatsappGrupoController;
use App\Http\Controllers\ApiPlataforma\WhatsappInstanciaController;
use App\Http\Controllers\ApiPlataforma\WhatsappMensagemController;
use App\Http\Controllers\ApiPlataforma\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Domínio próprio do Nodo, separado do sistema fiscal: sem sessão,
 * sem os middlewares de tenant, sem nenhuma relação com routes/web.php.
 *
 * Sem cadastro público: só o admin cria cliente e escolhe os módulos, em
 * /admin, dentro do EmitirAgora. Ver App\Livewire\Produto\NodoClientes.
 *
 * Sem API_DOMINIO configurada, este grupo não casa com host nenhum, mesma
 * regra de "sem configuração, recurso desligado" das outras integrações.
 */
if (filled(config('api_plataforma.dominio'))) {
    Route::domain(config('api_plataforma.dominio'))->group(function () {
        Route::middleware(['auth:sanctum', 'modulo:whatsapp', 'throttle:whatsapp'])
            ->prefix('whatsapp/v1')
            ->group(function () {
                Route::post('instancia', [WhatsappInstanciaController::class, 'store']);
                Route::get('instancia', [WhatsappInstanciaController::class, 'show']);
                Route::post('instancia/conectar', [WhatsappInstanciaController::class, 'conectar']);
                Route::post('mensagens', [WhatsappMensagemController::class, 'store']);
                Route::get('grupos', [WhatsappGrupoController::class, 'index']);
                Route::put('webhook', [WhatsappWebhookController::class, 'atualizarUrl']);
            });

        // Fora do grupo autenticado de propósito: quem chama é a uazapi, não
        // o cliente. O segredo na URL identifica a instância.
        Route::post('whatsapp/v1/uazapi-webhook/{secret}', [WhatsappWebhookController::class, 'receber'])
            ->name('nodo.uazapi-webhook');
    });
}
