<?php

namespace App\Services\Integrations;

readonly class RespostaCep
{
    public function __construct(
        public ?string $logradouro,
        public ?string $bairro,
        public ?string $municipio,
        public ?string $uf,
        /** cMun da NF-e. Sem ele a nota não é autorizada. */
        public ?string $codigoIbge,
    ) {}
}
