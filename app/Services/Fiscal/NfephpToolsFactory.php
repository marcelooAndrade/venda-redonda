<?php

namespace App\Services\Fiscal;

use App\Models\Emitente;
use NFePHP\NFe\Tools;

/**
 * Monta o `Tools` da sped-nfe para um emitente.
 *
 * Reaproveitado do `app-transm`, com duas diferenças: aqui o `Tools` é para
 * emitir, não só para consultar distribuição, e o `schemes` vem da
 * configuração em vez de fixo no código, porque é ele que decide se os
 * grupos da Reforma Tributária existem. Ver DF-003.
 */
class NfephpToolsFactory
{
    public function __construct(
        private readonly CertificateService $certificados,
    ) {}

    public function para(Emitente $emitente): Tools
    {
        $tools = new Tools(
            json_encode($this->config($emitente), JSON_THROW_ON_ERROR),
            $this->certificados->certificado($emitente),
        );

        $tools->model(config('fiscal.modelo', 55));

        return $tools;
    }

    /** @return array<string, mixed> */
    private function config(Emitente $emitente): array
    {
        return [
            'atualizacao' => now()->format('Y-m-d H:i:s'),
            'tpAmb' => $emitente->ambiente->tpAmb(),
            'razaosocial' => $emitente->razao_social,
            'cnpj' => $emitente->cnpj,
            'siglaUF' => $emitente->uf,
            'schemes' => config('fiscal.schema', 'PL_010_V1.30'),
            'versao' => config('fiscal.versao_nfe', '4.00'),
            'tokenIBPT' => '',
            'CSC' => '',
            'CSCid' => '',
            'proxyConf' => [
                'proxyIp' => '', 'proxyPort' => '', 'proxyUser' => '', 'proxyPass' => '',
            ],
        ];
    }
}
