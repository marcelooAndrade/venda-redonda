<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A administração da empresa Marcelo Andrade saiu deste sistema em 15/09:
 * vive no projeto omarceloandrade. O EmitirAgora é só o produto que cada
 * empresa usa para si. Vão embora o funil de negócio, a situação comercial
 * das empresas e a marca de dono do produto. As migrations que criaram isso
 * ficam como história, porque já rodaram em produção.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contatos_crm');

        if (Schema::hasColumn('tenants', 'situacao_comercial')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('situacao_comercial');
            });
        }

        if (Schema::hasColumn('users', 'dono_do_produto')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('dono_do_produto');
            });
        }
    }

    public function down(): void
    {
        // Não volta: o que foi removido não tem mais tela nem código que o leia.
    }
};
