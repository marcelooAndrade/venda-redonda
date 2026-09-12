<?php

namespace App\Services\Nfse;

use App\Enums\Fiscal\Ambiente;
use App\Models\EmitenteNfse;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Vira a NFS-e de homologação para produção.
 *
 * Mesmo desenho de `AtivarProducao` da NF-e: ação própria e auditada, nunca
 * um campo comum de formulário. O SIGISS não usa certificado nem responsável
 * técnico, então a única exigência é a senha do ambiente de produção.
 */
class AtivarProducaoNfse
{
    /**
     * @throws ValidationException
     */
    public function ativar(EmitenteNfse $config, User $user): void
    {
        if (blank($config->senha(Ambiente::Producao))) {
            throw ValidationException::withMessages([
                'ambiente' => 'Cadastre a senha de produção do SIGISS antes de ativar a produção.',
            ]);
        }

        $config->forceFill([
            'ambiente' => Ambiente::Producao,
            'producao_ativada_em' => now(),
            'producao_ativada_por' => $user->getKey(),
        ])->save();
    }

    /** Voltar é sempre o lado seguro: não exige nada. */
    public function voltarParaHomologacao(EmitenteNfse $config, User $user): void
    {
        $config->forceFill([
            'ambiente' => Ambiente::Homologacao,
            'producao_ativada_em' => null,
            'producao_ativada_por' => null,
        ])->save();
    }
}
