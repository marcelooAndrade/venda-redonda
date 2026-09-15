<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domínio do Nodo
    |--------------------------------------------------------------------------
    |
    | Produto à parte do sistema fiscal, com domínio próprio. Sem valor, as
    | rotas deste módulo não respondem em nenhum host (ver
    | routes/api_plataforma.php).
    |
    */
    'dominio' => env('API_DOMINIO', 'nodo.dev.br'),

];
