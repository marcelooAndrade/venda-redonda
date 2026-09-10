<?php

namespace App\Enums\Fiscal;

/**
 * Âmbito da operação, que determina qual regra tributária se aplica.
 * Deriva da comparação entre a UF do emitente e a do destinatário.
 */
enum AmbitoOperacao: string
{
    case Interna = 'interna';
    case Interestadual = 'interestadual';
    case Exterior = 'exterior';

    public function rotulo(): string
    {
        return match ($this) {
            self::Interna => 'Dentro do estado',
            self::Interestadual => 'Fora do estado',
            self::Exterior => 'Exterior',
        };
    }

    /** idDest da NF-e: 1 interna, 2 interestadual, 3 exterior. */
    public function idDest(): string
    {
        return match ($this) {
            self::Interna => '1',
            self::Interestadual => '2',
            self::Exterior => '3',
        };
    }

    public static function paraUf(string $ufEmitente, string $ufDestinatario): self
    {
        if (strtoupper($ufDestinatario) === 'EX') {
            return self::Exterior;
        }

        return strtoupper($ufEmitente) === strtoupper($ufDestinatario)
            ? self::Interna
            : self::Interestadual;
    }
}
