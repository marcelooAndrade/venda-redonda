<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('naturezas_operacao', function (Blueprint $table) {
            $table->char('cfop_exterior', 4)->nullable()->after('cfop_interestadual');
        });
    }

    public function down(): void
    {
        Schema::table('naturezas_operacao', fn (Blueprint $t) => $t->dropColumn('cfop_exterior'));
    }
};
