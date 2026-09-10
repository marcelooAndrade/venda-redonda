<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant é a empresa cliente que usa o sistema. Fica acima do emitente:
 * um tenant pode ter matriz e filiais, cada uma com seu CNPJ emitente.
 *
 * A marca vive aqui. O tema é resolvido pelo host e injetado como
 * sobrescrita das variáveis CSS, sem recompilar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            // Subdomínio: rcm.emissor.com.br
            $table->string('slug')->unique();
            // Domínio próprio: sistema.rcmdobrasil.com.br
            $table->string('dominio')->nullable()->unique();
            $table->string('logo_path')->nullable();
            $table->string('nome_curto', 40)->nullable();
            // Paleta da marca, já validada em contraste no momento da gravação.
            $table->json('tema')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
            $table->index('tenant_id');
        });

        Schema::table('emitentes', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();
            $table->index('tenant_id');
        });

        // Dados já existentes ganham um tenant, para não ficarem órfãos e
        // invisíveis depois que o escopo global entrar em vigor.
        if (DB::table('emitentes')->exists() || DB::table('users')->exists()) {
            $id = DB::table('tenants')->insertGetId([
                'nome' => 'Tenant principal',
                'slug' => 'principal',
                'ativo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('emitentes')->whereNull('tenant_id')->update(['tenant_id' => $id]);
            DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('emitentes', fn (Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('tenant_id'));
        Schema::dropIfExists('tenants');
    }
};
