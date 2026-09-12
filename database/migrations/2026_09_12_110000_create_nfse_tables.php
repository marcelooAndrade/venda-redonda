<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NFS-e de Araras pelo SIGISS, por emitente.
 *
 * Três tabelas: a configuração do prestador, o catálogo de serviços e as
 * notas. Tudo escopado por emitente, porque matriz e filial têm inscrição
 * municipal e senha próprias. Inscrição municipal e endereço já estão em
 * `emitentes`, então não se repetem aqui.
 *
 * O ambiente da NFS-e é separado do ambiente da NF-e: um emitente pode
 * emitir NFS-e em produção e nunca ter emitido uma NF-e.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emitente_nfse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('habilitado')->default(false);
            $table->string('ambiente', 12)->default('homologacao');
            // Cifradas pelo cast `encrypted`, com a APP_KEY, como a senha do certificado.
            $table->text('senha_homologacao')->nullable();
            $table->text('senha_producao')->nullable();
            $table->string('serie_rps', 5)->default('1');
            // Um contador por ambiente: homologação e produção têm numerações
            // independentes no SIGISS, e misturá-las causa "RPS já utilizado".
            $table->unsignedInteger('proximo_rps_homologacao')->default(1);
            $table->unsignedInteger('proximo_rps_producao')->default(1);
            $table->timestamp('producao_ativada_em')->nullable();
            $table->foreignId('producao_ativada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('servicos_nfse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->string('nome', 120);
            // Lista de serviços da LC 116, no formato que o SIGISS usa: 00.00.00.
            $table->string('codigo_servico', 12);
            $table->string('codigo_nbs', 20)->nullable();
            $table->string('c_class_trib', 10)->nullable();
            $table->string('ind_op', 10)->nullable();
            // Centésimos de ponto percentual: 200 é 2,00%. Inteiro, como o dinheiro.
            $table->unsignedSmallInteger('aliquota_iss_bp')->default(0);
            $table->boolean('iss_retido')->default(false);
            $table->text('descricao_padrao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['emitente_id', 'nome']);
        });

        Schema::create('notas_servico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fatura_parcela_id')->constrained('fatura_parcelas')->restrictOnDelete();
            $table->foreignId('servico_nfse_id')->constrained('servicos_nfse')->restrictOnDelete();
            $table->string('provedor', 20)->default('sigiss');
            // Gravado na nota: PDF e cancelamento usam o ambiente dela, não o atual.
            $table->string('ambiente', 12);
            $table->string('status', 12);
            $table->unsignedInteger('numero_rps');
            $table->string('serie_rps', 5);
            $table->string('numero_nfse', 20)->nullable();
            $table->string('serie_nfse', 20)->nullable();
            $table->string('codigo_verificacao', 100)->nullable();
            // Sempre nula no SIGISS. Existe para o provedor nacional.
            $table->string('chave_acesso', 50)->nullable();
            // Cópia do serviço no momento da emissão. Editar o catálogo depois
            // não muda o que foi enviado.
            $table->string('codigo_servico', 12);
            $table->string('codigo_nbs', 20)->nullable();
            $table->string('c_class_trib', 10)->nullable();
            $table->string('ind_op', 10)->nullable();
            $table->unsignedSmallInteger('aliquota_iss_bp');
            $table->boolean('iss_retido');
            $table->text('descricao');
            $table->bigInteger('valor_centavos');
            $table->string('xml_envio_path')->nullable();
            $table->string('xml_retorno_path')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->foreignId('emitida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emitida_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->string('motivo_cancelamento', 255)->nullable();
            $table->timestamps();

            $table->unique(['fatura_parcela_id', 'ambiente']);
            $table->unique(['emitente_id', 'ambiente', 'serie_rps', 'numero_rps'], 'notas_servico_rps_unico');
            $table->index(['emitente_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_servico');
        Schema::dropIfExists('servicos_nfse');
        Schema::dropIfExists('emitente_nfse');
    }
};
