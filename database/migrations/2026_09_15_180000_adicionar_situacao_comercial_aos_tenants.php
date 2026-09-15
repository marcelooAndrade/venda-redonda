<?php

use App\Enums\SituacaoComercialTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `string`, não `json`/`text`: pode ter DEFAULT em MySQL.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('situacao_comercial', 20)->default(SituacaoComercialTenant::Novo->value)->after('plano');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('situacao_comercial');
        });
    }
};
