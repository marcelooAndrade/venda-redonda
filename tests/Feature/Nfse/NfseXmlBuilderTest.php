<?php

use App\Models\Emitente;
use App\Models\NotaServico;
use App\Models\Pessoa;
use App\Services\Nfse\NfseXmlBuilder;

function xmlDeExemplo(array $nota = [], array $tomador = []): string
{
    $emitente = new Emitente([
        'cnpj' => '51401590000148', 'municipio' => 'Araras', 'uf' => 'sp',
    ]);

    $pessoa = new Pessoa(array_merge([
        'tipo_pessoa' => 'J', 'documento' => '12345678000195',
        'razao_social' => 'Cliente & Associados', 'inscricao_estadual' => null, 'inscricao_municipal' => null,
        'logradouro' => 'Rua A', 'numero' => '20', 'complemento' => null, 'bairro' => 'Centro',
        'cep' => '13600100', 'municipio' => 'Araras', 'uf' => 'sp',
        'telefone' => '(19) 99999-9999', 'email' => 'fiscal@example.com',
    ], $tomador));

    $nota = new NotaServico(array_merge([
        'serie_rps' => '1', 'numero_rps' => 27,
        'codigo_servico' => '10.08.01', 'codigo_nbs' => '1.1406.20.00',
        'c_class_trib' => '000001', 'ind_op' => '050101',
        'aliquota_iss_bp' => 200, 'iss_retido' => false,
        'descricao' => 'Gestão de mídia & criação', 'valor_centavos' => 550000,
    ], $nota));

    return (new NfseXmlBuilder)->montar($emitente, $pessoa, $nota);
}

it('monta o leiaute de araras com os campos na ordem e o conteudo escapado', function () {
    $this->travelTo('2026-09-12 10:00:00');
    $xml = xmlDeExemplo();

    expect($xml)
        ->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<cnpj_cpf_prestador>51401590000148</cnpj_cpf_prestador>')
        ->toContain('<cnpj_cpf_destinatario>12345678000195</cnpj_cpf_destinatario>')
        ->toContain('<pessoa_destinatario>J</pessoa_destinatario>')
        ->toContain('<razao_social_destinatario>Cliente &amp; Associados</razao_social_destinatario>')
        ->toContain('<fone_destinatario>19999999999</fone_destinatario>')
        ->toContain('<uf_destinatario>SP</uf_destinatario>')
        ->toContain('<valor_nf>5500,00</valor_nf>')
        ->toContain('<valor_servico>5500,00</valor_servico>')
        ->toContain('<data_emissao>12/09/2026</data_emissao>')
        ->toContain('<descricao>Gestão de mídia &amp; criação</descricao>')
        ->toContain('<id_codigo_servico>10.08.01</id_codigo_servico>')
        ->toContain('<iss_retido>N</iss_retido>')
        ->toContain('<aliq_iss>2,00</aliq_iss>')
        ->toContain('<valor_iss>110,00</valor_iss>')
        ->toContain('<bc_pis>0,00</bc_pis>')
        ->toContain('<valor_inss>0,00</valor_inss>')
        ->toContain('<sistema_gerador>Venda Redonda</sistema_gerador>')
        ->toContain('<serie_rps>1</serie_rps>')
        ->toContain('<rps>27</rps>')
        ->toContain('<codigo_nbs>1.1406.20.00</codigo_nbs>')
        ->toContain('<cidade_local_prest>Araras</cidade_local_prest>')
        ->toContain('<uf_local_prest>SP</uf_local_prest>')
        ->toContain('<c_classtrib>000001</c_classtrib>')
        ->toContain('<ind_op>050101</ind_op>')
        ->toContain('<consumo_pessoal>0</consumo_pessoal>');

    // A ordem é a do leiaute que rodou em produção: prestador primeiro, ISS
    // antes dos federais, gerador e RPS depois.
    expect(strpos($xml, '<cnpj_cpf_prestador>'))->toBeLessThan(strpos($xml, '<valor_nf>'))
        ->and(strpos($xml, '<valor_iss>'))->toBeLessThan(strpos($xml, '<bc_pis>'))
        ->and(strpos($xml, '<valor_inss>'))->toBeLessThan(strpos($xml, '<sistema_gerador>'));
});

it('pessoa fisica sai como F e iss retido como S', function () {
    $xml = xmlDeExemplo(['iss_retido' => true], ['tipo_pessoa' => 'F', 'documento' => '12345678909']);

    expect($xml)
        ->toContain('<pessoa_destinatario>F</pessoa_destinatario>')
        ->toContain('<cnpj_cpf_destinatario>12345678909</cnpj_cpf_destinatario>')
        ->toContain('<iss_retido>S</iss_retido>');
});

it('campos vazios do tomador viram tags vazias, nao somem', function () {
    $xml = xmlDeExemplo([], ['complemento' => null, 'email' => null, 'telefone' => null]);

    expect($xml)
        ->toContain('<complemento_ende_destinatario></complemento_ende_destinatario>')
        ->toContain('<email_destinatario></email_destinatario>')
        ->toContain('<fone_destinatario></fone_destinatario>');
});

it('corta a descricao em mil caracteres', function () {
    $xml = xmlDeExemplo(['descricao' => str_repeat('a', 1200)]);

    preg_match('/<descricao>(.*)<\/descricao>/', $xml, $m);
    expect(mb_strlen($m[1]))->toBe(1000);
});

it('calcula o iss em centavos, meio para cima, e formata sem separador de milhar', function () {
    expect(NfseXmlBuilder::iss(550000, 200))->toBe(11000)
        ->and(NfseXmlBuilder::iss(100, 500))->toBe(5)
        ->and(NfseXmlBuilder::iss(101, 500))->toBe(5)
        ->and(NfseXmlBuilder::iss(110, 500))->toBe(6)
        ->and(NfseXmlBuilder::iss(1000, 0))->toBe(0)
        ->and(NfseXmlBuilder::moeda(550000))->toBe('5500,00')
        ->and(NfseXmlBuilder::moeda(7))->toBe('0,07')
        ->and(NfseXmlBuilder::moeda(123456789))->toBe('1234567,89');
});
