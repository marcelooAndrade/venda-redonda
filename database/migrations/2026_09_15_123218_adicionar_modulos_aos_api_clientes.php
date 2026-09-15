<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quais módulos do Nodo o cliente tem ativo (ver ModuloApi). O cadastro
 * deixou de ser público: só o admin cria cliente, e escolhe os módulos na
 * hora.
 *
 * Sem valor padrão na coluna: MySQL recusa DEFAULT em BLOB/TEXT/JSON
 * ("Syntax error or access violation: 1101"), embora o SQLite aceite —
 * medido em produção, o teste local não pegou. `[]` vem do model
 * (`$attributes`), não do banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clientes', function (Blueprint $table) {
            $table->json('modulos')->nullable()->after('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('api_clientes', function (Blueprint $table) {
            $table->dropColumn('modulos');
        });
    }
};
