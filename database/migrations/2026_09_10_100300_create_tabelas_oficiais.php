<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabelas oficiais da NF-e. Os nomes dos campos seguem a nomenclatura da
 * SEFAZ para conferência direta com o MOC. Regra 8 do projeto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ufs', function (Blueprint $table) {
            $table->char('sigla', 2)->primary();
            $table->string('nome');
            $table->char('codigo_ibge', 2)->unique();
            $table->decimal('aliquota_interna', 5, 2)->nullable();
        });

        Schema::create('municipios', function (Blueprint $table) {
            $table->char('codigo_ibge', 7)->primary();
            $table->string('nome');
            $table->char('uf', 2);
            $table->index('uf');
            $table->index('nome');
        });

        Schema::create('ncms', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->index();
            $table->text('descricao');
            $table->boolean('valido_nfe')->default(false)->index();
            $table->date('vigente_de')->nullable();
            $table->date('vigente_ate')->nullable();
            $table->unique(['codigo', 'vigente_de']);
        });

        Schema::create('cests', function (Blueprint $table) {
            $table->char('codigo', 7)->primary();
            $table->text('descricao');
            $table->string('ncm_vinculado', 10)->nullable();
        });

        Schema::create('cfops', function (Blueprint $table) {
            $table->char('codigo', 4)->primary();
            $table->text('descricao');
            $table->boolean('entrada');
            $table->boolean('movimenta_estoque')->default(true);
        });

        // CST e CSOSN em uma tabela só, separados por imposto.
        Schema::create('codigos_situacao_tributaria', function (Blueprint $table) {
            $table->id();
            $table->string('imposto', 12); // icms, csosn, ipi, pis, cofins
            $table->string('codigo', 4);
            $table->text('descricao');
            $table->unique(['imposto', 'codigo']);
        });

        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->string('sigla', 6)->primary();
            $table->string('descricao');
        });

        Schema::create('meios_pagamento', function (Blueprint $table) {
            $table->char('codigo', 2)->primary(); // tPag
            $table->string('descricao');
            $table->boolean('exige_detalhe')->default(false);
        });

        // cClassTrib da Reforma Tributária. Ver DF-001.
        Schema::create('classificacoes_tributarias', function (Blueprint $table) {
            $table->string('codigo', 6)->primary();
            $table->text('descricao');
            $table->string('tributo', 8); // ibs, cbs, is
            $table->string('cst', 3)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['classificacoes_tributarias', 'meios_pagamento', 'unidades_medida',
            'codigos_situacao_tributaria', 'cfops', 'cests', 'ncms', 'municipios', 'ufs'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
