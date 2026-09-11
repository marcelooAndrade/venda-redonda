<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobrança Pix por parcela.
 *
 * A chave fica no emitente porque é dele a conta que recebe. Nome e cidade do
 * recebedor não viram campo novo: saem da razão social e do município que o
 * emitente já tem, e um dado só com duas fontes é dado que diverge.
 *
 * O payload é gravado, e não calculado na hora de exibir, porque ele é o que o
 * cliente recebeu. Se a chave mudar amanhã, a cobrança que já foi enviada
 * continua valendo o que valia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emitentes', function (Blueprint $table) {
            $table->string('chave_pix', 77)->nullable()->after('email');
        });

        Schema::table('fatura_parcelas', function (Blueprint $table) {
            $table->text('pix_payload')->nullable()->after('vencimento');
        });
    }

    public function down(): void
    {
        Schema::table('emitentes', function (Blueprint $table) {
            $table->dropColumn('chave_pix');
        });

        Schema::table('fatura_parcelas', function (Blueprint $table) {
            $table->dropColumn('pix_payload');
        });
    }
};
