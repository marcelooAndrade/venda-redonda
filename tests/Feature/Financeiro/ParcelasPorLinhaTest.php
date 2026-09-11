<?php

use App\Enums\Perfil;
use App\Livewire\Financeiro\ContasReceber;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

/**
 * A origem recebe cada parcela pronta, com descrição, valor e vencimento
 * próprios. A divisão automática é atalho para o caso comum, e não a única
 * forma: negociação de verdade tem entrada, valores diferentes e datas que não
 * caem de mês em mês.
 */
beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->emitente = emitenteCompleto();
    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);
});

it('gerar preenche as linhas com a divisao', function () {
    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->set('titulo', 'Venda 3001')
        ->set('valor', '900,00')
        ->set('parcelas', 3)
        ->set('primeiroVencimento', '2026-10-05')
        ->call('gerarLinhas')
        ->assertSet('linhas.0.valor', '300,00')
        ->assertSet('linhas.1.vencimento', '2026-11-05')
        ->assertSet('linhas.2.vencimento', '2026-12-05');
});

it('lanca com entrada e saldo, valores e datas diferentes', function () {
    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->set('titulo', 'Venda 3002')
        ->set('linhas', [
            ['descricao' => 'Entrada', 'valor' => '500,00', 'vencimento' => '2026-10-01'],
            ['descricao' => 'Saldo em 45 dias', 'valor' => '250,00', 'vencimento' => '2026-11-15'],
            ['descricao' => 'Saldo em 75 dias', 'valor' => '250,00', 'vencimento' => '2026-12-15'],
        ])
        ->call('lancar')
        ->assertHasNoErrors();

    $fatura = Fatura::firstWhere('titulo', 'Venda 3002');
    $parcelas = $fatura->parcelas;

    expect($parcelas->pluck('valor_centavos')->all())->toBe([50_000, 25_000, 25_000])
        ->and($parcelas->pluck('vencimento')->map->toDateString()->all())
        ->toBe(['2026-10-01', '2026-11-15', '2026-12-15'])
        ->and($parcelas->first()->descricao)->toBe('Entrada')
        ->and($fatura->totalCentavos())->toBe(100_000);
});

it('recusa linha com valor zerado, sem criar fatura pela metade', function () {
    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->set('titulo', 'Venda 3003')
        ->set('linhas', [
            ['descricao' => 'Primeira', 'valor' => '100,00', 'vencimento' => '2026-10-01'],
            ['descricao' => 'Zerada', 'valor' => '0', 'vencimento' => '2026-11-01'],
        ])
        ->call('lancar')
        ->assertHasErrors();

    expect(Fatura::count())->toBe(0)
        ->and(FaturaParcela::count())->toBe(0);
});

it('recusa linha sem vencimento', function () {
    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->set('titulo', 'Venda 3004')
        ->set('linhas', [
            ['descricao' => 'Sem data', 'valor' => '100,00', 'vencimento' => ''],
        ])
        ->call('lancar')
        ->assertHasErrors();

    expect(Fatura::count())->toBe(0);
});

it('sem linhas, nao lanca nada', function () {
    Livewire::actingAs($this->user)->test(ContasReceber::class)
        ->set('titulo', 'Venda 3005')
        ->set('linhas', [])
        ->call('lancar')
        ->assertHasErrors();

    expect(Fatura::count())->toBe(0);
});
