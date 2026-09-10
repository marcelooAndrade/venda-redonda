<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Núcleo da emissão. Nomes de campo seguem a nomenclatura da SEFAZ para
 * conferência direta com o MOC. Regra 8 do projeto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pessoa_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('natureza_operacao_id')->nullable()->constrained('naturezas_operacao')->nullOnDelete();
            $table->foreignId('transportadora_id')->nullable()->constrained('pessoas')->nullOnDelete();

            $table->unsignedSmallInteger('serie');
            // Nulo enquanto rascunho: o número só é atribuído na transmissão.
            $table->unsignedInteger('numero')->nullable();
            $table->char('chave_acesso', 44)->nullable()->unique();
            $table->string('status', 20)->default('rascunho');
            $table->string('ambiente', 12);

            $table->timestamp('data_emissao');
            $table->timestamp('data_saida')->nullable();
            $table->string('natureza_operacao')->nullable();
            // tpNF 0 entrada, 1 saída
            $table->char('tipo', 1)->default('1');
            // finNFe 1 normal, 2 complementar, 3 ajuste, 4 devolução
            $table->char('fin_nfe', 1)->default('1');
            // idDest 1 interna, 2 interestadual, 3 exterior
            $table->char('id_dest', 1)->default('1');
            $table->boolean('consumidor_final')->default(false);
            // indPres
            $table->char('ind_pres', 1)->default('9');
            // modFrete 0 a 4 e 9
            $table->char('mod_frete', 1)->default('9');

            // Totais, todos recalculados no servidor.
            $table->decimal('valor_produtos', 15, 2)->default(0);
            $table->decimal('valor_frete', 15, 2)->default(0);
            $table->decimal('valor_seguro', 15, 2)->default(0);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('valor_outros', 15, 2)->default(0);
            $table->decimal('base_icms', 15, 2)->default(0);
            $table->decimal('valor_icms', 15, 2)->default(0);
            $table->decimal('valor_icms_st', 15, 2)->default(0);
            $table->decimal('valor_fcp', 15, 2)->default(0);
            $table->decimal('valor_ipi', 15, 2)->default(0);
            $table->decimal('valor_pis', 15, 2)->default(0);
            $table->decimal('valor_cofins', 15, 2)->default(0);
            $table->decimal('valor_ibs', 15, 2)->default(0);
            $table->decimal('valor_cbs', 15, 2)->default(0);
            $table->decimal('valor_is', 15, 2)->default(0);
            $table->decimal('valor_nota', 15, 2)->default(0);

            $table->text('info_complementares')->nullable();
            $table->text('info_fisco')->nullable();

            // Retorno da SEFAZ.
            $table->string('protocolo', 20)->nullable();
            $table->string('c_stat', 4)->nullable();
            $table->text('x_motivo')->nullable();
            $table->timestamp('autorizada_em')->nullable();
            $table->string('recibo', 20)->nullable();
            // Contingência: 1 normal, 6 SVC-AN, 7 SVC-RS
            $table->char('tp_emis', 1)->default('1');
            $table->text('justificativa_contingencia')->nullable();
            $table->timestamp('contingencia_em')->nullable();

            $table->foreignId('criada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('transmitida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['emitente_id', 'serie', 'numero']);
            $table->index(['emitente_id', 'status', 'data_emissao']);
        });

        Schema::create('nota_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedSmallInteger('numero');
            $table->string('codigo', 60);
            $table->string('descricao');
            $table->string('gtin', 14)->nullable();
            $table->string('ncm', 8);
            $table->char('cest', 7)->nullable();
            $table->char('cfop', 4);
            $table->string('unidade', 6);
            $table->string('unidade_tributavel', 6);
            $table->char('origem', 1)->default('0');

            $table->decimal('quantidade', 15, 4);
            $table->decimal('quantidade_tributavel', 15, 4);
            $table->decimal('valor_unitario', 15, 10);
            $table->decimal('valor_produto', 15, 2);
            $table->decimal('valor_desconto', 15, 2)->default(0);
            $table->decimal('valor_frete', 15, 2)->default(0);
            $table->decimal('valor_seguro', 15, 2)->default(0);
            $table->decimal('valor_outros', 15, 2)->default(0);

            // Tributos calculados pelo TaxCalculator no servidor.
            $table->string('cst_icms', 3)->nullable();
            $table->string('csosn', 3)->nullable();
            $table->char('mod_bc', 1)->nullable();
            $table->decimal('base_icms', 15, 2)->default(0);
            $table->decimal('aliquota_icms', 7, 4)->default(0);
            $table->decimal('valor_icms', 15, 2)->default(0);
            $table->decimal('valor_fcp', 15, 2)->default(0);
            $table->decimal('base_icms_st', 15, 2)->default(0);
            $table->decimal('valor_icms_st', 15, 2)->default(0);
            $table->decimal('credito_sn', 15, 2)->default(0);
            $table->string('cst_ipi', 2)->nullable();
            $table->string('cod_enq_ipi', 3)->nullable();
            $table->decimal('valor_ipi', 15, 2)->default(0);
            $table->string('cst_pis', 2)->nullable();
            $table->decimal('valor_pis', 15, 2)->default(0);
            $table->string('cst_cofins', 2)->nullable();
            $table->decimal('valor_cofins', 15, 2)->default(0);
            // Reforma Tributária. Ver DF-001.
            $table->string('cst_ibscbs', 3)->nullable();
            $table->string('cclasstrib', 6)->nullable();
            $table->decimal('valor_ibs_uf', 15, 2)->default(0);
            $table->decimal('valor_ibs_mun', 15, 2)->default(0);
            $table->decimal('valor_cbs', 15, 2)->default(0);
            $table->decimal('valor_is', 15, 2)->default(0);

            $table->timestamps();
            $table->unique(['nota_id', 'numero']);
        });

        Schema::create('nota_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained()->cascadeOnDelete();
            $table->char('t_pag', 2);
            $table->decimal('valor', 15, 2);
            $table->char('ind_pag', 1)->nullable();
            $table->timestamps();
        });

        Schema::create('nota_duplicatas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained()->cascadeOnDelete();
            $table->string('numero', 60);
            $table->date('vencimento');
            $table->decimal('valor', 15, 2);
            $table->timestamps();
        });

        Schema::create('nota_volumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantidade')->nullable();
            $table->string('especie', 60)->nullable();
            $table->string('marca', 60)->nullable();
            $table->string('numeracao', 60)->nullable();
            $table->decimal('peso_liquido', 15, 3)->nullable();
            $table->decimal('peso_bruto', 15, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('nota_referencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained()->cascadeOnDelete();
            // Desde 01/09/2026 o referenciamento vai no grupo DFeReferenciado.
            // Ver DF-002.
            $table->char('chave_acesso', 44);
            $table->string('motivo')->nullable();
            $table->timestamps();

            $table->unique(['nota_id', 'chave_acesso']);
        });

        Schema::create('nota_arquivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_id')->constrained()->cascadeOnDelete();
            // gerado, assinado, protocolado, evento, danfe
            $table->string('tipo', 16);
            $table->string('path');
            $table->string('sha256', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['nota_id', 'tipo']);
        });

        Schema::create('sefaz_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nota_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operacao', 40);
            $table->string('ambiente', 12);
            $table->string('c_stat', 4)->nullable();
            $table->text('x_motivo')->nullable();
            $table->unsignedInteger('duracao_ms')->nullable();
            $table->text('erro')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['emitente_id', 'operacao', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['sefaz_logs', 'nota_arquivos', 'nota_referencias', 'nota_volumes',
            'nota_duplicatas', 'nota_pagamentos', 'nota_itens', 'notas'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
