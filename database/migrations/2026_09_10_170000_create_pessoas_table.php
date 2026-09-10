<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cliente, fornecedor e transportadora vivem na mesma tabela, separados por
 * papel. Uma metalúrgica pode comprar peça microfundida e vender liga para o
 * mesmo emitente: com tabelas separadas, esse CNPJ viraria dois cadastros que
 * divergem no primeiro endereço atualizado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pessoas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();

            $table->char('tipo_pessoa', 1);
            // String sempre: CNPJ pode ter letra, e um documento iniciado por
            // zero perderia o zero se virasse número.
            $table->string('documento', 14);
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();

            $table->char('ind_ie_dest', 1);
            $table->string('inscricao_estadual', 20)->nullable();
            $table->string('inscricao_municipal', 20)->nullable();
            $table->string('suframa', 9)->nullable();
            $table->boolean('consumidor_final')->default(true);

            $table->string('logradouro');
            $table->string('numero', 60);
            $table->string('complemento')->nullable();
            $table->string('bairro');
            $table->char('codigo_municipio', 7);
            $table->string('municipio');
            $table->char('uf', 2);
            $table->char('cep', 8);

            $table->string('telefone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('observacoes')->nullable();

            // Papéis. Não são exclusivos.
            $table->boolean('e_cliente')->default(false);
            $table->boolean('e_fornecedor')->default(false);
            $table->boolean('e_transportadora')->default(false);

            // Só para transportadora (grupo transporta e veicTransp).
            $table->string('placa', 7)->nullable();
            $table->char('placa_uf', 2)->nullable();
            $table->string('rntc', 20)->nullable();

            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['emitente_id', 'documento']);
            $table->index(['emitente_id', 'e_cliente']);
            $table->index(['emitente_id', 'e_fornecedor']);
            $table->index(['emitente_id', 'e_transportadora']);
            $table->index('razao_social');
        });

        // E-mails adicionais para envio de XML e DANFE.
        Schema::create('pessoa_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pessoa_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamps();

            $table->unique(['pessoa_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pessoa_emails');
        Schema::dropIfExists('pessoas');
    }
};
