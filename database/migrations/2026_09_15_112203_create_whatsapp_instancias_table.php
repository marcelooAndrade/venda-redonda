<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma instância uazapi por cliente, no máximo (`unique` em api_cliente_id).
 * `uazapi_token` é o token que a uazapi devolve ao criar a instância, não o
 * AdminToken da conta inteira — esse fica só em `.env`. Guardado cifrado:
 * é uma credencial de acesso ao WhatsApp real de alguém.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_instancias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_cliente_id')->unique()->constrained('api_clientes')->cascadeOnDelete();
            $table->string('uazapi_instance_id');
            $table->text('uazapi_token');
            $table->string('nome');
            $table->string('status')->default('disconnected');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instancias');
    }
};
