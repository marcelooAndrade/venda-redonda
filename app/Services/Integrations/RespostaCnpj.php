<?php

namespace App\Services\Integrations;

/**
 * A ReceitaWS não devolve inscrição estadual nem código IBGE. A IE fica para
 * preenchimento manual e o IBGE vem do ViaCEP, disparado com o CEP retornado.
 */
readonly class RespostaCnpj
{
    public function __construct(
        public string $razaoSocial,
        public ?string $nomeFantasia,
        public string $situacao,
        public bool $ativa,
        public ?string $logradouro,
        public ?string $numero,
        public ?string $complemento,
        public ?string $bairro,
        public ?string $municipio,
        public ?string $uf,
        public ?string $cep,
        public ?string $telefone,
        public ?string $email,
    ) {}
}
