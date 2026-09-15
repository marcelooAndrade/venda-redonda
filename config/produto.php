<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domínio do produto
    |--------------------------------------------------------------------------
    |
    | Decide duas coisas a partir do host: se a requisição é a apresentação ou
    | a aplicação, e quais hosts não pertencem a tenant nenhum.
    |
    | Fica em configuração porque homologação e produção respondem em domínios
    | diferentes, e cravar no código exigiria builds distintos.
    |
    */
    'dominio' => env('PRODUTO_DOMINIO', 'emitiragora.com.br'),

    /*
    |--------------------------------------------------------------------------
    | Slugs reservados
    |--------------------------------------------------------------------------
    |
    | O primeiro rótulo do host vira slug de tenant. Sem esta lista, um tenant
    | de slug `app` passaria a receber `app.<dominio>`, que é o host de login
    | do próprio produto. O model recusa estes valores.
    |
    */
    'slugs_reservados' => ['app', 'www', 'admin', 'api', 'mail', 'painel', 'suporte', 'status'],

    /*
    |--------------------------------------------------------------------------
    | Tenant administrativo
    |--------------------------------------------------------------------------
    |
    | Slug do tenant "Marcelo Andrade" (a empresa dona do produto), para a
    | tela Clientes: mostra os destinatários marcados como cliente dessa
    | empresa, não de qualquer tenant. Vazio por padrão — sem valor, a tela
    | avisa que falta configurar, em vez de adivinhar qual tenant é.
    |
    */
    'tenant_administrativo' => env('TENANT_ADMINISTRATIVO_SLUG'),

];
