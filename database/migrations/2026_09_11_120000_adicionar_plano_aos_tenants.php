<?php

use App\Enums\PlanoTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plano', 20)->default(PlanoTenant::Gratuito->value)->after('dominio');
        });

        // Quem já tem domínio próprio apontado está usufruindo do benefício, e
        // rebaixar em silêncio quebraria o acesso de um cliente em produção.
        DB::table('tenants')->whereNotNull('dominio')->update([
            'plano' => PlanoTenant::Avancado->value,
        ]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('plano');
        });
    }
};
