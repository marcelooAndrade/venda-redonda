<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Perfil;
use App\Livewire\Certificados\Gerenciar;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function usuarioCom(string $perfil): array
{
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create(['cnpj' => '11222333000181']);
    $user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $user->assignRole($perfil);

    return [$user, $emitente];
}

function pfx(string $nome): UploadedFile
{
    // createWithContent preserva os bytes reais do certificado e ainda entrega
    // ao Livewire um upload que ele sabe manipular.
    return UploadedFile::fake()->createWithContent(
        "{$nome}.pfx",
        (string) file_get_contents(base_path("tests/Fixtures/certificados/{$nome}.pfx")),
    );
}

beforeEach(function () {
    Storage::fake('fiscal');
    $this->seed(PerfilSeeder::class);
});

it('envia o certificado pela tela', function () {
    [$user, $emitente] = usuarioCom(Perfil::Administrador->value);

    Livewire::actingAs($user)
        ->test(Gerenciar::class)
        ->set('arquivo', pfx('valido'))
        ->set('senha', 'teste123')
        ->call('enviar')
        ->assertHasNoErrors();

    expect($emitente->certificados()->count())->toBe(1);
});

it('mostra o erro de cnpj divergente na tela', function () {
    [$user] = usuarioCom(Perfil::Administrador->value);

    Livewire::actingAs($user)
        ->test(Gerenciar::class)
        ->set('arquivo', pfx('outro-cnpj'))
        ->set('senha', 'teste123')
        ->call('enviar')
        ->assertHasErrors('certificado');
});

it('limpa a senha da memoria depois do envio', function () {
    [$user] = usuarioCom(Perfil::Administrador->value);

    Livewire::actingAs($user)
        ->test(Gerenciar::class)
        ->set('arquivo', pfx('valido'))
        ->set('senha', 'teste123')
        ->call('enviar')
        ->assertSet('senha', '');
});

it('nega o envio ao perfil de consulta', function () {
    [$user] = usuarioCom(Perfil::Consulta->value);

    Livewire::actingAs($user)
        ->test(Gerenciar::class)
        ->set('arquivo', pfx('valido'))
        ->set('senha', 'teste123')
        ->call('enviar')
        ->assertForbidden();
});

it('exige a palavra de confirmacao para virar producao', function () {
    [$user, $emitente] = usuarioCom(Perfil::Administrador->value);
    config()->set('fiscal.responsavel_tecnico', [
        'cnpj' => '11444777000161', 'contato' => 'Marcelo', 'email' => 'm@e.com', 'telefone' => '1999999999',
    ]);

    Livewire::actingAs($user)
        ->test(Gerenciar::class)
        ->set('arquivo', pfx('valido'))->set('senha', 'teste123')->call('enviar')
        ->set('confirmacaoProducao', 'producao')
        ->call('ativarProducao')
        ->assertHasErrors('confirmacaoProducao');

    expect($emitente->fresh()->ambiente)->toBe(Ambiente::Homologacao);
});

it('vira para producao com a confirmacao correta', function () {
    [$user, $emitente] = usuarioCom(Perfil::Administrador->value);
    config()->set('fiscal.responsavel_tecnico', [
        'cnpj' => '11444777000161', 'contato' => 'Marcelo', 'email' => 'm@e.com', 'telefone' => '1999999999',
    ]);

    Livewire::actingAs($user)
        ->test(Gerenciar::class)
        ->set('arquivo', pfx('valido'))->set('senha', 'teste123')->call('enviar')
        ->set('confirmacaoProducao', 'PRODUCAO')
        ->call('ativarProducao')
        ->assertHasNoErrors();

    expect($emitente->fresh()->ambiente)->toBe(Ambiente::Producao);
});
