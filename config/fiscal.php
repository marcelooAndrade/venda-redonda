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

    /*
    |--------------------------------------------------------------------------
    | Pacote de leiaute (schema)
    |--------------------------------------------------------------------------
    |
    | O `Make` da sped-nfe assume PL_009 quando não recebe schema, e PL_009 é
    | anterior à Reforma Tributária: os grupos IBS, CBS, IS e DFeReferenciado
    | simplesmente não são renderizados, sem erro nenhum.
    |
    | Confirmar contra a NT vigente antes de emitir em produção. Ver DF-003.
    |
    */
    'schema' => env('FISCAL_SCHEMA', 'PL_010_V1.30'),

];
