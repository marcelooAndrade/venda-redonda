<?php

namespace App\Enums\Fiscal;

/**
 * Ciclo de vida da NF-e.
 *
 * O tratamento visual carrega informação além da cor: estado em curso usa
 * tonalidade, estado terminal usa preenchimento sólido, e o inutilizado usa
 * borda tracejada porque nada chegou a existir. Assim a leitura sobrevive em
 * monocromia e para quem não distingue matiz. Ver docs/design-system.md.
 */
enum NFeStatus: string
{
    case Rascunho = 'rascunho';
    case EmProcessamento = 'em_processamento';
    case Autorizada = 'autorizada';
    case Rejeitada = 'rejeitada';
    case Denegada = 'denegada';
    case Cancelada = 'cancelada';
    case Inutilizada = 'inutilizada';
    case Contingencia = 'contingencia';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::EmProcessamento => 'Em processamento',
            self::Autorizada => 'Autorizada',
            self::Rejeitada => 'Rejeitada',
            self::Denegada => 'Denegada',
            self::Cancelada => 'Cancelada',
            self::Inutilizada => 'Inutilizada',
            self::Contingencia => 'Contingência',
        };
    }

    public function classesBadge(): string
    {
        return match ($this) {
            self::Rascunho => 'bg-graphite-100 text-graphite-800',
            self::EmProcessamento => 'bg-steel-100 text-steel-800',
            self::Autorizada => 'bg-success-100 text-success-800',
            self::Rejeitada => 'bg-danger-100 text-danger-800',
            self::Denegada => 'bg-danger-800 text-white',
            self::Cancelada => 'bg-graphite-800 text-white',
            self::Inutilizada => 'bg-graphite-50 text-graphite-600 border border-dashed border-graphite-300',
            self::Contingencia => 'bg-ember-100 text-ember-800',
        };
    }

    /** Estado terminal não aceita mais transmissão nem novo evento de correção. */
    public function terminal(): bool
    {
        return in_array($this, [self::Denegada, self::Cancelada, self::Inutilizada], true);
    }

    public function is(self $outro): bool
    {
        return $this === $outro;
    }

    /** Rejeitada volta para o rascunho: o operador corrige e retransmite. */
    public function recuperavel(): bool
    {
        return $this === self::Rejeitada;
    }
}
