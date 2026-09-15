<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `webhook_url`: para onde o Nodo repassa mensagem recebida do cliente.
 * `webhook_secret`: parte da URL que a uazapi chama de volta — não é o
 * cliente que fala com a uazapi, então precisa de algo imprevisível na
 * própria rota para saber de qual instância veio, sem exigir token (quem
 * chama é a uazapi, não tem o token do cliente). Gerado em PHP na criação
 * da instância, por isso nenhum dos dois tem valor padrão de banco — mesma
 * lição do DEFAULT em JSON que quebrou o deploy anterior: nada de DEFAULT
 * aqui, e nullable cobre linha antiga sem exigir backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_instancias', function (Blueprint $table) {
            $table->string('webhook_url')->nullable()->after('status');
            $table->string('webhook_secret')->nullable()->unique()->after('webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_instancias', function (Blueprint $table) {
            $table->dropColumn(['webhook_url', 'webhook_secret']);
        });
    }
};
