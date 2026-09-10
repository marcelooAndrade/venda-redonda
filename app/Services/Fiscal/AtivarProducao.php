<?php

namespace App\Services\Fiscal;

use App\Enums\Fiscal\Ambiente;
use App\Models\Emitente;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Vira o emitente de homologação para produção.
 *
 * O app-transm resolve isso travando o ambiente em homologação por
 * `Rule::in`. É seguro, mas impede emitir de verdade. Aqui a virada existe,
 * porém é uma ação própria e auditada, nunca um campo de formulário comum:
 * a partir dela toda nota passa a ter valor fiscal. Ver DF-004.
 */
class AtivarProducao
{
    public function __construct(
        private readonly CertificateService $certificados,
    ) {}

    /**
     * @throws ValidationException
     */
    public function ativar(Emitente $emitente, User $user): void
    {
        $this->exigirCertificadoValido($emitente);
        $this->exigirResponsavelTecnico();

        $emitente->forceFill([
            'ambiente' => Ambiente::Producao,
            'producao_ativada_em' => now(),
            'producao_ativada_por' => $user->getKey(),
        ])->save();
    }

    /**
     * Voltar para homologação não exige nada: é sempre o lado seguro.
     */
    public function voltarParaHomologacao(Emitente $emitente, User $user): void
    {
        $emitente->forceFill([
            'ambiente' => Ambiente::Homologacao,
            'producao_ativada_em' => null,
            'producao_ativada_por' => null,
        ])->save();
    }

    private function exigirCertificadoValido(Emitente $emitente): void
    {
        $certificado = $this->certificados->ativo($emitente);

        if ($certificado === null) {
            throw ValidationException::withMessages([
                'ambiente' => 'Cadastre um certificado A1 antes de ativar a produção.',
            ]);
        }

        if ($certificado->vencido()) {
            throw ValidationException::withMessages([
                'ambiente' => 'O certificado ativo está vencido desde '
                    .$certificado->valido_ate->format('d/m/Y').'. Renove antes de ativar a produção.',
            ]);
        }
    }

    private function exigirResponsavelTecnico(): void
    {
        $faltando = collect(['cnpj', 'contato', 'email', 'telefone'])
            ->filter(fn (string $campo): bool => blank(config("fiscal.responsavel_tecnico.{$campo}")));

        if ($faltando->isNotEmpty()) {
            throw ValidationException::withMessages([
                'ambiente' => 'Responsável técnico não configurado ('.$faltando->implode(', ').'). '
                    .'A SEFAZ exige o grupo infRespTec, então preencha antes de ativar a produção.',
            ]);
        }
    }
}
