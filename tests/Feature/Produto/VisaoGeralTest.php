<?php

use App\Enums\EtapaCrm;
use App\Enums\Perfil;
use App\Livewire\Produto\VisaoGeral;
use App\Models\ContaFinanceira;
use App\Models\ContatoCrm;
use App\Models\Emitente;
use App\Models\MovimentoCaixa;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
});

function donoDoProdutoVisaoGeral(): User
{
    $user = usuarioMarca(Perfil::Administrador->value);
    $user->forceFill(['dono_do_produto' => true])->save();

    return $user;
}

it('o dono do produto abre a tela', function () {
    $this->actingAs(donoDoProdutoVisaoGeral())->get('/visao-geral')->assertOk();
});

it('administrador comum nao abre a tela', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))->get('/visao-geral')->assertForbidden();
});

it('o item Visão geral so aparece na navegacao para o dono', function () {
    $this->actingAs(donoDoProdutoVisaoGeral())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('visao-geral'));

    $this->actingAs(usuarioMarca(Perfil::Administrador->value))
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee(route('visao-geral'));
});

it('avisa que falta configurar quando o slug esta vazio', function () {
    config(['produto.tenant_administrativo' => null]);

    Livewire::actingAs(donoDoProdutoVisaoGeral())
        ->test(VisaoGeral::class)
        ->assertSee('Falta configurar o tenant administrativo');
});

it('resume o crm por etapa', function () {
    ContatoCrm::create(['nome' => 'Aberto 1', 'etapa' => EtapaCrm::Base]);
    ContatoCrm::create(['nome' => 'Aberto 2', 'etapa' => EtapaCrm::Negociacao]);
    ContatoCrm::create(['nome' => 'Ganho 1', 'etapa' => EtapaCrm::Ganho]);
    ContatoCrm::create(['nome' => 'Perdido 1', 'etapa' => EtapaCrm::Perdido]);

    $componente = Livewire::actingAs(donoDoProdutoVisaoGeral())->test(VisaoGeral::class);

    $crm = $componente->get('crm');

    expect($crm['total'])->toBe(4)
        ->and($crm['emAberto'])->toBe(2)
        ->and($crm['ganhos'])->toBe(1)
        ->and($crm['perdidos'])->toBe(1);
});

it('soma o financeiro do primeiro emitente do tenant administrativo', function () {
    $tenant = Tenant::create(['nome' => 'Marcelo Andrade', 'slug' => 'marcelo-andrade']);
    config(['produto.tenant_administrativo' => $tenant->slug]);

    $emitente = Emitente::factory()->create(['tenant_id' => $tenant->id]);
    $conta = ContaFinanceira::create(['emitente_id' => $emitente->id, 'nome' => 'Caixa', 'saldo_inicial_centavos' => 5_000]);

    MovimentoCaixa::create([
        'emitente_id' => $emitente->id, 'conta_financeira_id' => $conta->id,
        'sentido' => 'credito', 'valor_centavos' => 2_000, 'descricao' => 'Venda',
        'ocorrido_em' => today(), 'origem_tipo' => 'ajuste', 'created_at' => now(),
    ]);

    $componente = Livewire::actingAs(donoDoProdutoVisaoGeral())->test(VisaoGeral::class);

    expect($componente->get('financeiro')['saldoCentavos'])->toBe(7_000);
});
