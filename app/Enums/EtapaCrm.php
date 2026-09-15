<?php

namespace App\Enums;

/**
 * Etapa do funil de negócio, na área administrativa (não tem relação com
 * tenant/emitente do sistema fiscal — é o funil de quem pode virar cliente
 * de desenvolvimento sob medida). Mesmo vocabulário de etapas do projeto
 * Marcelo Andrade, sem o motor de cadência automática nem a qualificação
 * BANT que vieram junto lá: medido que quase não eram usados.
 */
enum EtapaCrm: string
{
    case Base = 'base';
    case EmConexao = 'em_conexao';
    case EmContato = 'em_contato';
    case CallAgendada = 'call_agendada';
    case CallRealizada = 'call_realizada';
    case Negociacao = 'negociacao';
    case Ganho = 'ganho';
    case Perdido = 'perdido';

    public function rotulo(): string
    {
        return match ($this) {
            self::Base => 'Base',
            self::EmConexao => 'Em conexão',
            self::EmContato => 'Em contato',
            self::CallAgendada => 'Call agendada',
            self::CallRealizada => 'Call realizada',
            self::Negociacao => 'Negociação',
            self::Ganho => 'Ganho',
            self::Perdido => 'Perdido',
        };
    }

    /** Etapa final: não recebe mais movimentação normal do funil. */
    public function terminal(): bool
    {
        return in_array($this, [self::Ganho, self::Perdido], true);
    }
}
