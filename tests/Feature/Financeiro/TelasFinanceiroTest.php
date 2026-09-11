<?php

use App\Enums\Perfil;
use App\Livewire\Financeiro\ContasPagar;
use App\Livewire\Financeiro\ContasReceber;
use App\Models\ContaFinanceira;
use App\Models\ContaPagar as TituloPagar;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\MovimentoCaixa;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->emitente = emitenteCompleto();

    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);

    $this->conta = ContaFinanceira::create([
        'emitente_id' => $this->emitente->id,
        'nome' => 'Conta movimento',
        'saldo_inicial_centavos' => 100_000,
    ]);
});

it('quem nao tem permissao nao abre o financeiro', function (string $rota) {
    $consulta = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $consulta->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $consulta->assignRole(Perfil::Estoque->value);

    $this->actingAs($consulta)->get($rota)->assertForbidden();
})->with(['/contas-a-pagar', '/contas-a-receber']);

it('o administrador abre as duas telas', function (string $rota) {
    $this->actingAs($this->user)->get($rota)->assertOk();
})->with(['/contas-a-pagar', '/contas-a-receber']);

it('lanca um titulo a pagar com o valor digitado em reais', function () {
    Livewire::actingAs($this->user)->test(ContasPagar::class)
        ->set('descricao', 'Energia de setembro')
        ->set('fornecedor', 'CPFL')
        ->set('valor', 'R$ 1.250,90')
        ->set('vencimento', today()->toDateString())
        ->call('lancar')
        ->assertHasNoErrors();

    $titulo = TituloPagar::firstWhere('descricao', 'Energia de setembro');

    expect($titulo)->not->toBeNull()
        ->and($titulo->valor_centavos)->toBe(125090)
        ->and($titulo->status)->toBe('pendente');
});

it('recusa titulo sem valor', function () {
    Livewire::actingAs($this->user)->test(ContasPagar::class)
        ->set('descricao', 'Sem valor')
        ->set('valor', 'abc')
        ->set('vencimento', today()->toDateString())
        ->call('lancar')
        ->assertHasErrors('valor');

    expect(TituloPagar::count())->toBe(0);
});

it('baixa um titulo pela tela e o caixa acompanha', function () {
    $titulo = TituloPagar::create([
        'emitente_id' => $this->emitente->id, 'descricao' => 'Energia',
        'valor_centavos' => 35_000, 'vencimento' => today(),
    ]);

    Livewire::actingAs($this->user)->test(ContasPagar::class)
        ->set('contaBaixaId', $this->conta->id)
        ->call('baixar', $titulo->id)
        ->assertHasNoErrors();

    expect($titulo->fresh()->status)->toBe('pago')
        ->and($this->conta->fresh()->saldoCentavos())->toBe(65_000)
        ->and(MovimentoCaixa::count())->toBe(1);
});

it('nao enxerga titulo de outro emitente', function () {
    $outro = Emitente::factory()->create();
    TituloPagar::create([
        'emitente_id' => $outro->id, 'descricao' => 'Da outra empresa',
        'valor_centavos' => 1_000, 'vencimento' => today(),
    ]);

    Livewire::actingAs($this->user)->test(ContasPagar::class)
        ->assertDontSee('Da outra empresa');
});

it('cria fatura com parcelas e o total confere', function () {
    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->set('titulo', 'Venda 1001')
        ->set('valor', '900,00')
        ->set('parcelas', 3)
        ->set('primeiroVencimento', today()->toDateString())
        ->call('lancar')
        ->assertHasNoErrors();

    $fatura = Fatura::firstWhere('titulo', 'Venda 1001');

    expect($fatura->parcelas)->toHaveCount(3)
        ->and($fatura->totalCentavos())->toBe(90_000)
        ->and($fatura->parcelas->pluck('valor_centavos')->all())->toBe([30_000, 30_000, 30_000]);
});

it('divide sobra de centavo na primeira parcela, e nao no ar', function () {
    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->set('titulo', 'Venda 1002')
        ->set('valor', '100,00')
        ->set('parcelas', 3)
        ->set('primeiroVencimento', today()->toDateString())
        ->call('lancar');

    $fatura = Fatura::firstWhere('titulo', 'Venda 1002');

    expect($fatura->parcelas->pluck('valor_centavos')->all())->toBe([3_334, 3_333, 3_333])
        ->and($fatura->totalCentavos())->toBe(10_000);
});
