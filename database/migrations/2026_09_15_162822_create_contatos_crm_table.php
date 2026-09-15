<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funil de negócio da área administrativa: quem pode virar cliente de
 * desenvolvimento sob medida. Sem tenant nenhum — é dado do dono do
 * produto, não de uma empresa que usa o sistema fiscal.
 *
 * `etapa` é `string`, não `json`/`text`: pode ter DEFAULT em MySQL sem
 * repetir o erro 1101 do incidente anterior (coluna `modulos`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contatos_crm', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('empresa')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->string('etapa')->default('base');
            $table->text('observacao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contatos_crm');
    }
};
