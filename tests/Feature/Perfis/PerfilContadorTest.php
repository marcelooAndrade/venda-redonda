<?php

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    $this->user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $this->user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $this->user->assignRole(Perfil::Contador->value);
});

it('existe como perfil do sistema', function () {
    expect(Perfil::cases())->toHaveCount(5)
        ->and(Perfil::Contador->value)->toBe('Contador');
});

it('escreve a regra fiscal', function () {
    expect($this->user->can('tributacao.gerenciar'))->toBeTrue();
});

it('exporta o pacote da contabilidade', function () {
    expect($this->user->can('contador.exportar'))->toBeTrue();
});

it('enxerga as notas para conferir', function () {
    expect($this->user->can('nota.ver'))->toBeTrue();
});

it('nao emite nota', function () {
    expect($this->user->can('nota.emitir'))->toBeFalse();
});

it('nao cancela nota', function () {
    expect($this->user->can('nota.cancelar'))->toBeFalse();
});

it('nao mexe no certificado', function () {
    expect($this->user->can('certificado.gerenciar'))->toBeFalse();
});

it('nao vira o ambiente para producao', function () {
    expect($this->user->can('emitente.ativar-producao'))->toBeFalse();
});

it('nao movimenta estoque', function () {
    expect($this->user->can('estoque.movimentar'))->toBeFalse();
});

it('nenhum outro perfil escreve regra fiscal, so o administrador', function () {
    foreach ([Perfil::Faturamento, Perfil::Estoque, Perfil::Consulta] as $perfil) {
        $outro = User::factory()->create();
        $outro->assignRole($perfil->value);

        expect($outro->can('tributacao.gerenciar'))
            ->toBeFalse("perfil {$perfil->value} nao deveria escrever regra fiscal");
    }
});
