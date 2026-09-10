<?php

namespace App\Services\Import;

use Carbon\CarbonInterface;

/**
 * Uma NF-e lida de XML, ainda sem vínculo com o banco.
 *
 * Deliberadamente sem Eloquent: o parser é puro, então dá para testá-lo com
 * XML real sem tocar em banco, e ele não conhece o domínio de quem o usa.
 */
readonly class NotaImportada
{
    /**
     * @param  array<string, mixed>  $emitente
     * @param  array<string, mixed>  $destinatario
     * @param  array<int, array<string, mixed>>  $itens
     * @param  array<string, float>  $totais
     */
    public function __construct(
        public string $chave,
        public string $numero,
        public string $serie,
        public CarbonInterface $dataEmissao,
        public string $naturezaOperacao,
        public array $emitente,
        public array $destinatario,
        public array $itens,
        public array $totais,
        public ?string $protocolo,
        public ?string $cStat,
        public string $xml,
    ) {}
}
