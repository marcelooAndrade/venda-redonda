<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_entrada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pessoa_id')->nullable()->constrained()->nullOnDelete();

            $table->char('chave_acesso', 44)->unique();
            $table->string('numero', 12);
            $table->string('serie', 3);
            $table->timestamp('data_emissao');
            $table->string('natureza_operacao')->nullable();

            // entrada: comprada de fornecedor. propria: emitida por nós em
            // outro sistema, importada como histórico.
            $table->string('tipo', 10)->default('entrada');
            // pendente, conciliada, confirmada, ignorada
            $table->string('status', 12)->default('pendente');

            $table->decimal('valor_produtos', 15, 2)->default(0);
            $table->decimal('valor_frete', 15, 2)->default(0);
            $table->decimal('valor_ipi', 15, 2)->default(0);
            $table->decimal('valor_nota', 15, 2)->default(0);

            $table->string('protocolo', 20)->nullable();
            $table->string('xml_path')->nullable();
            $table->timestamp('confirmada_em')->nullable();
            $table->foreignId('confirmada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['emitente_id', 'status']);
            $table->index('data_emissao');
        });

        Schema::create('nota_entrada_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_entrada_id')->constrained('notas_entrada')->cascadeOnDelete();
            // Produto interno vinculado. Nulo enquanto não conciliado.
            $table->foreignId('produto_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('numero');
            $table->string('codigo_fornecedor', 60);
            $table->string('descricao');
            $table->string('gtin', 14)->nullable();
            $table->string('ncm', 8)->nullable();
            $table->char('cfop_origem', 4)->nullable();
            $table->char('cfop_entrada', 4)->nullable();
            $table->string('unidade', 6)->nullable();
            $table->decimal('quantidade', 15, 4);
            $table->decimal('valor_unitario', 15, 4);
            $table->decimal('valor_total', 15, 2);
            $table->decimal('custo_unitario', 15, 4);
            // Quantas unidades internas cabem numa unidade do fornecedor.
            $table->decimal('fator_conversao', 15, 6)->default(1);
            $table->timestamps();

            $table->unique(['nota_entrada_id', 'numero']);
        });

        // Vínculo entre o código do fornecedor e o produto interno. Salvo uma
        // vez, a próxima importação do mesmo fornecedor já vem conciliada.
        Schema::create('produto_fornecedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pessoa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained()->cascadeOnDelete();
            $table->string('codigo_fornecedor', 60);
            $table->string('descricao_fornecedor')->nullable();
            $table->decimal('fator_conversao', 15, 6)->default(1);
            $table->timestamps();

            $table->unique(['pessoa_id', 'codigo_fornecedor']);
            $table->index(['emitente_id', 'produto_id']);
        });

        // Conversão de CFOP de saída do fornecedor para o de entrada nosso.
        Schema::create('cfop_entrada_saida', function (Blueprint $table) {
            $table->char('cfop_saida', 4)->primary();
            $table->char('cfop_entrada', 4);
            $table->string('descricao')->nullable();
        });

        // Pares mais comuns. O contador ajusta e completa pela tela.
        $pares = [
            ['5101', '1101', 'Compra para industrialização'],
            ['6101', '2101', 'Compra para industrialização, outra UF'],
            ['5102', '1102', 'Compra para comercialização'],
            ['6102', '2102', 'Compra para comercialização, outra UF'],
            ['5910', '1910', 'Bonificação, doação ou brinde'],
            ['6910', '2910', 'Bonificação, doação ou brinde, outra UF'],
            ['5915', '1915', 'Remessa para conserto'],
            ['6915', '2915', 'Remessa para conserto, outra UF'],
            ['5916', '1916', 'Retorno de conserto'],
            ['6916', '2916', 'Retorno de conserto, outra UF'],
            ['5202', '1202', 'Devolução de venda'],
            ['6202', '2202', 'Devolução de venda, outra UF'],
        ];

        DB::table('cfop_entrada_saida')->insert(array_map(
            fn (array $p): array => ['cfop_saida' => $p[0], 'cfop_entrada' => $p[1], 'descricao' => $p[2]],
            $pares,
        ));
    }

    public function down(): void
    {
        foreach (['cfop_entrada_saida', 'produto_fornecedor', 'nota_entrada_itens', 'notas_entrada'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
