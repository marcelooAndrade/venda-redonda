<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin pessoal
    |--------------------------------------------------------------------------
    |
    | Para onde vão os leads de quem se cadastra no produto. Fica vazio em
    | desenvolvimento e em teste: sem URL nem token, nada sai pela rede.
    |
    */
    'admin_pessoal' => [
        'url' => env('ADMIN_PESSOAL_URL'),
        'token' => env('ADMIN_PESSOAL_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta (pixel e Conversions API)
    |--------------------------------------------------------------------------
    |
    | Mesmo pixel e mesma conta de anúncios do projeto Marcelo Andrade. Fica
    | vazio em desenvolvimento e em teste: sem pixel nem token, nada sai pela
    | rede, e o pixel do navegador não é carregado.
    |
    */
    'meta' => [
        'pixel_id' => env('META_PIXEL_ID'),
        'access_token' => env('META_CAPI_ACCESS_TOKEN'),
    ],

];
