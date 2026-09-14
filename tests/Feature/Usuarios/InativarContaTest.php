<?php

use App\Enums\Perfil;
use App\Livewire\Usuarios\Cadastro;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

function adminEAlvoDeUmaEmpresaSo(): array
{
    test()->seed(PerfilSeeder::class);
    $emitente = Emitente::factory()->create();

    $admin = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $admin->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $admin->assignRole(Perfil::Administrador->value);

    $alvo = User::factory()->create(['tenant_id' => $emitente->tenant_id]);
    $alvo->emitentes()->attach($emitente);
    app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
    $alvo->assignRole(Perfil::Contador->value);

    test()->actingAs($admin);

    return [$admin, $alvo, $emitente];
}

it('inativa a conta de um usuario que so tem esta empresa', function () {
    [, $alvo] = adminEAlvoDeUmaEmpresaSo();

    Livewire::test(Cadastro::class)->call('inativar', $alvo->id);

    expect($alvo->fresh()->ativo)->toBeFalse();
});

it('reativa a conta', function () {
    [, $alvo] = adminEAlvoDeUmaEmpresaSo();
    $alvo->forceFill(['ativo' => false])->save();

    Livewire::test(Cadastro::class)->call('reativar', $alvo->id);

    expect($alvo->fresh()->ativo)->toBeTrue();
});

it('nao inativa a conta inteira de quem tambem tem outra empresa', function () {
    [, $alvo] = adminEAlvoDeUmaEmpresaSo();
    // Tenant explícito e diferente: senão o emitente cairia no mesmo tenant
    // "teste" ambiente e `emitentesDaEmpresa()` o contaria como desta
    // empresa, o que faria o teste passar mesmo sem a trava funcionar.
    $outroTenant = Tenant::create(['nome' => 'Outra Empresa', 'slug' => 'outra-'.uniqid()]);
    $outraEmpresa = Emitente::factory()->create(['tenant_id' => $outroTenant->id]);
    $alvo->emitentes()->attach($outraEmpresa);

    Livewire::test(Cadastro::class)->call('inativar', $alvo->id);

    expect($alvo->fresh()->ativo)->toBeTrue()
        ->and($alvo->fresh()->podeAcessar($outraEmpresa))->toBeTrue();
});
