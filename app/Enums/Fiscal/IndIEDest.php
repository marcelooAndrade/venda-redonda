<?php

namespace App\Enums\Fiscal;

/**
 * indIEDest: indicador da Inscrição Estadual do destinatário.
 *
 * A SEFAZ cruza este campo com a IE informada. Contribuinte sem IE, ou
 * isento com IE preenchida, resulta em rejeição.
 */
enum IndIEDest: string
{
    case Contribuinte = '1';
    case Isento = '2';
    case NaoContribuinte = '9';

    public function rotulo(): string
    {
        return match ($this) {
            self::Contribuinte => 'Contribuinte de ICMS',
            self::Isento => 'Contribuinte isento de Inscrição Estadual',
            self::NaoContribuinte => 'Não contribuinte',
        };
    }

    public function exigeInscricaoEstadual(): bool
    {
        return $this === self::Contribuinte;
    }
}
