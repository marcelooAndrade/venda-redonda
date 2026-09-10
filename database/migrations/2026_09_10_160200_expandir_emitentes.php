<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completa o emitente com o que a NF-e exige no grupo emit, mais a
 * configuração fiscal. Nomes seguem a nomenclatura da SEFAZ. Regra 8.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emitentes', function (Blueprint $table) {
            $table->string('cnae', 7)->nullable()->after('inscricao_municipal');

            // Endereço do emitente (grupo enderEmit).
            $table->string('logradouro')->nullable()->after('crt');
            $table->string('numero', 60)->nullable()->after('logradouro');
            $table->string('complemento')->nullable()->after('numero');
            $table->string('bairro')->nullable()->after('complemento');
            $table->char('codigo_municipio', 7)->nullable()->after('bairro');
            $table->string('municipio')->nullable()->after('codigo_municipio');
            $table->char('uf', 2)->nullable()->after('municipio');
            $table->char('cep', 8)->nullable()->after('uf');
            $table->string('telefone', 20)->nullable()->after('cep');
            $table->string('email')->nullable()->after('telefone');
            $table->string('logo_path')->nullable()->after('email');

            // Configuração fiscal.
            $table->unsignedSmallInteger('serie_padrao')->default(1)->after('ambiente');
            $table->decimal('aliquota_credito_simples', 5, 2)->nullable()->after('serie_padrao');
            $table->text('info_complementares_padrao')->nullable()->after('aliquota_credito_simples');
            $table->string('autxml_documento', 14)->nullable()->after('info_complementares_padrao');

            // Rastro da virada para produção.
            $table->timestamp('producao_ativada_em')->nullable()->after('autxml_documento');
            $table->foreignId('producao_ativada_por')->nullable()->after('producao_ativada_em')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('emitentes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producao_ativada_por');
            $table->dropColumn([
                'cnae', 'logradouro', 'numero', 'complemento', 'bairro',
                'codigo_municipio', 'municipio', 'uf', 'cep', 'telefone', 'email',
                'logo_path', 'serie_padrao', 'aliquota_credito_simples',
                'info_complementares_padrao', 'autxml_documento', 'producao_ativada_em',
            ]);
        });
    }
};
