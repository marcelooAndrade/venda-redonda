<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem administra o produto, e não um tenant.
 *
 * É coluna, e não permissão do spatie, de propósito: as permissões são
 * escopadas por emitente e o perfil Administrador recebe todas. Uma permissão
 * "ver todas as empresas" chegaria a todo administrador de todo cliente, que
 * é exatamente quem não pode vê-las.
 *
 * Não é preenchível em massa e não tem tela. Liga-se por comando artisan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('dono_do_produto')->default(false)->after('ultimo_acesso_em');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dono_do_produto');
        });
    }
};
