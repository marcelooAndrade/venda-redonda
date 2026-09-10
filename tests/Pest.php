<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)

    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/*
 * Helpers compartilhados entre suítes.
 *
 * Ficam aqui, e não no arquivo onde nasceram, para que cada teste possa ser
 * rodado isoladamente. Pest carrega este arquivo sempre; um arquivo de teste
 * só é carregado quando ele mesmo entra na execução.
 */

use App\Models\Emitente;
use App\Models\NaturezaOperacao;
use App\Models\Nota;
use App\Models\NotaItem;
use App\Models\PerfilFiscal;
use App\Models\PerfilFiscalRegra;
use App\Models\Pessoa;
use App\Models\Produto;
use App\Models\User;
use App\Services\Fiscal\RespostaSefaz;
use App\Services\Fiscal\SefazGateway;
use App\Services\Stock\StockService;
use App\Support\TenantAtual;

function produtoDe(Emitente $emitente, array $extra = []): Produto
{
    return Produto::create(array_merge([
        'emitente_id' => $emitente->id,
        'codigo' => 'PC-'.fake()->unique()->numerify('###'),
        'descricao' => 'Peça microfundida',
        'ncm' => '73259910',
        'unidade_comercial' => 'PC',
        'unidade_tributavel' => 'PC',
        'fator_conversao' => 1,
        'origem' => '0',
    ], $extra));
}

function xmlAutorizado(): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/xml/nfe-autorizada.xml'));
}

function emitenteCompleto(): Emitente
{
    return Emitente::factory()->create([
        'razao_social' => 'RCM DO BRASIL LTDA',
        'nome_fantasia' => 'RCM do Brasil',
        'cnpj' => '11222333000181',
        'inscricao_estadual' => '123456789012',
        'crt' => '3',
        'logradouro' => 'Rua Joao Grigoleto', 'numero' => '83',
        'bairro' => 'Distrito Industrial II',
        'codigo_municipio' => '3503307', 'municipio' => 'Araras',
        'uf' => 'SP', 'cep' => '13602200', 'telefone' => '1930960072',
    ]);
}

function destinatarioCompleto(Emitente $e, array $extra = []): Pessoa
{
    return Pessoa::create(array_merge([
        'emitente_id' => $e->id, 'tipo_pessoa' => 'J',
        'documento' => '11444777000161',
        'razao_social' => 'METALURGICA PIRACICABA LTDA',
        'ind_ie_dest' => '1', 'inscricao_estadual' => '111222333444',
        'logradouro' => 'Avenida Industrial', 'numero' => '450',
        'bairro' => 'Distrito Industrial',
        'codigo_municipio' => '3538709', 'municipio' => 'Piracicaba',
        'uf' => 'SP', 'cep' => '13400000',
        'e_cliente' => true,
    ], $extra));
}

function notaPronta(array $extraNota = [], array $extraRegra = []): Nota
{
    $emitente = emitenteCompleto();
    $dest = destinatarioCompleto($emitente);

    $perfil = PerfilFiscal::create(['emitente_id' => $emitente->id, 'nome' => 'Peças']);
    PerfilFiscalRegra::create(array_merge([
        'perfil_fiscal_id' => $perfil->id, 'ambito' => 'interna', 'vigente_de' => '2026-01-01',
        'cst_icms' => '00', 'aliquota_icms' => 18,
        'cst_pis' => '01', 'aliquota_pis' => 1.65,
        'cst_cofins' => '01', 'aliquota_cofins' => 7.6,
    ], $extraRegra));

    $natureza = NaturezaOperacao::create([
        'emitente_id' => $emitente->id, 'perfil_fiscal_id' => $perfil->id,
        'descricao' => 'Venda de producao propria',
        'cfop_interno' => '5101', 'cfop_interestadual' => '6101',
    ]);

    $produto = Produto::create([
        'emitente_id' => $emitente->id, 'perfil_fiscal_id' => $perfil->id,
        'codigo' => 'PC-001', 'descricao' => 'Peca microfundida em aco inox 316L',
        'ncm' => '73259910', 'unidade_comercial' => 'PC', 'unidade_tributavel' => 'PC',
        'fator_conversao' => 1, 'origem' => '0', 'preco_venda' => 145.90,
        'peso_liquido' => 0.45, 'peso_bruto' => 0.48,
    ]);

    $nota = Nota::create(array_merge([
        'emitente_id' => $emitente->id,
        'pessoa_id' => $dest->id,
        'natureza_operacao_id' => $natureza->id,
        'natureza_operacao' => $natureza->descricao,
        'serie' => 1, 'numero' => 1480,
        'ambiente' => 'homologacao',
        'data_emissao' => now(),
        'id_dest' => '1', 'mod_frete' => '9',
        'valor_produtos' => 1459.00, 'valor_nota' => 1459.00,
        'base_icms' => 1459.00, 'valor_icms' => 262.62,
        'valor_pis' => 24.07, 'valor_cofins' => 110.88,
    ], $extraNota));

    // Produto com saldo, como numa fundição de verdade. Sem isso a
    // transmissão é barrada antes de chegar na SEFAZ, e com razão.
    app(StockService::class)->entrada($produto, 1000, 60.00, 'Saldo inicial');

    NotaItem::create([
        'nota_id' => $nota->id, 'produto_id' => $produto->id, 'numero' => 1,
        'codigo' => 'PC-001', 'descricao' => 'Peca microfundida em aco inox 316L',
        'ncm' => '73259910', 'cfop' => '5101', 'unidade' => 'PC', 'unidade_tributavel' => 'PC',
        'origem' => '0', 'quantidade' => 10, 'quantidade_tributavel' => 10,
        'valor_unitario' => 145.90, 'valor_produto' => 1459.00,
        'cst_icms' => '00', 'mod_bc' => '3', 'base_icms' => 1459.00,
        'aliquota_icms' => 18, 'valor_icms' => 262.62,
        'cst_pis' => '01', 'valor_pis' => 24.07,
        'cst_cofins' => '01', 'valor_cofins' => 110.88,
    ]);

    return $nota->fresh(['itens', 'destinatario', 'emitente']);
}

/*
 * Gateway falso da SEFAZ, roteirizado.
 *
 * Fica aqui porque três suítes precisam dele: transmissão, eventos e a tela
 * de emissão. Guarda o que foi chamado, para as asserções de idempotência.
 */
function gatewayFake(array $roteiro): SefazGateway
{
    return new class($roteiro) implements SefazGateway
    {
        public array $chamadas = [];

        public function __construct(private array $roteiro) {}

        private function responder(string $metodo): RespostaSefaz
        {
            $this->chamadas[] = $metodo;
            $r = $this->roteiro[$metodo] ?? new RespostaSefaz('999', "Sem roteiro para {$metodo}");

            if ($r instanceof Throwable) {
                throw $r;
            }

            return $r;
        }

        public function enviar(Emitente $e, string $xml): RespostaSefaz
        {
            return $this->responder('enviar');
        }

        public function consultarRecibo(Emitente $e, string $recibo): RespostaSefaz
        {
            return $this->responder('consultarRecibo');
        }

        public function consultarChave(Emitente $e, string $chave): RespostaSefaz
        {
            return $this->responder('consultarChave');
        }

        public function statusServico(Emitente $e): RespostaSefaz
        {
            $this->chamadas[] = 'statusServico';

            return $this->roteiro['statusServico'] ?? new RespostaSefaz('107', 'Servico em operacao');
        }

        public function cancelar(Emitente $e, string $chave, string $protocolo, string $justificativa): RespostaSefaz
        {
            return $this->responder('cancelar');
        }

        public function cartaCorrecao(Emitente $e, string $chave, string $correcao, int $sequencia): RespostaSefaz
        {
            return $this->responder('cartaCorrecao');
        }

        public function inutilizar(Emitente $e, int $ano, int $serie, int $inicial, int $final, string $justificativa): RespostaSefaz
        {
            return $this->responder('inutilizar');
        }
    };
}

function comGateway(array $roteiro): object
{
    $fake = gatewayFake($roteiro);
    app()->instance(SefazGateway::class, $fake);

    return $fake;
}

function autorizada(string $chave = '35260911222333000181550010000014801033717992'): RespostaSefaz
{
    return new RespostaSefaz('100', 'Autorizado o uso da NF-e', '135260000123456', null, '<nfeProc/>', $chave);
}

/*
 * Usuário do tenant de teste, com perfil e emitente vinculados.
 *
 * Três suítes precisam dele: a tela de marca, o upload de logo e o
 * isolamento entre tenants.
 */
function usuarioMarca(string $perfil): User
{
    $tenant = app(TenantAtual::class)->obter();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $emitente = Emitente::factory()->create(['tenant_id' => $tenant->id]);
    $user->emitentes()->attach($emitente);
    setPermissionsTeamId($emitente->id);
    $user->assignRole($perfil);

    return $user;
}
