<?php

namespace App\Services\Integrations;

use App\Support\Documento;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Consulta de CNPJ na ReceitaWS.
 *
 * A ReceitaWS tem duas APIs, e a spec oficial as separa pela URL. A Pública
 * é `/v1/cnpj/{cnpj}`: sem autenticação, 3 consultas por minuto por IP, e
 * só responde CNPJ que já está no banco dela; fora disso devolve 504. A
 * Comercial é `/v1/cnpj/{cnpj}/days/{dias}`: exige Bearer, consulta a
 * Receita Federal em tempo real quando o dado em cache é mais velho que
 * `dias`, e devolve 402 quando a cota do plano acaba. Com o token
 * configurado a consulta vai para a Comercial; sem ele, para a Pública. O
 * token na URL pública não compraria nada.
 */
class ReceitaWsService
{
    private const URL = 'https://receitaws.com.br/v1/cnpj/';

    private const CACHE_DIAS = 7;

    /**
     * Pior caso do tempo de rede: `TIMEOUT_SEGUNDOS` vezes (1 + `REPETICOES`)
     * mais `REPETICOES` esperas de `ESPERA_MS`. Com os valores abaixo, isso
     * dá 8 + 0,5 + 8 = 16,5s, bem abaixo do limite de requisição de qualquer
     * plataforma comum. Antes era timeout de 20s com `retry(2, 1500)`, que
     * são duas tentativas no total: 20 + 1,5 + 20 = 41,5s, e foi o que
     * travou um cadastro em 13/09 com "This page has expired", porque a
     * plataforma corta a requisição bem antes disso.
     *
     * `Http::retry($n)` conta tentativas, não repetições. Por isso o código
     * passa `1 + REPETICOES`, para a constante dizer o que o nome diz. O
     * teste de tempo de espera conta as tentativas de verdade pelo fake.
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
        $token = (string) config('services.receitaws.token');
        $comercial = $token !== '';

        try {
            $resposta = Http::timeout(self::TIMEOUT_SEGUNDOS)
                ->retry(1 + self::REPETICOES, self::ESPERA_MS, fn (Throwable $e) => PoliticaDeRepeticao::valeRepetir($e), throw: false)
                ->when($comercial, fn ($http) => $http->withToken($token))
                ->get($this->url($cnpj, $comercial));
        } catch (Throwable $e) {
            Log::warning('ReceitaWS indisponível', ['cnpj' => $cnpj]);

            throw new RuntimeException('Não foi possível consultar a Receita agora. Preencha manualmente.');
        }

        if ($resposta->status() === 429) {
            Log::warning('ReceitaWS: limite por minuto da API Pública atingido', ['cnpj' => $cnpj]);

            throw new RuntimeException('Limite de consultas atingido, aguarde 1 minuto e tente de novo.');
        }

        if ($resposta->status() === 402) {
            Log::warning('ReceitaWS: cota do plano esgotada', ['cnpj' => $cnpj]);

            throw new RuntimeException('A cota do plano da ReceitaWS acabou. Preencha manualmente e avise quem administra o sistema.');
        }

        if ($resposta->status() === 504) {
            if (! $comercial) {
                Log::warning('ReceitaWS: CNPJ fora da base gratuita', ['cnpj' => $cnpj]);

                throw new RuntimeException('Este CNPJ ainda não está na base gratuita da ReceitaWS. Preencha manualmente.');
            }

            throw new RuntimeException('A Receita Federal não respondeu a tempo. Tente de novo em instantes ou preencha manualmente.');
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

    private function url(string $cnpj, bool $comercial): string
    {
        if (! $comercial) {
            return self::URL.$cnpj;
        }

        $dias = (int) config('services.receitaws.dias');

        return self::URL.$cnpj.'/days/'.$dias;
    }
}
