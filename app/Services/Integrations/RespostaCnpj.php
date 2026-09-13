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

    /**
     * Ida e volta por array, para o cache guardar dados simples em vez do
     * objeto. O padrão do Laravel 13, `cache.serializable_classes => false`,
     * recusa desserializar qualquer classe do cache, o que virava este DTO em
     * `__PHP_Incomplete_Class` na segunda consulta do mesmo CNPJ. Guardando
     * array, a leitura não depende de desserializar classe nenhuma.
     *
     * @return array<string, string|bool|null>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param  array<string, string|bool|null>  $dados
     */
    public static function fromArray(array $dados): self
    {
        return new self(...$dados);
    }
}
