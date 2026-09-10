<?php

use App\Models\Emitente;
use App\Models\Produto;
use App\Services\Produtos\ValidarProduto;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function ncmValido(string $codigo = '73259910'): void
{
    DB::table('ncms')->insert([
        'codigo' => $codigo, 'descricao' => 'De aço', 'valido_nfe' => true, 'vigente_de' => '2022-04-01',
    ]);
}

function produtoBase(array $extra = []): array
{
    return array_merge([
        'codigo' => 'PC-001',
        'descricao' => 'Peça microfundida em aço inox',
        'ncm' => '73259910',
        'gtin' => 'SEM GTIN',
        'unidade_comercial' => 'PC',
        'unidade_tributavel' => 'PC',
        'fator_conversao' => 1,
        'origem' => '0',
        'preco_venda' => 145.90,
    ], $extra);
}

beforeEach(function () {
    $this->emitente = Emitente::factory()->create();
    $this->validador = app(ValidarProduto::class);
});

describe('NCM', function () {
    it('aceita ncm existente na tabela oficial', function () {
        ncmValido();

        expect(fn () => $this->validador->validar(produtoBase()))->not->toThrow(ValidationException::class);
    });

    it('recusa ncm que nao existe', function () {
        ncmValido();

        $this->validador->validar(produtoBase(['ncm' => '99999999']));
    })->throws(ValidationException::class, 'não consta');

    it('recusa ncm de capitulo, que nao vale na nota', function () {
        // "7325" é posição, não item de oito dígitos. Só o de 8 vale na NF-e.
        DB::table('ncms')->insert([
            'codigo' => '7325', 'descricao' => 'Outras obras moldadas', 'valido_nfe' => false, 'vigente_de' => '2022-04-01',
        ]);

        $this->validador->validar(produtoBase(['ncm' => '7325']));
    })->throws(ValidationException::class);
});

describe('GTIN', function () {
    it('aceita gtin valido', function () {
        ncmValido();

        expect(fn () => $this->validador->validar(produtoBase(['gtin' => '7891000315507'])))
            ->not->toThrow(ValidationException::class);
    });

    it('recusa gtin com digito errado', function () {
        ncmValido();

        $this->validador->validar(produtoBase(['gtin' => '7891000315508']));
    })->throws(ValidationException::class, 'GTIN');
});

describe('unidade tributável', function () {
    it('exige fator quando a unidade tributavel difere da comercial', function () {
        ncmValido();

        $this->validador->validar(produtoBase([
            'unidade_comercial' => 'CX', 'unidade_tributavel' => 'PC', 'fator_conversao' => 1,
        ]));
    })->throws(ValidationException::class, 'fator de conversão');

    it('aceita fator maior que um quando as unidades diferem', function () {
        ncmValido();

        expect(fn () => $this->validador->validar(produtoBase([
            'unidade_comercial' => 'CX', 'unidade_tributavel' => 'PC', 'fator_conversao' => 12,
        ])))->not->toThrow(ValidationException::class);
    });
});

describe('gravação', function () {
    it('normaliza o gtin vazio para o literal da nf-e', function () {
        ncmValido();

        $produto = Produto::create([...produtoBase(['gtin' => '']), 'emitente_id' => $this->emitente->id]);

        expect($produto->gtin)->toBe('SEM GTIN');
    });

    it('impede codigo repetido no mesmo emitente', function () {
        ncmValido();
        Produto::create([...produtoBase(), 'emitente_id' => $this->emitente->id]);

        Produto::create([...produtoBase(), 'emitente_id' => $this->emitente->id]);
    })->throws(QueryException::class);

    it('permite o mesmo codigo em emitentes diferentes', function () {
        ncmValido();
        $outro = Emitente::factory()->create();

        Produto::create([...produtoBase(), 'emitente_id' => $this->emitente->id]);
        Produto::create([...produtoBase(), 'emitente_id' => $outro->id]);

        expect(Produto::count())->toBe(2);
    });
});
