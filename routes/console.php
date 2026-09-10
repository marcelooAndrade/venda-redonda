<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Certificado vencido trava a emissão por completo, então o aviso roda todo dia.
Schedule::command('fiscal:alertar-certificados')
    ->dailyAt('07:00')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping();
