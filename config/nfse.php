<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SIGISS de Araras
    |--------------------------------------------------------------------------
    |
    | Web service da prefeitura de Araras/SP. Homologação e produção têm hosts
    | diferentes. As URLs ficam aqui, e não no código, para uma mudança de
    | endereço não exigir build.
    |
    | A cadeia de certificados existe porque o SIGISS omite o intermediário
    | Sectigo no handshake TLS, e sem ele a conexão falha na verificação.
    | É certificado público de autoridade certificadora, não segredo.
    |
    */
    'sigiss' => [
        'homologacao_url' => env('SIGISS_HOMOLOGACAO_URL', 'https://wshml.sigissweb.com/rest'),
        'producao_url' => env('SIGISS_PRODUCAO_URL', 'https://wsararas.sigissweb.com/rest'),
        'ca_bundle' => resource_path('certificates/sigiss-sectigo-ca.pem'),
    ],

];
