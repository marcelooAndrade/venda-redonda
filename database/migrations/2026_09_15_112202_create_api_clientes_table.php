<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cliente da plataforma de APIs (Nodo), sem nenhuma relação com
 * `tenants`/`users` do sistema fiscal: são dois produtos diferentes, e o
 * EmitirAgora é só mais um cliente daqui, sem tratamento especial.
 *
 * Sem senha: o acesso é só pelo token (Sanctum), como qualquer API. Ver
 * docs/superpowers/specs/2026-09-15-painel-api-whatsapp-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clientes');
    }
};
