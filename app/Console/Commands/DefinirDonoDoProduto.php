<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Marca quem administra o produto.
 *
 * Não existe tela para isso, de propósito: é uma pessoa, e conceder por tela
 * exigiria uma tela que atravessa tenants, que é a superfície que este
 * sistema não tem. O comando roda no servidor, por quem tem acesso a ele.
 */
class DefinirDonoDoProduto extends Command
{
    protected $signature = 'produto:definir-dono {email : E-mail de quem passa a administrar o produto}';

    protected $description = 'Marca um usuário como dono do produto, o único que vê o painel de empresas';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        // Sem escopo de tenant: o comando roda sem host, e o dono pode estar
        // em qualquer tenant.
        $user = User::query()->withoutGlobalScope('tenant')->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("Nenhum usuário com o e-mail {$email}.");

            return self::FAILURE;
        }

        $user->forceFill(['dono_do_produto' => true])->save();

        $this->components->info("{$user->name} agora administra o produto.");

        return self::SUCCESS;
    }
}
