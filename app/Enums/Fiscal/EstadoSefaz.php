<?php

namespace App\Enums\Fiscal;

/**
 * O que o indicador do topo sabe sobre o serviço da SEFAZ.
 *
 * Três dos cinco estados não vêm da SEFAZ: sem certificado, sem resposta e
 * não consultada são situações do próprio sistema. O indicador precisa
 * distingui-las de "paralisada", senão um certificado ausente pareceria
 * SEFAZ fora do ar.
 */
enum EstadoSefaz: string
{
    case Operando = 'operando';
    case Paralisada = 'paralisada';
    case SemResposta = 'sem_resposta';
    case SemCertificado = 'sem_certificado';
    case SemConsulta = 'sem_consulta';

    public function rotulo(): string
    {
        return match ($this) {
            self::Operando => 'em operação',
            self::Paralisada => 'paralisada',
            self::SemResposta => 'sem resposta',
            self::SemCertificado => 'sem certificado',
            self::SemConsulta => 'não consultada',
        };
    }

    /**
     * Cor da bolinha. Verde e vermelho são o par validado para daltonismo
     * no design system, e aqui o texto ao lado carrega o sentido também.
     * Os estados do próprio sistema ficam em cinza: não são notícia da SEFAZ.
     */
    public function classeIndicador(): string
    {
        return match ($this) {
            self::Operando => 'bg-success-600',
            self::Paralisada => 'bg-danger-600',
            self::SemResposta => 'bg-ember-500',
            self::SemCertificado, self::SemConsulta => 'bg-graphite-400',
        };
    }
}
