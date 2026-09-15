<?php

namespace App\Enums;

/**
 * Módulo do Nodo que um cliente pode ter ativo. Cada um é pago em separado
 * (decisão de negócio, não imposta pelo código): o admin escolhe, por
 * cliente, quais módulos ele usa. Novo módulo entra como novo case, sem
 * mexer no modelo de dados nem na autenticação.
 */
enum ModuloApi: string
{
    case Whatsapp = 'whatsapp';

    public function rotulo(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
        };
    }
}
