<?php

use App\Support\Pix;

/**
 * Casos portados de `src/lib/pix.test.ts` do projeto Marcelo Andrade, onde este
 * gerador está em produção. Se o payload daqui divergir do de lá, um dos dois
 * está errado, e o de lá já foi lido por banco.
 */
it('calcula CRC16 CCITT-FALSE', function () {
    expect(Pix::crc16('123456789'))->toBe('29B1');
});

it('gera payload estatico com valor, txid e CRC valido', function () {
    $payload = Pix::payload(
        chave: 'financeiro@example.com',
        nomeRecebedor: 'Marcelo Andrade',
        cidadeRecebedor: 'Araras',
        centavos: 125050,
        identificador: 'FATURA001P2',
    );

    expect($payload)->toContain('0014BR.GOV.BCB.PIX')
        ->and($payload)->toContain('54071250.50')
        ->and($payload)->toContain('5915MARCELO ANDRADE')
        ->and(substr($payload, -8, 4))->toBe('6304')
        ->and(substr($payload, -4))->toBe(Pix::crc16(substr($payload, 0, -4)));
});

it('gera identificador estavel por fatura e parcela', function () {
    expect(Pix::identificador('12345678-1234-4234-9234-123456789abc', 2))
        ->toBe('123456781234423492002');
});

it('tira acento e caractere que o BR Code nao aceita', function () {
    $payload = Pix::payload(
        chave: 'chave@exemplo.com',
        nomeRecebedor: 'Distribuição Rio Claro Ltda.',
        cidadeRecebedor: 'São Paulo',
        centavos: 1000,
        identificador: 'A1',
    );

    expect($payload)->toContain('DISTRIBUICAO RIO CLARO')
        ->and($payload)->toContain('SAO PAULO');
});

it('recusa o que o banco recusaria', function (array $args, string $erro) {
    expect(fn () => Pix::payload(...$args))->toThrow(InvalidArgumentException::class, $erro);
})->with([
    'sem chave' => [['chave' => '', 'nomeRecebedor' => 'X Y', 'cidadeRecebedor' => 'Araras', 'centavos' => 100, 'identificador' => 'A'], 'Chave Pix inválida.'],
    'sem nome' => [['chave' => 'k@e.com', 'nomeRecebedor' => '', 'cidadeRecebedor' => 'Araras', 'centavos' => 100, 'identificador' => 'A'], 'Nome e cidade do recebedor são obrigatórios.'],
    'valor zero' => [['chave' => 'k@e.com', 'nomeRecebedor' => 'X Y', 'cidadeRecebedor' => 'Araras', 'centavos' => 0, 'identificador' => 'A'], 'Valor Pix inválido.'],
]);
