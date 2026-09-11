<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Enums\Perfil;
use App\Enums\PlanoTenant;
use App\Models\Emitente;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Documento;
use App\Support\HostDoProduto;
use App\Support\TenantAtual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cadastro pela porta do produto.
 *
 * Não cria só um usuário: cria a empresa inteira, porque neste sistema uma
 * conta solta não serve para nada. A permissão é escopada por emitente, então
 * sem emitente a pessoa entra e leva 403 em toda tela.
 *
 * São quatro coisas numa transação: tenant no plano gratuito, emitente,
 * usuário e papel de administrador. Se qualquer uma falhar, nenhuma fica.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'razao_social' => ['required', 'string', 'max:255'],
            'cnpj' => [
                'required', 'string',
                function (string $atributo, mixed $valor, callable $falhar): void {
                    if (! Documento::cnpjValido((string) $valor)) {
                        $falhar('O CNPJ informado não é válido.');
                    }
                },
                // Sem escopo de tenant: o CNPJ é único no país, não por cliente.
                Rule::unique('emitentes', 'cnpj')->where(
                    fn ($q) => $q->where('cnpj', Documento::normalizarCnpj((string) ($input['cnpj'] ?? '')))
                ),
            ],
            'inscricao_estadual' => ['required', 'string', 'max:20'],
            'crt' => ['required', 'string', Rule::in(['1', '2', '3'])],
            'telefone' => ['required', 'string', 'max:20'],
        ], [
            'razao_social.required' => 'Informe a razão social da empresa.',
            'cnpj.required' => 'Informe o CNPJ da empresa.',
            'cnpj.unique' => 'Este CNPJ já está cadastrado.',
            'inscricao_estadual.required' => 'Informe a inscrição estadual, ou ISENTO.',
            'crt.required' => 'Informe o regime tributário.',
            'telefone.required' => 'Informe um telefone para contato.',
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $tenant = Tenant::create([
                'nome' => $input['razao_social'],
                'slug' => $this->slugLivre($input['razao_social']),
                'plano' => PlanoTenant::Gratuito,
            ]);

            // O escopo de tenant é resolvido do contêiner, e a empresa acabou
            // de nascer. Sem isto, o emitente sairia com `tenant_id` nulo.
            app(TenantAtual::class)->definir($tenant);

            $emitente = Emitente::create([
                'tenant_id' => $tenant->getKey(),
                'razao_social' => $input['razao_social'],
                'cnpj' => Documento::normalizarCnpj($input['cnpj']),
                'inscricao_estadual' => $input['inscricao_estadual'],
                'crt' => $input['crt'],
                'telefone' => $input['telefone'],
            ]);

            $user = User::create([
                'tenant_id' => $tenant->getKey(),
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->emitentes()->attach($emitente);

            // Papel é escopado por emitente: sem definir o time, o `assignRole`
            // grava no time errado e a pessoa entra sem permissão nenhuma.
            app(PermissionRegistrar::class)->setPermissionsTeamId($emitente->getKey());
            $user->assignRole(Perfil::Administrador->value);

            return $user;
        });
    }

    /**
     * Slug a partir do nome da empresa, sem colidir com o que é reservado.
     *
     * Reservado importa mesmo sem subdomínio de cliente: `app` capturaria o
     * host do próprio produto se o esquema de subdomínio voltar.
     */
    private function slugLivre(string $nome): string
    {
        $base = Str::limit(Str::slug($nome), 40, '') ?: 'empresa';
        $candidato = $base;
        $sufixo = 1;

        while (
            in_array($candidato, HostDoProduto::slugsReservados(), true)
            || Tenant::where('slug', $candidato)->exists()
        ) {
            $sufixo++;
            $candidato = "{$base}-{$sufixo}";
        }

        return $candidato;
    }
}
