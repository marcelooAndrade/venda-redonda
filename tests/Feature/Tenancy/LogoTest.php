<?php

use App\Enums\Perfil;
use App\Livewire\Tenancy\Marca;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fiscal\DanfeService;
use App\Support\TenantAtual;
use Database\Seeders\PerfilSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PerfilSeeder::class);
    Storage::fake('fiscal');
});

it('grava a logo do sistema no disco privado', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    Livewire::actingAs($user)->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->image('logo.png', 300, 80))
        ->call('salvarLogoSistema')
        ->assertHasNoErrors();

    $path = Tenant::find($user->tenant_id)->logo_path;

    expect($path)->not->toBeNull();
    Storage::disk('fiscal')->assertExists($path);
});

it('recusa arquivo que nao e imagem', function () {
    Livewire::actingAs(usuarioMarca(Perfil::Administrador->value))->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->create('contrato.pdf', 40, 'application/pdf'))
        ->call('salvarLogoSistema')
        ->assertHasErrors('logoSistema');
});

it('recusa imagem acima de um megabyte', function () {
    Livewire::actingAs(usuarioMarca(Perfil::Administrador->value))->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->image('enorme.png')->size(1500))
        ->call('salvarLogoSistema')
        ->assertHasErrors('logoSistema');
});

it('remover a logo volta para o fallback e apaga o arquivo', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    $componente = Livewire::actingAs($user)->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->image('logo.png'))
        ->call('salvarLogoSistema');

    $path = Tenant::find($user->tenant_id)->logo_path;

    $componente->call('removerLogoSistema');

    expect(Tenant::find($user->tenant_id)->logo_path)->toBeNull();
    Storage::disk('fiscal')->assertMissing($path);
});

it('trocar a logo apaga a anterior, para nao deixar orfao no bucket', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    $componente = Livewire::actingAs($user)->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->image('antiga.png'))
        ->call('salvarLogoSistema');

    $antiga = Tenant::find($user->tenant_id)->logo_path;

    $componente->set('logoSistema', UploadedFile::fake()->image('nova.png'))
        ->call('salvarLogoSistema');

    $nova = Tenant::find($user->tenant_id)->logo_path;

    expect($nova)->not->toBe($antiga);
    Storage::disk('fiscal')->assertMissing($antiga);
    Storage::disk('fiscal')->assertExists($nova);
});

it('serve a logo do tenant para quem esta autenticado', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    Livewire::actingAs($user)->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->image('logo.png'))
        ->call('salvarLogoSistema');

    $this->actingAs($user)->get('/marca/logo')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('nega a logo a visitante', function () {
    $this->get('/marca/logo')->assertRedirect('/login');
});

it('devolve 404 quando o tenant ainda nao tem logo', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))->get('/marca/logo')->assertNotFound();
});

it('nunca serve a logo de outro tenant', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    // Outro cliente, com logo própria no mesmo bucket.
    Storage::disk('fiscal')->put('marca/tenant/999/alheia.png', 'LOGO-DO-CONCORRENTE');
    Tenant::create([
        'nome' => 'Concorrente', 'slug' => 'concorrente',
        'logo_path' => 'marca/tenant/999/alheia.png',
    ]);

    Livewire::actingAs($user)->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->image('propria.png'))
        ->call('salvarLogoSistema');

    $conteudo = $this->actingAs($user)->get('/marca/logo')->streamedContent();

    expect($conteudo)->not->toBe('LOGO-DO-CONCORRENTE');
});

it('muda o etag quando a logo e trocada, para o navegador nao servir a antiga', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    $componente = Livewire::actingAs($user)->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->image('antiga.png'))
        ->call('salvarLogoSistema');

    $antes = $this->actingAs($user)->get('/marca/logo')->headers->get('ETag');

    $componente->set('logoSistema', UploadedFile::fake()->image('nova.png'))
        ->call('salvarLogoSistema');

    $depois = $this->actingAs($user)->get('/marca/logo')->headers->get('ETag');

    expect($antes)->not->toBeNull()->and($depois)->not->toBe($antes);
});

it('responde 304 quando o navegador ja tem a logo', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    Livewire::actingAs($user)->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->image('logo.png'))
        ->call('salvarLogoSistema');

    $etag = $this->actingAs($user)->get('/marca/logo')->headers->get('ETag');

    $this->actingAs($user)
        ->withHeaders(['If-None-Match' => $etag])
        ->get('/marca/logo')
        ->assertStatus(304);
});

it('a sidebar aponta para a rota da logo, nunca para o caminho no bucket', function () {
    $user = usuarioMarca(Perfil::Administrador->value);

    Livewire::actingAs($user)->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->image('logo.png'))
        ->call('salvarLogoSistema');

    $this->actingAs($user)->get('/marca')
        ->assertSee('src="'.route('marca.logo').'"', false)
        ->assertDontSee('marca/tenant/');
});

it('grava a logo do danfe no emitente em foco', function () {
    $user = usuarioMarca(Perfil::Administrador->value);
    $emitente = $user->emitentes()->first();

    Livewire::actingAs($user)->test(Marca::class)
        ->set('logoDanfe', UploadedFile::fake()->image('marca.png'))
        ->call('salvarLogoDanfe')
        ->assertHasNoErrors();

    $path = $emitente->fresh()->logo_path;

    expect($path)->not->toBeNull();
    Storage::disk('fiscal')->assertExists($path);
});

it('a logo do danfe e separada da logo do sistema', function () {
    $user = usuarioMarca(Perfil::Administrador->value);
    $emitente = $user->emitentes()->first();

    Livewire::actingAs($user)->test(Marca::class)
        ->set('logoSistema', UploadedFile::fake()->image('sistema.png'))
        ->call('salvarLogoSistema')
        ->set('logoDanfe', UploadedFile::fake()->image('danfe.png'))
        ->call('salvarLogoDanfe');

    $doDanfe = $emitente->fresh()->logo_path;
    $doSistema = Tenant::find($user->tenant_id)->logo_path;

    expect($doDanfe)->not->toBeNull()
        ->and($doSistema)->not->toBeNull()
        ->and($doDanfe)->not->toBe($doSistema)
        ->and($doDanfe)->toStartWith('marca/emitente/')
        ->and($doSistema)->toStartWith('marca/tenant/');
});

it('o danfe sai maior com a logo do emitente do que sem ela', function () {
    $nota = notaPronta();

    // O usuário é montado em volta do emitente da nota, e sem outro vínculo,
    // para que o `EmitenteAtual` resolva justamente esse.
    $user = User::factory()->create(['tenant_id' => app(TenantAtual::class)->obter()->id]);
    $user->emitentes()->attach($nota->emitente_id);
    setPermissionsTeamId($nota->emitente_id);
    $user->assignRole(Perfil::Administrador->value);

    $servico = app(DanfeService::class);
    $semLogo = strlen($servico->previa($nota, xmlAutorizado()));

    Livewire::actingAs($user)->test(Marca::class)
        ->set('logoDanfe', UploadedFile::fake()->image('marca.png', 400, 120))
        ->call('salvarLogoDanfe')
        ->assertHasNoErrors();

    $comLogo = strlen($servico->previa($nota->fresh(['emitente', 'itens', 'destinatario']), xmlAutorizado()));

    expect($comLogo)->toBeGreaterThan($semLogo);
});

it('a tela de marca oferece as duas logos, com a diferenca explicada', function () {
    $this->actingAs(usuarioMarca(Perfil::Administrador->value))->get('/marca')
        ->assertSee('Logo do sistema')
        ->assertSee('Logo do DANFE');
});
