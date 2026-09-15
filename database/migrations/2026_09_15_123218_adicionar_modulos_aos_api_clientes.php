<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quais módulos do Nodo o cliente tem ativo (ver ModuloApi). O cadastro
 * deixou de ser público: só o admin cria cliente, e escolhe os módulos na
 * hora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clientes', function (Blueprint $table) {
            $table->json('modulos')->default('[]')->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('api_clientes', function (Blueprint $table) {
            $table->dropColumn('modulos');
        });
    }
};
