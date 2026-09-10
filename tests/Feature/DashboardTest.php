<?php

namespace Tests\Feature;

use App\Enums\Perfil;
use App\Models\Emitente;
use App\Models\User;
use Database\Seeders\PerfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    /**
     * O painel é por emitente. Usuário sem vínculo não tem painel para ver,
     * e devolver 404 evita revelar que a rota existe para outro emitente.
     */
    public function test_user_without_emitente_gets_not_found(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertNotFound();
    }

    public function test_user_linked_to_an_emitente_sees_the_panel(): void
    {
        $this->seed(PerfilSeeder::class);

        $user = User::factory()->create();
        $emitente = Emitente::factory()->create();
        $user->emitentes()->attach($emitente);
        setPermissionsTeamId($emitente->id);
        $user->assignRole(Perfil::Administrador->value);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Painel');
    }
}
