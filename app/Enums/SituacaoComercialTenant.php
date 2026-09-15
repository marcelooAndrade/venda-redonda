<?php

namespace App\Enums;

/**
 * Em que pé está a conversa comercial com uma empresa que se cadastrou no
 * produto. É o "Leads Venda Redonda" do projeto Marcelo Andrade, agora dentro
 * do painel de empresas. Não muda nada no acesso da empresa ao sistema.
 */
enum SituacaoComercialTenant: string
{
    case Novo = 'novo';
    case Contatado = 'contatado';
    case Cliente = 'cliente';
    case Descartado = 'descartado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Novo => 'Novo',
            self::Contatado => 'Contatado',
            self::Cliente => 'Cliente',
            self::Descartado => 'Descartado',
        };
    }
}
