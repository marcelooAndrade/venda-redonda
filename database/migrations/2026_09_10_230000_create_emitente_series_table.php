<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controle de numeração por emitente e série.
 *
 * A NF-e exige sequência sem buraco. Número pulado obriga inutilização
 * formal na SEFAZ; número repetido é rejeição na hora. Por isso a
 * atribuição acontece com lock, e só no momento da transmissão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emitente_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('serie');
            $table->unsignedInteger('proximo_numero')->default(1);
            $table->timestamps();

            $table->unique(['emitente_id', 'serie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emitente_series');
    }
};
