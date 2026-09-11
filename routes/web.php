<?php

use App\Http\Controllers\LogoTenantController;
use App\Http\Controllers\RaizController;
use App\Livewire\Certificados\Gerenciar;
use App\Livewire\Contador\Exportacao;
use App\Livewire\Estoque\Painel;
use App\Livewire\Notas\Emissao;
use App\Livewire\Painel\Inicio;
use App\Livewire\Pessoas\Cadastro;
use App\Livewire\Tenancy\Marca;
use App\Livewire\Tributacao\Regras;
use Illuminate\Support\Facades\Route;

// A raiz muda de superfície conforme o host: apresentação no domínio nu
// do produto, aplicação em todo o resto.
Route::get('/', RaizController::class)->name('home');

// Pública de propósito: a tela de login precisa mostrar a logo de quem está
// entrando, e ela roda antes de haver usuário. Não é exposição nova, porque
// quem alcança o host já alcança a tela de login. O isolamento continua vindo
// do host resolvido, não de identificador na URL.
Route::get('logo', LogoTenantController::class)->name('logo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Inicio::class)->name('dashboard');

    Route::get('contabilidade', Exportacao::class)->name('contabilidade');
    Route::get('notas', Emissao::class)->name('notas');
    Route::get('importacao', App\Livewire\Importacao\Painel::class)->name('importacao');
    Route::get('estoque', Painel::class)->name('estoque');
    Route::get('marca', Marca::class)->name('marca');
    Route::get('produtos', App\Livewire\Produtos\Cadastro::class)->name('produtos');
    Route::get('regras-fiscais', Regras::class)->name('regras-fiscais');
    Route::get('destinatarios', Cadastro::class)->name('destinatarios');
    Route::get('certificados', Gerenciar::class)->name('certificados');
});

require __DIR__.'/settings.php';
