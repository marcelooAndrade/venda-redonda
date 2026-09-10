<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Vitrine do design system. Só administrador, é ferramenta interna.
    Route::view('design-system', 'design-system')
        ->middleware('can:ver-design-system')
        ->name('design-system');
});

require __DIR__.'/settings.php';
