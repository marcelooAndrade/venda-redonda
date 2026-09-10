<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Na devolução, cada item aponta o item da nota original.
 *
 * Descoberto lendo a sped-nfe: `tagDFeReferenciado` grava em
 * `aDFeReferenciado[$item]` e o render anexa ao `det`, não ao `ide`.
 * O referenciamento da NT 2025.002-RTC v1.40 é por item. Ver DF-002.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nota_itens', function (Blueprint $table) {
            $table->char('chave_referenciada', 44)->nullable()->after('cfop');
            $table->unsignedSmallInteger('item_referenciado')->nullable()->after('chave_referenciada');
        });
    }

    public function down(): void
    {
        Schema::table('nota_itens', fn (Blueprint $t) => $t->dropColumn(['chave_referenciada', 'item_referenciado']));
    }
};
