<?php

namespace App\Services\Nfse;

use App\Enums\Fiscal\Ambiente;
use App\Models\Emitente;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;

/**
 * Web service do SIGISS de Araras, portado de `SigissClient` do Oficina
 * Fluxo, onde rodou em produção.
 *
 * O protocolo é simples e antigo: login por CNPJ e senha devolvendo um token,
 * XML em ISO-8859-1 num POST, e GETs com o número na URL para cancelar e
 * imprimir. As respostas não têm esquema: a leitura é por nome de tag, e o
 * cancelamento é lido por ausência de "erro" no texto. Ver DF-025.
 */
class SigissGateway implements GatewayNfse
{
    public function emitir(Emitente $emitente, Ambiente $ambiente, string $xml): RespostaNfse
    {
        $token = $this->login($emitente, $ambiente);

        // O SIGISS só lê ISO-8859-1. A declaração muda junto com o conteúdo,
        // senão o parser de lá lê os acentos errados.
        $corpo = mb_convert_encoding(
            str_replace('encoding="UTF-8"', 'encoding="ISO-8859-1"', $xml),
            'ISO-8859-1',
            'UTF-8',
        );

        $resposta = $this->chamar(
            fn (): Response => $this->pedido(45)
                ->withHeaders(['AUTHORIZATION' => $token])
                ->withBody($corpo, 'application/xml; charset=ISO-8859-1')
                ->post($this->url($ambiente, 'nfes')),
            'emitir a NFS-e',
        );

        return self::lerEmissao($this->texto($resposta));
    }

    public function cancelar(Emitente $emitente, Ambiente $ambiente, string $numero, string $serie, string $motivo): RespostaNfse
    {
        $token = $this->login($emitente, $ambiente);

        $caminho = sprintf(
            'nfes/cancela/%s/serie/%s/motivo/%s',
            rawurlencode($numero),
            rawurlencode($serie),
            rawurlencode($motivo),
        );

        $resposta = $this->chamar(
            fn (): Response => $this->pedido(45)
                ->withHeaders(['AUTHORIZATION' => $token])
                ->get($this->url($ambiente, $caminho)),
            'cancelar a NFS-e',
        );

        return self::lerCancelamento($this->texto($resposta));
    }

    public function pdf(Emitente $emitente, Ambiente $ambiente, string $numero, string $serie): string
    {
        $token = $this->login($emitente, $ambiente);

        $caminho = sprintf('nfes/nfimpressa/%s/serie/%s', rawurlencode($numero), rawurlencode($serie));

        $resposta = $this->chamar(
            fn (): Response => $this->pedido(45)
                ->withHeaders(['AUTHORIZATION' => $token])
                ->get($this->url($ambiente, $caminho)),
            'obter o PDF da NFS-e',
        );

        $bytes = $resposta->body();

        if (! str_starts_with($bytes, '%PDF')) {
            throw new FalhaDeComunicacaoNfse('O SIGISS não devolveu um PDF válido para esta NFS-e.');
        }

        return $bytes;
    }

    /**
     * Lê a resposta da emissão.
     *
     * Autorizada é ter `numero_nf` numérico maior que zero. Sem isso é
     * rejeição, e o motivo vem da primeira tag conhecida ou do texto sem tags.
     */
    public static function lerEmissao(string $texto): RespostaNfse
    {
        $xml = self::xml($texto);

        if ($xml === null) {
            return new RespostaNfse(false, motivo: self::textoPuro($texto), bruto: $texto);
        }

        $numero = self::primeiro($xml, ['numero_nf', 'numero_nfe', 'numeronf']);
        $motivo = self::primeiro($xml, ['motivo_rejeicao', 'mensagem', 'message', 'erro', 'error']);

        if ($numero === null || ! ctype_digit($numero) || (int) $numero <= 0) {
            return new RespostaNfse(false, motivo: $motivo ?? self::textoPuro($texto), bruto: $texto);
        }

        return new RespostaNfse(
            sucesso: true,
            numero: $numero,
            serie: self::primeiro($xml, ['serie', 'serie_nf']),
            codigoVerificacao: self::primeiro($xml, ['codigo', 'codigo_verificacao']),
            bruto: $texto,
        );
    }

    /**
     * O SIGISS responde ao cancelamento com texto livre. A origem lia sucesso
     * como "não contém erro nem recusa", e é o que se mantém.
     */
    public static function lerCancelamento(string $texto): RespostaNfse
    {
        $normalizado = Str::lower(Str::ascii(strip_tags($texto)));
        $recusou = str_contains($normalizado, 'erro') || str_contains($normalizado, 'recus');

        return new RespostaNfse(! $recusou, motivo: $recusou ? self::textoPuro($texto) : null, bruto: $texto);
    }

    private function login(Emitente $emitente, Ambiente $ambiente): string
    {
        $senha = $emitente->nfse?->senha($ambiente);

        if (blank($senha)) {
            throw new RuntimeException('Senha do SIGISS não cadastrada para o ambiente de '.$ambiente->rotulo().'.');
        }

        $resposta = $this->chamar(
            fn (): Response => $this->pedido(30)
                ->asJson()
                ->post($this->url($ambiente, 'login'), ['login' => $emitente->cnpj, 'senha' => $senha]),
            'autenticar no SIGISS',
        );

        // O token pode vir em JSON, como texto entre aspas ou como texto puro.
        $json = $resposta->json();
        $token = is_array($json) ? ($json['token'] ?? $json['access_token'] ?? $json['authorization'] ?? null) : null;
        $token = is_string($token) ? trim($token) : trim($resposta->body(), " \t\n\r\0\x0B\"");

        if ($token === '') {
            throw new FalhaDeComunicacaoNfse('O SIGISS autenticou, mas não devolveu o token de acesso.');
        }

        return $token;
    }

    /** @param Closure(): Response $chamada */
    private function chamar(Closure $chamada, string $acao): Response
    {
        try {
            $resposta = $chamada();
        } catch (ConnectionException $e) {
            throw new FalhaDeComunicacaoNfse("Falha de comunicação com o SIGISS ao {$acao}: {$e->getMessage()}", previous: $e);
        }

        if (! $resposta->successful()) {
            $detalhe = trim((string) preg_replace('/\s+/', ' ', strip_tags($resposta->body())));

            throw new FalhaDeComunicacaoNfse(
                "Não foi possível {$acao} no SIGISS (HTTP {$resposta->status()})"
                .($detalhe !== '' ? ': '.mb_substr($detalhe, 0, 500) : '.'),
            );
        }

        return $resposta;
    }

    private function pedido(int $timeout): PendingRequest
    {
        $cadeia = (string) config('nfse.sigiss.ca_bundle');

        if (! is_readable($cadeia)) {
            throw new RuntimeException("A cadeia de certificados do SIGISS não está em {$cadeia}.");
        }

        return Http::withOptions(['verify' => $cadeia])
            ->connectTimeout(10)
            ->timeout($timeout)
            ->accept('*/*');
    }

    /** Corpo em UTF-8, com a declaração corrigida, seja qual for a codificação que veio. */
    private function texto(Response $resposta): string
    {
        $corpo = $resposta->body();

        if (! mb_check_encoding($corpo, 'UTF-8')) {
            $corpo = mb_convert_encoding($corpo, 'UTF-8', 'Windows-1252');
        }

        return (string) preg_replace('/encoding=["\'](?:ISO-8859-1|windows-1252)["\']/i', 'encoding="UTF-8"', $corpo);
    }

    private function url(Ambiente $ambiente, string $caminho): string
    {
        $base = (string) config($ambiente === Ambiente::Producao ? 'nfse.sigiss.producao_url' : 'nfse.sigiss.homologacao_url');

        return rtrim($base, '/').'/'.ltrim($caminho, '/');
    }

    private static function xml(string $texto): ?SimpleXMLElement
    {
        $anterior = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($texto);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return $xml instanceof SimpleXMLElement ? $xml : null;
    }

    /** @param array<int, string> $nomes */
    private static function primeiro(SimpleXMLElement $xml, array $nomes): ?string
    {
        foreach ($nomes as $nome) {
            $valor = trim((string) ($xml->{$nome} ?? ''));

            if ($valor !== '') {
                return mb_substr($valor, 0, 1000);
            }
        }

        return null;
    }

    private static function textoPuro(string $texto): string
    {
        $puro = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return $puro !== '' ? mb_substr($puro, 0, 1000) : 'Resposta vazia ou inválida do SIGISS.';
    }
}
