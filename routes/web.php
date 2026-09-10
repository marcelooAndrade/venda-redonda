<?php

use App\Livewire\Certificados\Gerenciar;
use App\Livewire\Pessoas\Cadastro;
use App\Livewire\Tributacao\Regras;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('regras-fiscais', Regras::class)->name('regras-fiscais');
    Route::get('destinatarios', Cadastro::class)->name('destinatarios');
    Route::get('certificados', Gerenciar::class)->name('certificados');

    // Vitrine do design system. Só administrador, é ferramenta interna.
    Route::view('design-system', 'design-system')
        ->middleware('can:ver-design-system')
        ->name('design-system');
});

require __DIR__.'/settings.php';
