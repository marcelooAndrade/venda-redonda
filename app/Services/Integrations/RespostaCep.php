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

    /**
     * Ida e volta por array, para o cache guardar dados simples em vez do
     * objeto. Ver a mesma nota em RespostaCnpj: o padrão do Laravel recusa
     * desserializar classe do cache, e a segunda consulta do mesmo CEP
     * quebrava com classe incompleta.
     *
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, string|null>  $dados
     */
    public static function fromArray(array $dados): self
    {
        return new self(...$dados);
    }
}
