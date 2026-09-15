<?php

use App\Enums\Perfil;
use App\Livewire\Financeiro\FaturaDetalhe;
use App\Models\ContaFinanceira;
use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\MovimentoCaixa;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->emitente = emitenteCompleto(['chave_pix' => '11222333000181']);
    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);

    $this->conta = ContaFinanceira::create(['emitente_id' => $this->emitente->id, 'nome' => 'Conta', 'saldo_inicial_centavos' => 0]);

    $this->fatura = Fatura::create(['emitente_id' => $this->emitente->id, 'titulo' => 'Consultoria']);
    $this->p1 = $this->fatura->parcelas()->create(['numero' => 1, 'descricao' => 'Entrada', 'valor_centavos' => 30_000, 'vencimento' => '2026-09-01']);
    $this->p2 = $this->fatura->parcelas()->create(['numero' => 2, 'descricao' => 'Saldo', 'valor_centavos' => 70_000, 'vencimento' => '2026-10-10']);
});

it('abre a fatura com o link publico, os totais e as parcelas', function () {
    $this->actingAs($this->user)->get('/faturas/'.$this->fatura->id)
        ->assertOk()
        ->assertSee('Consultoria')
        ->assertSee(route('fatura.publica', $this->fatura->public_token))
        ->assertSee('Entrada')
        ->assertSee('Saldo')
        ->assertSee('1.000,00');
});

it('nao abre fatura de outro emitente', function () {
    $outro = Emitente::factory()->create();
    $alheia = Fatura::create(['emitente_id' => $outro->id, 'titulo' => 'Alheia']);

    $this->actingAs($this->user)->get('/faturas/'.$alheia->id)->assertNotFound();
});

it('recebe uma parcela na conta e o caixa acompanha', function () {
    Livewire::actingAs($this->user)->test(FaturaDetalhe::class, ['fatura' => $this->fatura->id])
        ->set('contaBaixaId', $this->conta->id)
        ->call('baixar', $this->p1->id)
        ->assertHasNoErrors();

    expect($this->p1->fresh()->status)->toBe('pago')
        ->and($this->conta->fresh()->saldoCentavos())->toBe(30_000);
});

it('reabrir uma parcela paga estorna no caixa em vez de apagar', function () {
    Livewire::actingAs($this->user)->test(FaturaDetalhe::class, ['fatura' => $this->fatura->id])
        ->set('contaBaixaId', $this->conta->id)
        ->call('baixar', $this->p1->id)
        ->call('reabrir', $this->p1->id)
        ->assertHasNoErrors();

    $p1 = $this->p1->fresh();

    expect($p1->status)->toBe('pendente')
        ->and($p1->pago_em)->toBeNull()
        ->and($p1->conta_financeira_id)->toBeNull()
        ->and(MovimentoCaixa::count())->toBe(2)
        ->and(MovimentoCaixa::where('origem_tipo', 'estorno')->value('sentido'))->toBe('debito')
        ->and($this->conta->fresh()->saldoCentavos())->toBe(0);
});

it('altera o vencimento de uma parcela', function () {
    Livewire::actingAs($this->user)->test(FaturaDetalhe::class, ['fatura' => $this->fatura->id])
        ->call('iniciarVencimento', $this->p2->id)
        ->assertSet('novoVencimento', '2026-10-10')
        ->set('novoVencimento', '2026-10-20')
        ->call('salvarVencimento')
        ->assertHasNoErrors();

    expect($this->p2->fresh()->vencimento->toDateString())->toBe('2026-10-20');
});

it('edita titulo, observacoes e parcelas, e o pix acompanha o valor novo', function () {
    $pixAntes = $this->p2->fresh()->pix_payload;

    $componente = Livewire::actingAs($this->user)->test(FaturaDetalhe::class, ['fatura' => $this->fatura->id])
        ->call('abrirEdicao')
        ->assertSet('edTitulo', 'Consultoria')
        ->set('edTitulo', 'Consultoria de setembro')
        ->set('edObservacoes', 'Pague até o vencimento.');

    $linhas = $componente->get('edLinhas');
    $linhas[1]['valor'] = '750,00';
    $linhas[1]['descricao'] = 'Saldo final';

    $componente->set('edLinhas', $linhas)->call('salvarEdicao')->assertHasNoErrors();

    $fatura = $this->fatura->fresh();
    $p2 = $this->p2->fresh();

    expect($fatura->titulo)->toBe('Consultoria de setembro')
        ->and($fatura->observacoes)->toBe('Pague até o vencimento.')
        ->and($p2->valor_centavos)->toBe(75_000)
        ->and($p2->descricao)->toBe('Saldo final')
        ->and($p2->pix_payload)->not->toBe($pixAntes)
        ->and($p2->pix_payload)->toContain('5406750.00');
});

it('parcela paga nao muda de valor pela edicao', function () {
    Livewire::actingAs($this->user)->test(FaturaDetalhe::class, ['fatura' => $this->fatura->id])
        ->set('contaBaixaId', $this->conta->id)
        ->call('baixar', $this->p1->id);

    $componente = Livewire::actingAs($this->user)->test(FaturaDetalhe::class, ['fatura' => $this->fatura->id])
        ->call('abrirEdicao');

    $linhas = $componente->get('edLinhas');
    $linhas[0]['valor'] = '999,00';

    $componente->set('edLinhas', $linhas)->call('salvarEdicao')->assertHasErrors('edLinhas.0.valor');

    expect($this->p1->fresh()->valor_centavos)->toBe(30_000);
});
