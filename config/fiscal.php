<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Responsável técnico (infRespTec)
    |--------------------------------------------------------------------------
    |
    | Identifica a software house perante a SEFAZ. Fica em configuração global
    | porque é o mesmo em todos os emitentes atendidos por esta instalação.
    |
    | Obrigatório antes de virar qualquer emitente para produção: a validação
    | da virada confere estes campos.
    |
    */
    'responsavel_tecnico' => [
        'cnpj' => env('FISCAL_RESP_TEC_CNPJ'),
        'contato' => env('FISCAL_RESP_TEC_CONTATO'),
        'email' => env('FISCAL_RESP_TEC_EMAIL'),
        'telefone' => env('FISCAL_RESP_TEC_TELEFONE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    */
    'versao_nfe' => '4.00',
    'modelo' => 55,

];
