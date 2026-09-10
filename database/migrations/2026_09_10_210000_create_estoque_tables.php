<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estoque como razão imutável.
 *
 * `estoque_movimentos` é o registro fiel: nada é editado nem apagado.
 * `estoque_saldos` é a materialização do saldo e do custo médio, atualizada
 * dentro da mesma transação, com lock, para não divergir sob concorrência.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoque_movimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 16);
            // Sempre positiva. O sinal vem do tipo.
            $table->decimal('quantidade', 15, 4);
            // Custo unitário vigente no momento do movimento. Guardado aqui
            // porque a média muda depois e o custo da saída se perderia.
            $table->decimal('custo_unitario', 15, 4)->nullable();
            // Saldo depois do movimento, para o Kardex não precisar somar tudo.
            $table->decimal('saldo_apos', 15, 4);
            $table->decimal('custo_medio_apos', 15, 4)->nullable();
            $table->string('documento')->nullable();
            $table->text('justificativa')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['produto_id', 'id']);
            $table->index(['emitente_id', 'tipo']);
            $table->index('documento');
        });

        Schema::create('estoque_saldos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantidade', 15, 4)->default(0);
            $table->decimal('custo_medio', 15, 4)->default(0);
            $table->timestamps();

            $table->unique('produto_id');
            $table->index(['emitente_id', 'quantidade']);
        });

        Schema::table('emitentes', function (Blueprint $table) {
            $table->boolean('permite_saldo_negativo')->default(false)->after('serie_padrao');
        });
    }

    public function down(): void
    {
        Schema::table('emitentes', fn (Blueprint $t) => $t->dropColumn('permite_saldo_negativo'));
        Schema::dropIfExists('estoque_saldos');
        Schema::dropIfExists('estoque_movimentos');
    }
};
