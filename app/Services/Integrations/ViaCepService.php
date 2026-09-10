<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ViaCepService
{
    private const URL = 'https://viacep.com.br/ws/';

    private const CACHE_DIAS = 30;

    public function consultar(string $cep): RespostaCep
    {
        $cep = preg_replace('/\D/', '', $cep) ?? '';

        if (strlen($cep) !== 8) {
            throw new RuntimeException('CEP precisa ter 8 dígitos.');
        }

        return Cache::remember(
            "viacep:{$cep}",
            now()->addDays(self::CACHE_DIAS),
            fn (): RespostaCep => $this->buscar($cep),
        );
    }

    private function buscar(string $cep): RespostaCep
    {
        try {
            $resposta = Http::timeout(15)->retry(2, 1000, throw: false)->get(self::URL."{$cep}/json/");
        } catch (\Throwable) {
            throw new RuntimeException('Não foi possível consultar o CEP agora. Preencha manualmente.');
        }

        if ($resposta->failed()) {
            throw new RuntimeException('A consulta de CEP falhou. Preencha manualmente.');
        }

        $dados = $resposta->json() ?? [];

        // O ViaCEP devolve 200 com {"erro": true} para CEP inexistente.
        if (filter_var($dados['erro'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('CEP não encontrado. Preencha o endereço manualmente e escolha o município pela tabela IBGE.');
        }

        return new RespostaCep(
            logradouro: ($dados['logradouro'] ?? null) ?: null,
            bairro: ($dados['bairro'] ?? null) ?: null,
            municipio: ($dados['localidade'] ?? null) ?: null,
            uf: ($dados['uf'] ?? null) ?: null,
            codigoIbge: ($dados['ibge'] ?? null) ?: null,
        );
    }
}
