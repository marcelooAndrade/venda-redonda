<?php

use App\Models\Emitente;
use App\Models\EstoqueMovimento;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\ProdutoFornecedor;
use App\Models\User;
use App\Services\Import\ConfirmarEntradaService;
use App\Services\Import\NFeImportService;
use App\Services\Stock\StockService;
use Illuminate\Support\Facades\Storage;

function fornecedorEProdutos(Emitente $emitente): array
{
    $f = Pessoa::create([
        'emitente_id' => $emitente->id, 'tipo_pessoa' => 'J',
        'documento' => '11444777000161', 'razao_social' => 'METALURGICA PIRACICABA LTDA',
        'ind_ie_dest' => '2', 'logradouro' => 'R', 'numero' => '1', 'bairro' => 'C',
        'codigo_municipio' => '3538709', 'municipio' => 'Piracicaba', 'uf' => 'SP',
        'cep' => '13400000', 'e_fornecedor' => true,
    ]);

    $produtos = [];
    foreach ([['MP-INOX-316', 'Barra inox', '72222000', 'KG', null], ['MP-CERA-A', 'Cera A', '34049019', 'KG', '7891000315507']] as $i => [$cod, $desc, $ncm, $un, $gtin]) {
        $p = Produto::create([
            'emitente_id' => $emitente->id, 'codigo' => "INT-{$i}", 'descricao' => $desc,
            'ncm' => $ncm, 'gtin' => $gtin, 'unidade_comercial' => $un, 'unidade_tributavel' => $un,
            'fator_conversao' => 1, 'origem' => '0',
        ]);
        ProdutoFornecedor::create([
            'emitente_id' => $emitente->id, 'pessoa_id' => $f->id,
            'produto_id' => $p->id, 'codigo_fornecedor' => $cod,
        ]);
        $produtos[$cod] = $p;
    }

    return [$f, $produtos];
}

beforeEach(function () {
    Storage::fake('fiscal');
    $this->emitente = Emitente::factory()->create(['cnpj' => '11222333000181', 'uf' => 'SP']);
    $this->user = User::factory()->create();
    $this->user->emitentes()->attach($this->emitente);
    $this->confirmar = app(ConfirmarEntradaService::class);
    $this->stock = app(StockService::class);
});

it('recusa confirmar nota com item nao conciliado', function () {
    $nota = app(NFeImportService::class)->importar(xmlAutorizado(), $this->emitente, $this->user);

    $this->confirmar->confirmar($nota, $this->user);
})->throws(RuntimeException::class, 'conciliad');

it('da entrada no estoque com o custo rateado', function () {
    [, $produtos] = fornecedorEProdutos($this->emitente);
    $nota = app(NFeImportService::class)->importar(xmlAutorizado(), $this->emitente, $this->user);

    $this->confirmar->confirmar($nota, $this->user);

    $saldo = $this->stock->saldo($produtos['MP-INOX-316']);

    expect($saldo->quantidade)->toBe(500.0)
        // (14250 + 350 + 730) / 500
        ->and($saldo->custo_medio)->toBe(30.66);
});

it('marca a nota como confirmada com autor e horario', function () {
    fornecedorEProdutos($this->emitente);
    $nota = app(NFeImportService::class)->importar(xmlAutorizado(), $this->emitente, $this->user);

    $this->confirmar->confirmar($nota, $this->user);

    $nota = $nota->fresh();
    expect($nota->status)->toBe('confirmada')
        ->and($nota->confirmada_por)->toBe($this->user->id)
        ->and($nota->confirmada_em)->not->toBeNull();
});

it('recusa confirmar duas vezes', function () {
    fornecedorEProdutos($this->emitente);
    $nota = app(NFeImportService::class)->importar(xmlAutorizado(), $this->emitente, $this->user);
    $this->confirmar->confirmar($nota, $this->user);

    $this->confirmar->confirmar($nota->fresh(), $this->user);
})->throws(RuntimeException::class, 'já foi confirmada');

it('registra o documento de origem no movimento', function () {
    [, $produtos] = fornecedorEProdutos($this->emitente);
    $nota = app(NFeImportService::class)->importar(xmlAutorizado(), $this->emitente, $this->user);

    $this->confirmar->confirmar($nota, $this->user);

    expect($this->stock->movimentos($produtos['MP-INOX-316'])->first()->documento)
        ->toContain('8821');
});

it('aplica o fator de conversao do fornecedor', function () {
    [$f, $produtos] = fornecedorEProdutos($this->emitente);
    // O fornecedor vende em KG, mas internamente contamos em unidades de 0,5 kg.
    ProdutoFornecedor::where('codigo_fornecedor', 'MP-INOX-316')->update(['fator_conversao' => 2]);

    $nota = app(NFeImportService::class)->importar(xmlAutorizado(), $this->emitente, $this->user);
    $this->confirmar->confirmar($nota, $this->user);

    $saldo = $this->stock->saldo($produtos['MP-INOX-316']);

    expect($saldo->quantidade)->toBe(1000.0)
        // O custo por unidade interna cai pela metade.
        ->and($saldo->custo_medio)->toBe(15.33);
});

it('nota propria nao movimenta estoque', function () {
    $this->emitente->forceFill(['cnpj' => '11444777000161'])->save();
    fornecedorEProdutos($this->emitente);
    $nota = app(NFeImportService::class)->importar(xmlAutorizado(), $this->emitente->fresh(), $this->user);

    $this->confirmar->confirmar($nota, $this->user);

    expect($nota->fresh()->status)->toBe('confirmada')
        ->and(EstoqueMovimento::count())->toBe(0);
});
