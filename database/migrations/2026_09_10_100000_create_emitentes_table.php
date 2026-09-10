<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emitentes', function (Blueprint $table) {
            $table->id();
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('cnpj', 14)->unique();
            $table->string('inscricao_estadual', 20);
            $table->string('inscricao_municipal', 20)->nullable();
            // CRT: 1 Simples, 2 Simples com excesso de sublimite, 3 Regime Normal, 4 MEI
            $table->string('crt', 1);
            $table->string('ambiente', 12)->default('homologacao');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emitentes');
    }
};
