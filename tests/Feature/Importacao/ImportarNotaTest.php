<?php

use App\Models\Emitente;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\ProdutoFornecedor;
use App\Models\User;
use App\Services\Import\NFeImportService;
use Illuminate\Support\Facades\Storage;

function xmlAutorizado(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/xml/nfe-autorizada.xml'));
}

beforeEach(function () {
    Storage::fake('fiscal');
    // O destinatário do XML é o CNPJ da RCM.
    $this->emitente = Emitente::factory()->create(['cnpj' => '11222333000181', 'uf' => 'SP']);
    $this->user = User::factory()->create();
    $this->user->emitentes()->attach($this->emitente);
    $this->service = app(NFeImportService::class);
});

describe('identificação', function () {
    it('reconhece nota de entrada quando o destinatario e o emitente', function () {
        $nota = $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        expect($nota->tipo)->toBe('entrada')
            ->and($nota->chave_acesso)->toBe('35260911444777000161550010000088211234567897')
            ->and($nota->numero)->toBe('8821');
    });

    it('reconhece nota propria emitida em outro sistema', function () {
        // Quando o CNPJ do emitente do XML é o nosso, é histórico, não compra.
        $this->emitente->forceFill(['cnpj' => '11444777000161'])->save();

        $nota = $this->service->importar(xmlAutorizado(), $this->emitente->fresh(), $this->user);

        expect($nota->tipo)->toBe('propria');
    });

    it('recusa nota que nao e nossa nem para nos', function () {
        $this->emitente->forceFill(['cnpj' => '99888777000166'])->save();

        $this->service->importar(xmlAutorizado(), $this->emitente->fresh(), $this->user);
    })->throws(RuntimeException::class, 'não pertence');
});

describe('duplicidade', function () {
    it('recusa a mesma chave duas vezes', function () {
        $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);
    })->throws(RuntimeException::class, 'já foi importada');

    it('guarda o xml original', function () {
        $nota = $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        expect(Storage::disk('fiscal')->exists($nota->xml_path))->toBeTrue();
    });
});

describe('fornecedor', function () {
    it('cria o fornecedor a partir do xml quando nao existe', function () {
        $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        $fornecedor = Pessoa::where('documento', '11444777000161')->first();

        expect($fornecedor)->not->toBeNull()
            ->and($fornecedor->razao_social)->toBe('METALURGICA PIRACICABA LTDA')
            ->and($fornecedor->e_fornecedor)->toBeTrue()
            ->and($fornecedor->municipio)->toBe('Piracicaba')
            ->and($fornecedor->codigo_municipio)->toBe('3538709');
    });

    it('reaproveita fornecedor ja cadastrado sem duplicar', function () {
        Pessoa::create([
            'emitente_id' => $this->emitente->id, 'tipo_pessoa' => 'J',
            'documento' => '11444777000161', 'razao_social' => 'Nome que ja estava',
            'ind_ie_dest' => '1', 'inscricao_estadual' => '111222333444',
            'logradouro' => 'R', 'numero' => '1', 'bairro' => 'C',
            'codigo_municipio' => '3538709', 'municipio' => 'Piracicaba', 'uf' => 'SP',
            'cep' => '13400000', 'e_cliente' => true,
        ]);

        $nota = $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        expect(Pessoa::where('documento', '11444777000161')->count())->toBe(1)
            // Não sobrescreve o que o operador já tinha ajustado.
            ->and($nota->pessoa->razao_social)->toBe('Nome que ja estava')
            // Mas passa a valer também como fornecedor.
            ->and($nota->pessoa->e_fornecedor)->toBeTrue();
    });
});

describe('itens', function () {
    it('importa todos os itens da nota', function () {
        $nota = $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        expect($nota->itens)->toHaveCount(2);
    });

    it('converte o cfop de saida do fornecedor para o de entrada', function () {
        $nota = $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        // 5102 do fornecedor vira 1102 na nossa entrada.
        expect($nota->itens[0]->cfop_origem)->toBe('5102')
            ->and($nota->itens[0]->cfop_entrada)->toBe('1102');
    });

    it('guarda o custo unitario ja rateado', function () {
        $nota = $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        expect((float) $nota->itens[0]->custo_unitario)->toBe(30.66);
    });

    it('nasce sem produto vinculado', function () {
        $nota = $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        expect($nota->itens[0]->produto_id)->toBeNull()
            ->and($nota->status)->toBe('pendente');
    });
});

describe('conciliação automática', function () {
    it('vincula pelo codigo do fornecedor ja conhecido', function () {
        $fornecedor = Pessoa::create([
            'emitente_id' => $this->emitente->id, 'tipo_pessoa' => 'J',
            'documento' => '11444777000161', 'razao_social' => 'METALURGICA PIRACICABA LTDA',
            'ind_ie_dest' => '1', 'inscricao_estadual' => '111222333444',
            'logradouro' => 'R', 'numero' => '1', 'bairro' => 'C',
            'codigo_municipio' => '3538709', 'municipio' => 'Piracicaba', 'uf' => 'SP',
            'cep' => '13400000', 'e_fornecedor' => true,
        ]);
        $produto = Produto::create([
            'emitente_id' => $this->emitente->id, 'codigo' => 'MP-001',
            'descricao' => 'Barra inox 316L', 'ncm' => '72222000',
            'unidade_comercial' => 'KG', 'unidade_tributavel' => 'KG',
            'fator_conversao' => 1, 'origem' => '0',
        ]);
        ProdutoFornecedor::create([
            'emitente_id' => $this->emitente->id, 'pessoa_id' => $fornecedor->id,
            'produto_id' => $produto->id, 'codigo_fornecedor' => 'MP-INOX-316',
        ]);

        $nota = $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        expect($nota->itens[0]->produto_id)->toBe($produto->id);
    });

    it('sugere pelo gtin quando nao ha vinculo salvo', function () {
        $produto = Produto::create([
            'emitente_id' => $this->emitente->id, 'codigo' => 'CERA-A',
            'descricao' => 'Cera para microfusão', 'ncm' => '34049019',
            'gtin' => '7891000315507',
            'unidade_comercial' => 'KG', 'unidade_tributavel' => 'KG',
            'fator_conversao' => 1, 'origem' => '0',
        ]);

        $nota = $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        expect($nota->itens[1]->produto_id)->toBe($produto->id);
    });

    it('marca como conciliada quando todos os itens acharam produto', function () {
        foreach ([['MP-INOX-316', '72222000', null], ['MP-CERA-A', '34049019', '7891000315507']] as $i => [$cod, $ncm, $gtin]) {
            $p = Produto::create([
                'emitente_id' => $this->emitente->id, 'codigo' => "P{$i}",
                'descricao' => "Produto {$i}", 'ncm' => $ncm, 'gtin' => $gtin,
                'unidade_comercial' => 'KG', 'unidade_tributavel' => 'KG',
                'fator_conversao' => 1, 'origem' => '0',
            ]);
            if ($gtin === null) {
                $f = Pessoa::create([
                    'emitente_id' => $this->emitente->id, 'tipo_pessoa' => 'J',
                    'documento' => '11444777000161', 'razao_social' => 'METALURGICA PIRACICABA LTDA',
                    'ind_ie_dest' => '2', 'logradouro' => 'R', 'numero' => '1', 'bairro' => 'C',
                    'codigo_municipio' => '3538709', 'municipio' => 'Piracicaba', 'uf' => 'SP',
                    'cep' => '13400000', 'e_fornecedor' => true,
                ]);
                ProdutoFornecedor::create([
                    'emitente_id' => $this->emitente->id, 'pessoa_id' => $f->id,
                    'produto_id' => $p->id, 'codigo_fornecedor' => $cod,
                ]);
            }
        }

        $nota = $this->service->importar(xmlAutorizado(), $this->emitente, $this->user);

        expect($nota->status)->toBe('conciliada');
    });
});
