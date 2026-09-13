<?php

namespace App\Services\Integrations;

use App\Support\Documento;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ReceitaWsService
{
    private const URL = 'https://receitaws.com.br/v1/cnpj/';

    private const CACHE_DIAS = 7;

    /**
     * Pior caso do tempo de rede: `TIMEOUT_SEGUNDOS` vezes (1 + `REPETICOES`)
     * mais `REPETICOES` esperas de `ESPERA_MS`. Com os valores abaixo, isso
     * dá 8 + 0,5 + 8 = 16,5s, bem abaixo do limite de requisição de qualquer
     * plataforma comum. Antes era timeout de 20s com 2 repetições e 1,5s de
     * espera: 20 + 1,5 + 20 + 1,5 + 20 = 64s, e foi o que travou um cadastro
     * em 13/09 com "This page has expired", porque a plataforma corta a
     * requisição bem antes disso. Constantes, e não números soltos, para o
     * teste de tempo de espera conferir contra elas, não reproduzi-las.
     */
    public const TIMEOUT_SEGUNDOS = 8;

    public const REPETICOES = 1;

    public const ESPERA_MS = 500;

    public function consultar(string $cnpj): RespostaCnpj
    {
        $cnpj = Documento::normalizarCnpj($cnpj);

        // Valida antes de sair da máquina: o plano gratuito tem limite por
        // minuto, e não faz sentido gastar consulta com documento inválido.
        if (! Documento::cnpjValido($cnpj)) {
            throw new RuntimeException('CNPJ inválido. Confira os dígitos antes de consultar.');
        }

        return Cache::remember(
            "receitaws:{$cnpj}",
            now()->addDays(self::CACHE_DIAS),
            fn (): RespostaCnpj => $this->buscar($cnpj),
        );
    }

    private function buscar(string $cnpj): RespostaCnpj
    {
        $token = config('services.receitaws.token');

        try {
            $resposta = Http::timeout(self::TIMEOUT_SEGUNDOS)
                ->retry(self::REPETICOES, self::ESPERA_MS, throw: false)
                ->when($token, fn ($http) => $http->withToken($token))
                ->get(self::URL.$cnpj);
        } catch (\Throwable $e) {
            Log::warning('ReceitaWS indisponível', ['cnpj' => $cnpj]);

            throw new RuntimeException('Não foi possível consultar a Receita agora. Preencha manualmente.');
        }

        if ($resposta->status() === 429) {
            throw new RuntimeException('Limite de consultas atingido, aguarde 1 minuto e tente de novo.');
        }

        if ($resposta->failed()) {
            throw new RuntimeException('A consulta à Receita falhou. Preencha manualmente.');
        }

        $dados = $resposta->json() ?? [];

        if (($dados['status'] ?? null) === 'ERROR') {
            throw new RuntimeException($dados['message'] ?? 'A Receita recusou a consulta.');
        }

        $situacao = (string) ($dados['situacao'] ?? '');

        return new RespostaCnpj(
            razaoSocial: (string) ($dados['nome'] ?? ''),
            nomeFantasia: ($dados['fantasia'] ?? null) ?: null,
            situacao: $situacao,
            ativa: strcasecmp($situacao, 'ATIVA') === 0,
            logradouro: ($dados['logradouro'] ?? null) ?: null,
            numero: ($dados['numero'] ?? null) ?: null,
            complemento: ($dados['complemento'] ?? null) ?: null,
            bairro: ($dados['bairro'] ?? null) ?: null,
            municipio: ($dados['municipio'] ?? null) ?: null,
            uf: ($dados['uf'] ?? null) ?: null,
            cep: ($cep = preg_replace('/\D/', '', (string) ($dados['cep'] ?? ''))) !== '' ? $cep : null,
            telefone: ($dados['telefone'] ?? null) ?: null,
            email: ($dados['email'] ?? null) ?: null,
        );
    }
}
