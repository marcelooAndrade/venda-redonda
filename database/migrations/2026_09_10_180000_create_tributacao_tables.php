<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tributação parametrizável. Nenhuma alíquota, CST ou CFOP vive no código:
 * quem escreve a regra é o contador, pela tela, e o sistema apenas aplica.
 *
 * Nomes de campo seguem a nomenclatura da SEFAZ para conferência direta com
 * o MOC. Regra 8 do projeto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['emitente_id', 'nome']);
        });

        Schema::create('perfil_fiscal_regras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_fiscal_id')->constrained('perfis_fiscais')->cascadeOnDelete();

            // interna, interestadual, exterior
            $table->string('ambito', 14);
            // CRT ao qual a regra se aplica. Nulo vale para qualquer regime.
            $table->char('crt', 1)->nullable();

            // Vigência: regra muda com o tempo, e nota antiga precisa continuar
            // refletindo a regra que valia na data de emissão.
            $table->date('vigente_de');
            $table->date('vigente_ate')->nullable();

            // ICMS, regime normal
            $table->string('cst_icms', 2)->nullable();
            // CSOSN, Simples Nacional
            $table->string('csosn', 3)->nullable();
            $table->string('mod_bc', 1)->nullable();
            $table->decimal('aliquota_icms', 7, 4)->nullable();
            $table->decimal('reducao_bc', 7, 4)->nullable();
            $table->decimal('aliquota_credito_sn', 7, 4)->nullable();

            // ICMS ST
            $table->string('mod_bc_st', 1)->nullable();
            $table->decimal('mva_st', 7, 4)->nullable();
            $table->decimal('reducao_bc_st', 7, 4)->nullable();
            $table->decimal('aliquota_st', 7, 4)->nullable();

            // FCP e diferimento
            $table->decimal('aliquota_fcp', 7, 4)->nullable();
            $table->decimal('percentual_diferimento', 7, 4)->nullable();

            // IPI
            $table->string('cst_ipi', 2)->nullable();
            $table->string('codigo_enquadramento_ipi', 3)->nullable();
            $table->decimal('aliquota_ipi', 7, 4)->nullable();

            // PIS e COFINS
            $table->string('cst_pis', 2)->nullable();
            $table->decimal('aliquota_pis', 7, 4)->nullable();
            $table->string('cst_cofins', 2)->nullable();
            $table->decimal('aliquota_cofins', 7, 4)->nullable();

            // Reforma Tributária. Obrigatório para CRT 3 desde 03/08/2026,
            // conforme NT 2025.002-RTC v1.40. Ver DF-001.
            $table->string('cst_ibscbs', 3)->nullable();
            $table->string('cclasstrib', 6)->nullable();
            $table->decimal('aliquota_ibs_uf', 7, 4)->nullable();
            $table->decimal('aliquota_ibs_mun', 7, 4)->nullable();
            $table->decimal('aliquota_cbs', 7, 4)->nullable();
            $table->string('cst_is', 3)->nullable();
            $table->decimal('aliquota_is', 7, 4)->nullable();

            $table->text('observacao_contador')->nullable();
            $table->timestamps();

            $table->index(['perfil_fiscal_id', 'ambito', 'vigente_de']);
        });

        Schema::create('naturezas_operacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('perfil_fiscal_id')->nullable()->constrained('perfis_fiscais')->nullOnDelete();

            $table->string('descricao');
            $table->char('cfop_interno', 4)->nullable();
            $table->char('cfop_interestadual', 4)->nullable();
            // finNFe: 1 normal, 2 complementar, 3 ajuste, 4 devolução
            $table->char('fin_nfe', 1)->default('1');
            // tpNF: 0 entrada, 1 saída
            $table->char('tipo', 1)->default('1');
            $table->boolean('movimenta_estoque')->default(true);
            $table->boolean('gera_financeiro')->default(true);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['emitente_id', 'descricao']);
        });

        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('perfil_fiscal_id')->nullable()->constrained('perfis_fiscais')->nullOnDelete();

            $table->string('codigo', 60);
            $table->string('descricao');
            $table->string('gtin', 14)->nullable();
            $table->string('gtin_tributavel', 14)->nullable();
            $table->string('ncm', 8);
            $table->char('cest', 7)->nullable();
            $table->string('ex_tipi', 3)->nullable();

            $table->string('unidade_comercial', 6);
            $table->string('unidade_tributavel', 6);
            $table->decimal('fator_conversao', 15, 6)->default(1);

            // orig do ICMS: 0 a 8
            $table->char('origem', 1)->default('0');

            $table->decimal('preco_venda', 15, 4)->default(0);
            $table->decimal('custo', 15, 4)->default(0);
            $table->decimal('peso_liquido', 15, 3)->nullable();
            $table->decimal('peso_bruto', 15, 3)->nullable();
            $table->decimal('estoque_minimo', 15, 4)->default(0);
            $table->boolean('controla_estoque')->default(true);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['emitente_id', 'codigo']);
            $table->index(['emitente_id', 'ativo']);
            $table->index('gtin');
            $table->index('ncm');
        });
    }

    public function down(): void
    {
        foreach (['produtos', 'naturezas_operacao', 'perfil_fiscal_regras', 'perfis_fiscais'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
