<?php

namespace App\Enums;

/**
 * Plano contratado pelo tenant.
 *
 * Os nomes aqui descrevem o degrau comercial, mas o código nunca pergunta
 * "qual plano é": pergunta o que o plano permite. Assim renomear o degrau,
 * que é decisão de negócio, não obriga a caçar comparação espalhada.
 */
enum PlanoTenant: string
{
    case Gratuito = 'gratuito';
    case Avancado = 'avancado';

    /**
     * Domínio próprio e marca na tela de login.
     *
     * São o mesmo benefício visto de dois lados: o cliente entra por um
     * endereço dele e encontra a marca dele já na porta. No plano gratuito a
     * porta é a da Venda Redonda, e a marca do cliente aparece só depois que
     * ele entra.
     */
    public function permiteMarcaPropria(): bool
    {
        return $this === self::Avancado;
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Gratuito => 'Gratuito',
            self::Avancado => 'Avançado',
        };
    }
}
