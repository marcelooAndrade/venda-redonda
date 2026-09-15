<?php

use App\Enums\EtapaCrm;
use App\Enums\Perfil;
use App\Livewire\Produto\Crm;
use App\Models\ContatoCrm;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
});

function donoDoProdutoCrm(): User
{
    $user = usuarioMarca(Perfil::Administrador->value);
    $user->forceFill(['dono_do_produto' => true])->save();

    return $user;
}

it('o dono do produto abre a tela', function () {
    $this->actingAs(donoDoProdutoCrm())->get('/crm')->assertOk();
});

it('administrador comum nao abre a tela', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))->get('/crm')->assertForbidden();
});

it('o item CRM so aparece na navegacao para o dono', function () {
    $this->actingAs(donoDoProdutoCrm())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('crm'));

    $this->actingAs(usuarioMarca(Perfil::Administrador->value))
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee(route('crm'));
});

it('novo contato nasce na etapa base', function () {
    Livewire::actingAs(donoDoProdutoCrm())
        ->test(Crm::class)
        ->set('novoNome', 'Fulano de Tal')
        ->set('novaEmpresa', 'Metalurgica Exemplo')
        ->call('criarContato');

    $contato = ContatoCrm::firstWhere('nome', 'Fulano de Tal');

    expect($contato)->not->toBeNull()
        ->and($contato->etapa)->toBe(EtapaCrm::Base)
        ->and($contato->empresa)->toBe('Metalurgica Exemplo');
});

it('exige nome para criar contato', function () {
    Livewire::actingAs(donoDoProdutoCrm())
        ->test(Crm::class)
        ->set('novoNome', '')
        ->call('criarContato')
        ->assertHasErrors('novoNome');

    expect(ContatoCrm::count())->toBe(0);
});

it('move o contato de etapa', function () {
    $contato = ContatoCrm::create(['nome' => 'Fulano', 'etapa' => EtapaCrm::Base]);

    Livewire::actingAs(donoDoProdutoCrm())
        ->test(Crm::class)
        ->call('moverEtapa', $contato->id, EtapaCrm::CallAgendada->value);

    expect($contato->fresh()->etapa)->toBe(EtapaCrm::CallAgendada);
});

it('agrupa os contatos por etapa para o board', function () {
    ContatoCrm::create(['nome' => 'Contato Base', 'etapa' => EtapaCrm::Base]);
    ContatoCrm::create(['nome' => 'Contato Ganho', 'etapa' => EtapaCrm::Ganho]);

    $componente = Livewire::actingAs(donoDoProdutoCrm())->test(Crm::class);

    $porEtapa = $componente->get('contatosPorEtapa');

    expect($porEtapa[EtapaCrm::Base->value])->toHaveCount(1)
        ->and($porEtapa[EtapaCrm::Ganho->value])->toHaveCount(1)
        ->and($porEtapa[EtapaCrm::Perdido->value])->toHaveCount(0);
});

it('salva observacao do contato', function () {
    $contato = ContatoCrm::create(['nome' => 'Fulano']);

    Livewire::actingAs(donoDoProdutoCrm())
        ->test(Crm::class)
        ->call('iniciarEdicaoObservacao', $contato->id)
        ->set('observacaoEmEdicao', 'Ligar semana que vem.')
        ->call('salvarObservacao', $contato->id);

    expect($contato->fresh()->observacao)->toBe('Ligar semana que vem.');
});
