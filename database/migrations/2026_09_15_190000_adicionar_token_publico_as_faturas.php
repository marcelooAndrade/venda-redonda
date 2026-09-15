<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * O link que o cliente recebe para ver e pagar a fatura. UUID sorteado, sem
 * relação com o id: quem tem o link vê a fatura, quem não tem não chuta o
 * vizinho. Portado de `invoices.public_token` do projeto Marcelo Andrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->string('public_token', 36)->nullable()->unique()->after('status');
        });

        // As faturas que já existem também ganham link, uma a uma: o valor é
        // sorteado, não dá para escrever num UPDATE só.
        foreach (DB::table('faturas')->whereNull('public_token')->pluck('id') as $id) {
            DB::table('faturas')->where('id', $id)->update(['public_token' => (string) Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('faturas', function (Blueprint $table) {
            $table->dropColumn('public_token');
        });
    }
};
