<?php

namespace App\Enums\Fiscal;

/**
 * Tipos de movimento do razão de estoque.
 *
 * Movimento nunca é editado nem apagado: correção é sempre um movimento novo,
 * de sinal contrário. É o que preserva o Kardex como registro fiel.
 */
enum TipoMovimentoEstoque: string
{
    case Entrada = 'entrada';
    case Saida = 'saida';
    case Ajuste = 'ajuste';
    case Inventario = 'inventario';
    case Estorno = 'estorno';
    case Devolucao = 'devolucao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
            self::Ajuste => 'Ajuste manual',
            self::Inventario => 'Inventário',
            self::Estorno => 'Estorno',
            self::Devolucao => 'Devolução',
        };
    }

    /** Movimento que soma ao saldo. */
    public function positivo(): bool
    {
        return in_array($this, [self::Entrada, self::Estorno, self::Devolucao], true);
    }

    /** Só entrada com custo conhecido recalcula a média ponderada. */
    public function afetaCustoMedio(): bool
    {
        return in_array($this, [self::Entrada, self::Devolucao], true);
    }

    public function classesBadge(): string
    {
        return match ($this) {
            self::Entrada, self::Devolucao => 'bg-success-100 text-success-800',
            self::Saida => 'bg-danger-100 text-danger-800',
            self::Estorno => 'bg-ember-100 text-ember-800',
            self::Ajuste, self::Inventario => 'bg-steel-100 text-steel-800',
        };
    }
}
