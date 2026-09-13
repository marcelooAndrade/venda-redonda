<?php

namespace App\Services\Integrations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ViaCepService
{
    private const URL = 'https://viacep.com.br/ws/';

    private const CACHE_DIAS = 30;

    /**
     * Mesma régua do ReceitaWsService, e pela mesma razão: pior caso de
     * 8 + 0,5 + 8 = 16,5s, contra os 15 + 1 + 15 = 31s de antes, que eram
     * `retry(2, 1000)`, duas tentativas no total. Ver o comentário lá para
     * a origem do número e para o que `Http::retry()` conta.
     */
    public const TIMEOUT_SEGUNDOS = 8;

    public const REPETICOES = 1;

    public const ESPERA_MS = 500;

    public function consultar(string $cep): RespostaCep
    {
        $cep = preg_replace('/\D/', '', $cep) ?? '';

        if (strlen($cep) !== 8) {
            throw new RuntimeException('CEP precisa ter 8 dígitos.');
        }

        // Guarda array, não o objeto: ver a nota no ReceitaWsService. O
        // padrão do Laravel recusa desserializar classe do cache, e a segunda
        // consulta do mesmo CEP voltava como classe incompleta.
        $dados = Cache::remember(
            "viacep:{$cep}",
            now()->addDays(self::CACHE_DIAS),
            fn (): array => $this->buscar($cep)->toArray(),
        );

        return RespostaCep::fromArray($dados);
    }

    private function buscar(string $cep): RespostaCep
    {
        try {
            $resposta = Http::timeout(self::TIMEOUT_SEGUNDOS)
                ->retry(1 + self::REPETICOES, self::ESPERA_MS, fn (Throwable $e) => PoliticaDeRepeticao::valeRepetir($e), throw: false)
                ->get(self::URL."{$cep}/json/");
        } catch (Throwable) {
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
