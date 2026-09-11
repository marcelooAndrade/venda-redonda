<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Núcleo financeiro, portado do projeto Marcelo Andrade.
 *
 * Três decisões da origem que foram mantidas porque estão certas:
 *
 * - **Dinheiro em centavos, inteiro.** Nunca `decimal`, nunca float. O
 *   `app-transm` usa `decimal(10,2)` e a origem usa `bigint` de centavos: a
 *   origem venceu.
 * - **Contas a receber é fatura com parcelas**, e o título é a parcela. Uma
 *   venda em 3 vezes são três títulos com vencimentos próprios, e não um título
 *   com data única.
 * - **O caixa é razão, e aponta para a origem.** Cada movimento diz de qual
 *   título veio, o que torna a conciliação possível em vez de opinativa.
 *
 * O que mudou em relação à origem: tudo é escopado por `emitente_id`, como todo
 * o resto deste sistema, e os nomes estão em português, pela convenção do
 * projeto.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Plano de contas: hierárquico, código de até três níveis.
        Schema::create('centros_custo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pai_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $table->string('codigo', 11);
            $table->string('nome', 120);
            $table->string('natureza', 10); // receita | despesa
            $table->boolean('grupo')->default(false);
            // Classificação individual vence a do centro. Nulo aqui significa
            // "herda do pai", e é o que sustenta o custo essencial mensal.
            $table->boolean('essencial')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['emitente_id', 'codigo']);
            $table->index(['emitente_id', 'natureza', 'ativo']);
        });

        Schema::create('contas_financeiras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 100);
            $table->string('banco', 100)->nullable();
            $table->string('tipo', 12)->default('corrente'); // corrente | poupanca | caixa
            $table->bigInteger('saldo_inicial_centavos')->default(0);
            $table->boolean('padrao')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['emitente_id', 'ativo']);
        });

        Schema::create('contas_pagar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $table->foreignId('conta_financeira_id')->nullable()->constrained('contas_financeiras')->nullOnDelete();
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->string('descricao', 160);
            $table->string('fornecedor', 160)->nullable();
            $table->bigInteger('valor_centavos');
            $table->date('vencimento');
            $table->string('status', 12)->default('pendente'); // pendente | pago | cancelado
            $table->timestamp('pago_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['emitente_id', 'status', 'vencimento']);
        });

        Schema::create('faturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pessoa_id')->nullable()->constrained('pessoas')->nullOnDelete();
            $table->foreignId('centro_custo_id')->nullable()->constrained('centros_custo')->nullOnDelete();
            $table->string('titulo', 160);
            $table->string('status', 12)->default('ativa'); // ativa | cancelada
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['emitente_id', 'status']);
        });

        Schema::create('fatura_parcelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fatura_id')->constrained('faturas')->cascadeOnDelete();
            $table->foreignId('conta_financeira_id')->nullable()->constrained('contas_financeiras')->nullOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('descricao', 160);
            $table->bigInteger('valor_centavos');
            $table->date('vencimento');
            $table->string('status', 12)->default('pendente'); // pendente | pago | cancelado
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();

            $table->unique(['fatura_id', 'numero']);
            $table->index(['status', 'vencimento']);
        });

        // Razão de caixa. Imutável, como o movimento de estoque: correção é
        // lançamento contrário, nunca edição.
        Schema::create('movimentos_caixa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conta_financeira_id')->constrained('contas_financeiras')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sentido', 8); // credito | debito
            $table->bigInteger('valor_centavos');
            $table->string('descricao', 200);
            $table->date('ocorrido_em');
            $table->string('origem_tipo', 16); // conta_pagar | fatura_parcela | ajuste
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['emitente_id', 'ocorrido_em']);
            $table->index(['origem_tipo', 'origem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos_caixa');
        Schema::dropIfExists('fatura_parcelas');
        Schema::dropIfExists('faturas');
        Schema::dropIfExists('contas_pagar');
        Schema::dropIfExists('contas_financeiras');
        Schema::dropIfExists('centros_custo');
    }
};
