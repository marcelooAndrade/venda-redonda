<?php

namespace App\Services\Nfse;

/**
 * Resposta do provedor, já interpretada.
 *
 * `sucesso` diz se o provedor aceitou. Na emissão, aceitar significa ter
 * numerado a NFS-e. `bruto` é o corpo devolvido, guardado em disco pelo
 * emissor mesmo na rejeição: é a única prova do que o provedor disse.
 */
readonly class RespostaNfse
{
    public function __construct(
        public bool $sucesso,
        public ?string $numero = null,
        public ?string $serie = null,
        public ?string $codigoVerificacao = null,
        public ?string $motivo = null,
        public ?string $bruto = null,
    ) {}
}
