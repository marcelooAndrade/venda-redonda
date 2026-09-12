<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Fiscal\ConversorLegado;
use App\Services\Fiscal\ConversorLegadoOpenssl;
use App\Services\Fiscal\NfephpSefazGateway;
use App\Services\Fiscal\SefazGateway;
use App\Services\Integrations\AdminPessoalGateway;
use App\Services\Integrations\GatewayDeLeads;
use App\Support\TenantAtual;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ConversorLegado::class, ConversorLegadoOpenssl::class);
        $this->app->bind(SefazGateway::class, NfephpSefazGateway::class);
        $this->app->bind(GatewayDeLeads::class, AdminPessoalGateway::class);
        $this->app->singleton(TenantAtual::class);

        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // A única habilidade que atravessa tenants. Não é permissão do spatie
        // porque essas são por emitente e o perfil Administrador as recebe
        // todas; esta é de uma pessoa, marcada por coluna. O `Gate::before`
        // do spatie devolve nulo para habilidade que ele não conhece, e a
        // decisão cai aqui.
        Gate::define('produto.administrar', fn (User $user): bool => $user->dono_do_produto === true);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
