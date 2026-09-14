<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptor geral da conta. Diferente de perder o acesso a uma empresa
 * (que é o vínculo com o emitente sendo desfeito): aqui a pessoa para de
 * autenticar em qualquer lugar. Ver
 * docs/superpowers/specs/2026-09-14-gestao-usuarios-design.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('ativo')->default(true)->after('dono_do_produto');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('ativo');
        });
    }
};
