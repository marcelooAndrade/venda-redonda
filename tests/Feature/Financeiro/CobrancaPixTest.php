<?php

use App\Enums\Perfil;
use App\Livewire\Financeiro\ContasReceber;
use App\Models\FaturaParcela;
use App\Models\User;
use App\Support\Pix;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->emitente = emitenteCompleto();
    $this->user = User::factory()->create(['tenant_id' => $this->emitente->tenant_id]);
    $this->user->emitentes()->attach($this->emitente);
    setPermissionsTeamId($this->emitente->id);
    $this->user->assignRole(Perfil::Administrador->value);
});

function lancarFatura($user, string $valor = '900,00', int $parcelas = 3): void
{
    Livewire::actingAs($user)->test(ContasReceber::class)
        ->set('titulo', 'Venda 2001')
        ->set('valor', $valor)
        ->set('parcelas', $parcelas)
        ->set('primeiroVencimento', today()->toDateString())
        ->call('lancar')
        ->assertHasNoErrors();
}

it('sem chave cadastrada, a parcela nasce sem cobranca', function () {
    lancarFatura($this->user);

    expect(FaturaParcela::pluck('pix_payload')->filter())->toBeEmpty();
});

it('com chave, cada parcela ganha o proprio codigo', function () {
    $this->emitente->forceFill(['chave_pix' => '11222333000181'])->save();

    lancarFatura($this->user);

    $parcelas = FaturaParcela::orderBy('numero')->get();

    expect($parcelas)->toHaveCount(3)
        ->and($parcelas->pluck('pix_payload')->filter())->toHaveCount(3)
        // Códigos diferentes: o identificador carrega o número da parcela.
        ->and($parcelas->pluck('pix_payload')->unique())->toHaveCount(3);
});

it('o codigo carrega o valor da parcela, e nao o total', function () {
    $this->emitente->forceFill(['chave_pix' => '11222333000181'])->save();

    lancarFatura($this->user, '900,00', 3);

    $primeira = FaturaParcela::orderBy('numero')->first();

    // 300,00 em campo 54, com o tamanho declarado.
    expect($primeira->pix_payload)->toContain('5406300.00')
        ->and($primeira->pix_payload)->not->toContain('900.00');
});

it('o codigo fecha com CRC valido, que e o que o banco confere', function () {
    $this->emitente->forceFill(['chave_pix' => '11222333000181'])->save();

    lancarFatura($this->user, '150,00', 1);

    $payload = FaturaParcela::first()->pix_payload;

    expect(substr($payload, -4))->toBe(Pix::crc16(substr($payload, 0, -4)));
});

it('chave invalida nao derruba o lancamento da fatura', function () {
    // Cobrança é conveniência; o título é o que não pode faltar.
    $this->emitente->forceFill(['chave_pix' => str_repeat('x', 80)])->save();

    lancarFatura($this->user, '100,00', 1);

    expect(FaturaParcela::count())->toBe(1)
        ->and(FaturaParcela::first()->pix_payload)->toBeNull();
});
