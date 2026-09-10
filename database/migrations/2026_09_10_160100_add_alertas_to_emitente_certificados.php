<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emitente_certificados', function (Blueprint $table) {
            // Marcos de vencimento já avisados, para não repetir o mesmo alerta.
            $table->json('alertas_enviados')->nullable()->after('convertido_de_legado');
        });
    }

    public function down(): void
    {
        Schema::table('emitente_certificados', function (Blueprint $table) {
            $table->dropColumn('alertas_enviados');
        });
    }
};
