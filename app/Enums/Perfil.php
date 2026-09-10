<?php

namespace App\Enums;

/**
 * Perfis de acesso. O vínculo é por emitente, via teams do spatie/laravel-permission.
 */
enum Perfil: string
{
    case Administrador = 'Administrador';
    case Faturamento = 'Faturamento';
    case Estoque = 'Estoque';
    case Contador = 'Contador';
    case Consulta = 'Consulta';

    public function descricao(): string
    {
        return match ($this) {
            self::Administrador => 'Acesso total, incluindo certificado, usuários e virada para produção.',
            self::Faturamento => 'Emite, cancela e corrige notas. Gerencia destinatários.',
            self::Estoque => 'Movimenta estoque, gerencia produtos e processa importações.',
            self::Contador => 'Escreve a regra fiscal e exporta o pacote da contabilidade. Não emite nem cancela nota.',
            self::Consulta => 'Somente leitura.',
        };
    }
}
