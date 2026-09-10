<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();

            // 110111 cancelamento, 110110 carta de correção
            $table->string('tipo', 6);
            $table->unsignedSmallInteger('sequencia')->default(1);
            $table->text('justificativa')->nullable();
            $table->text('correcao')->nullable();

            $table->string('protocolo', 20)->nullable();
            $table->string('c_stat', 4)->nullable();
            $table->text('x_motivo')->nullable();
            $table->string('xml_path')->nullable();
            $table->timestamp('homologado_em')->nullable();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['nota_id', 'tipo']);
            $table->unique(['nota_id', 'tipo', 'sequencia']);
        });

        Schema::create('inutilizacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('ano');
            $table->unsignedSmallInteger('serie');
            $table->unsignedInteger('numero_inicial');
            $table->unsignedInteger('numero_final');
            $table->text('justificativa');
            $table->string('protocolo', 20)->nullable();
            $table->string('c_stat', 4)->nullable();
            $table->text('x_motivo')->nullable();
            $table->string('xml_path')->nullable();
            $table->timestamp('homologada_em')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['emitente_id', 'serie', 'ano']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inutilizacoes');
        Schema::dropIfExists('nota_eventos');
    }
};
