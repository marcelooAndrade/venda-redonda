<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emitente_certificados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emitente_id')->constrained()->cascadeOnDelete();
            // O .pfx vai cifrado com Crypt para o disco privado. Nunca em texto claro.
            $table->string('arquivo_path');
            $table->text('senha');
            $table->string('titular');
            $table->string('cnpj', 14);
            $table->string('serial')->nullable();
            $table->string('fingerprint', 64)->index();
            $table->timestamp('valido_de')->nullable();
            $table->timestamp('valido_ate');
            $table->boolean('ativo')->default(false);
            $table->boolean('convertido_de_legado')->default(false);
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['emitente_id', 'ativo']);
            $table->index('valido_ate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emitente_certificados');
    }
};
