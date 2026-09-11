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
    'dominio' => env('PRODUTO_DOMINIO', 'vendaredonda.com.br'),

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

];
