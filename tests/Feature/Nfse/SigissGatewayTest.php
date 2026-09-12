<?php

use App\Enums\Fiscal\Ambiente;
use App\Services\Nfse\FalhaDeComunicacaoNfse;
use App\Services\Nfse\SigissGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->emitente = emitenteComNfse(['senha_producao' => 'segredo-prod']);
    $this->xml = '<?xml version="1.0" encoding="UTF-8"?><notafiscal><descricao>Gestão de mídia</descricao></notafiscal>';
});

it('autentica com cnpj e senha e emite em ISO-8859-1 com o token no cabecalho', function () {
    Http::fake([
        '*/rest/login' => Http::response('token-sigiss'),
        '*/rest/nfes' => Http::response('<?xml version="1.0"?><notafiscal><numero_nf>456</numero_nf><serie>NFE</serie><codigo>ABC123</codigo></notafiscal>'),
    ]);

    $resposta = app(SigissGateway::class)->emitir($this->emitente, Ambiente::Homologacao, $this->xml);

    expect($resposta->sucesso)->toBeTrue()
        ->and($resposta->numero)->toBe('456')
        ->and($resposta->serie)->toBe('NFE')
        ->and($resposta->codigoVerificacao)->toBe('ABC123')
        ->and($resposta->bruto)->toContain('<numero_nf>456</numero_nf>');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/rest/login')
        && $r['login'] === '11222333000181'
        && $r['senha'] === 'segredo-hml');

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/rest/nfes')
        && $r->hasHeader('AUTHORIZATION', 'token-sigiss')
        && str_contains($r->body(), 'encoding="ISO-8859-1"')
        && ! str_contains($r->body(), 'Gestão de mídia')
        && str_contains(mb_convert_encoding($r->body(), 'UTF-8', 'ISO-8859-1'), 'Gestão de mídia'));
});

it('le o token em json, em texto entre aspas e em texto puro', function (string $corpoLogin) {
    Http::fake([
        '*/rest/login' => Http::response($corpoLogin),
        '*/rest/nfes' => Http::response('<notafiscal><numero_nf>1</numero_nf></notafiscal>'),
    ]);

    app(SigissGateway::class)->emitir($this->emitente, Ambiente::Homologacao, $this->xml);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/rest/nfes') && $r->hasHeader('AUTHORIZATION', 'tk'));
})->with(['{"token":"tk"}', '{"access_token":"tk"}', '"tk"', 'tk']);

it('resposta sem numero e rejeicao, com o motivo que o sigiss deu', function () {
    Http::fake([
        '*/rest/login' => Http::response('tk'),
        '*/rest/nfes' => Http::response('<notafiscal><erro>RPS já utilizado</erro></notafiscal>'),
    ]);

    $resposta = app(SigissGateway::class)->emitir($this->emitente, Ambiente::Homologacao, $this->xml);

    expect($resposta->sucesso)->toBeFalse()
        ->and($resposta->motivo)->toBe('RPS já utilizado')
        ->and($resposta->numero)->toBeNull();
});

it('resposta que nao e xml vira rejeicao com o texto sem tags', function () {
    Http::fake([
        '*/rest/login' => Http::response('tk'),
        '*/rest/nfes' => Http::response('<html><body>Servi&ccedil;o indispon&iacute;vel</body>'),
    ]);

    $resposta = app(SigissGateway::class)->emitir($this->emitente, Ambiente::Homologacao, $this->xml);

    expect($resposta->sucesso)->toBeFalse()->and($resposta->motivo)->not->toBeEmpty();
});

it('resposta em latin1 e convertida para utf-8 antes de guardar', function () {
    $latin1 = mb_convert_encoding('<?xml version="1.0" encoding="ISO-8859-1"?><notafiscal><erro>Alíquota inválida</erro></notafiscal>', 'ISO-8859-1', 'UTF-8');
    Http::fake([
        '*/rest/login' => Http::response('tk'),
        '*/rest/nfes' => Http::response($latin1),
    ]);

    $resposta = app(SigissGateway::class)->emitir($this->emitente, Ambiente::Homologacao, $this->xml);

    expect($resposta->motivo)->toBe('Alíquota inválida')
        ->and($resposta->bruto)->toContain('encoding="UTF-8"')
        ->and(mb_check_encoding((string) $resposta->bruto, 'UTF-8'))->toBeTrue();
});

it('cancelamento aceito e recusado', function (string $corpo, bool $sucesso) {
    Http::fake([
        '*/rest/login' => Http::response('tk'),
        '*/rest/nfes/cancela/*' => Http::response($corpo),
    ]);

    $resposta = app(SigissGateway::class)->cancelar($this->emitente, Ambiente::Homologacao, '700', 'NFE', 'Serviço não prestado a pedido do cliente');

    expect($resposta->sucesso)->toBe($sucesso);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/rest/nfes/cancela/700/serie/NFE/motivo/'));
})->with([
    ['<resultado>Nota cancelada com sucesso</resultado>', true],
    ['<resultado>Erro: nota já cancelada</resultado>', false],
    ['<resultado>Cancelamento recusado pelo fisco</resultado>', false],
]);

it('devolve o pdf quando e pdf e falha quando nao e', function () {
    Http::fake([
        '*/rest/login' => Http::response('tk'),
        '*/rest/nfes/nfimpressa/700/*' => Http::response('%PDF-1.4 conteudo'),
        '*/rest/nfes/nfimpressa/701/*' => Http::response('<html>erro</html>'),
    ]);

    expect(app(SigissGateway::class)->pdf($this->emitente, Ambiente::Homologacao, '700', 'NFE'))->toStartWith('%PDF');

    expect(fn () => app(SigissGateway::class)->pdf($this->emitente, Ambiente::Homologacao, '701', 'NFE'))
        ->toThrow(FalhaDeComunicacaoNfse::class, 'PDF');
});

it('falha de conexao vira falha de comunicacao', function () {
    Http::fake([
        '*/rest/login' => Http::response('tk'),
        '*/rest/nfes' => Http::failedConnection(),
    ]);

    expect(fn () => app(SigissGateway::class)->emitir($this->emitente, Ambiente::Homologacao, $this->xml))
        ->toThrow(FalhaDeComunicacaoNfse::class, 'Falha de comunicação');
});

// Os stubs do Http::fake acumulam na mesma execução e o primeiro que casa
// vence, então este cenário não pode dividir o teste com o de cima.
it('http fora de 2xx vira falha de comunicacao com o status', function () {
    Http::fake([
        '*/rest/login' => Http::response('tk'),
        '*/rest/nfes' => Http::response('Internal Server Error', 500),
    ]);

    expect(fn () => app(SigissGateway::class)->emitir($this->emitente, Ambiente::Homologacao, $this->xml))
        ->toThrow(FalhaDeComunicacaoNfse::class, 'HTTP 500');
});

it('producao e homologacao batem em hosts diferentes, cada um com a propria senha', function () {
    Http::fake([
        '*/rest/login' => Http::response('tk'),
        '*/rest/nfes' => Http::response('<notafiscal><numero_nf>1</numero_nf></notafiscal>'),
    ]);

    app(SigissGateway::class)->emitir($this->emitente, Ambiente::Producao, $this->xml);

    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), config('nfse.sigiss.producao_url')) && str_ends_with($r->url(), '/login') && $r['senha'] === 'segredo-prod');
    Http::assertNotSent(fn (Request $r) => str_starts_with($r->url(), config('nfse.sigiss.homologacao_url')));
});

it('sem senha do ambiente nao encosta na rede', function () {
    Http::fake();
    $emitente = emitenteComNfse(['senha_homologacao' => null], ['cnpj' => '11444777000161']);

    expect(fn () => app(SigissGateway::class)->emitir($emitente, Ambiente::Homologacao, $this->xml))
        ->toThrow(RuntimeException::class, 'Senha do SIGISS');

    Http::assertNothingSent();
});

it('sem a cadeia de certificados no disco, erro claro antes de qualquer chamada', function () {
    Http::fake();
    config(['nfse.sigiss.ca_bundle' => '/nao/existe.pem']);

    expect(fn () => app(SigissGateway::class)->emitir($this->emitente, Ambiente::Homologacao, $this->xml))
        ->toThrow(RuntimeException::class, 'cadeia');

    Http::assertNothingSent();
});
