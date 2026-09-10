<?php

namespace Database\Factories;

use App\Models\Emitente;
use App\Models\Tenant;
use App\Support\TenantAtual;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Emitente>
 */
class EmitenteFactory extends Factory
{
    protected $model = Emitente::class;

    public function definition(): array
    {
        return [
            'tenant_id' => app(TenantAtual::class)->id()
                ?? Tenant::query()->first()?->getKey()
                ?? Tenant::create(['nome' => 'Tenant de teste', 'slug' => 'teste'])->getKey(),
            'razao_social' => $this->faker->company().' Ltda',
            'nome_fantasia' => $this->faker->company(),
            'cnpj' => (string) $this->faker->unique()->numerify('##############'),
            'inscricao_estadual' => (string) $this->faker->numerify('############'),
            'crt' => '3',
            'ativo' => true,
        ];
    }
}
