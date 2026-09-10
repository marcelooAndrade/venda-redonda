<?php

namespace App\Services\Import;

use App\Models\Emitente;
use App\Models\NotaEntrada;
use App\Models\NotaEntradaItem;
use App\Models\Pessoa;
use App\Models\User;
use App\Support\Documento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Traz uma NF-e de XML para dentro do sistema.
 *
 * Não movimenta estoque: importar é só registrar o que chegou. A entrada
 * acontece na confirmação, depois que os itens estiverem conciliados com o
 * cadastro de produtos.
 */
class NFeImportService
{
    public function __construct(
        private readonly NFeXmlParser $parser,
        private readonly ConciliacaoService $conciliacao,
    ) {}

    public function importar(string $xml, Emitente $emitente, ?User $user = null): NotaEntrada
    {
        $nota = $this->parser->parse($xml);

        $this->conferirDuplicidade($nota->chave);
        $tipo = $this->identificarTipo($nota, $emitente);

        return DB::transaction(function () use ($nota, $emitente, $tipo): NotaEntrada {
            $fornecedor = $tipo === 'entrada'
                ? $this->resolverFornecedor($nota->emitente, $emitente)
                : null;

            $path = $this->guardarXml($nota->chave, $emitente, $nota->xml);

            $registro = NotaEntrada::create([
                'emitente_id' => $emitente->getKey(),
                'pessoa_id' => $fornecedor?->getKey(),
                'chave_acesso' => $nota->chave,
                'numero' => $nota->numero,
                'serie' => $nota->serie,
                'data_emissao' => $nota->dataEmissao,
                'natureza_operacao' => $nota->naturezaOperacao,
                'tipo' => $tipo,
                'status' => 'pendente',
                'valor_produtos' => $nota->totais['valor_produtos'],
                'valor_frete' => $nota->totais['valor_frete'],
                'valor_ipi' => $nota->totais['valor_ipi'],
                'valor_nota' => $nota->totais['valor_nota'],
                'protocolo' => $nota->protocolo,
                'xml_path' => $path,
            ]);

            foreach ($nota->itens as $item) {
                NotaEntradaItem::create([
                    'nota_entrada_id' => $registro->getKey(),
                    'numero' => $item['numero'],
                    'codigo_fornecedor' => $item['codigo'],
                    'descricao' => $item['descricao'],
                    'gtin' => $item['gtin'],
                    'ncm' => $item['ncm'],
                    'cfop_origem' => $item['cfop'],
                    'cfop_entrada' => $this->cfopDeEntrada($item['cfop']),
                    'unidade' => $item['unidade'],
                    'quantidade' => $item['quantidade'],
                    'valor_unitario' => $item['valor_unitario'],
                    'valor_total' => $item['valor_total'],
                    'custo_unitario' => $item['custo_unitario'],
                    'fator_conversao' => 1,
                ]);
            }

            // Tenta casar os itens com o cadastro antes de mostrar a tela: o
            // operador só resolve o que o sistema não soube resolver sozinho.
            $this->conciliacao->conciliarAutomaticamente($registro->fresh('itens'));

            return $registro->fresh('itens');
        });
    }

    private function conferirDuplicidade(string $chave): void
    {
        if (NotaEntrada::query()->where('chave_acesso', $chave)->exists()) {
            throw new RuntimeException("A nota de chave {$chave} já foi importada.");
        }
    }

    /**
     * Nota de entrada quando somos o destinatário. Nota própria quando somos
     * o emitente: veio de outro sistema e entra como histórico, sem estoque.
     */
    private function identificarTipo(NotaImportada $nota, Emitente $emitente): string
    {
        $nosso = preg_replace('/\D/', '', $emitente->cnpj);
        $destino = preg_replace('/\D/', '', (string) ($nota->destinatario['cnpj'] ?? ''));
        $origem = preg_replace('/\D/', '', (string) ($nota->emitente['cnpj'] ?? ''));

        if ($destino === $nosso) {
            return 'entrada';
        }

        if ($origem === $nosso) {
            return 'propria';
        }

        throw new RuntimeException(
            'Esta nota não pertence ao emitente selecionado: nem o emitente nem o destinatário do XML '
            ."correspondem ao CNPJ {$nosso}."
        );
    }

    /** @param array<string, mixed> $dados */
    private function resolverFornecedor(array $dados, Emitente $emitente): Pessoa
    {
        $documento = Documento::normalizarCnpj((string) ($dados['cnpj'] ?? $dados['cpf'] ?? ''));

        $existente = Pessoa::query()->where('documento', $documento)->first();

        if ($existente !== null) {
            // Não sobrescreve o que o operador já ajustou, só garante o papel.
            if (! $existente->e_fornecedor) {
                $existente->update(['e_fornecedor' => true]);
            }

            return $existente;
        }

        return Pessoa::create([
            'emitente_id' => $emitente->getKey(),
            'tipo_pessoa' => filled($dados['cnpj'] ?? null) ? 'J' : 'F',
            'documento' => $documento,
            'razao_social' => $dados['razao_social'] ?: 'Fornecedor sem nome no XML',
            'nome_fantasia' => $dados['nome_fantasia'] ?? null,
            'ind_ie_dest' => filled($dados['inscricao_estadual'] ?? null) ? '1' : '2',
            'inscricao_estadual' => $dados['inscricao_estadual'] ?? null,
            'logradouro' => $dados['logradouro'] ?: 'Não informado',
            'numero' => $dados['numero'] ?: 'S/N',
            'complemento' => $dados['complemento'] ?? null,
            'bairro' => $dados['bairro'] ?: 'Não informado',
            'codigo_municipio' => $dados['codigo_municipio'] ?: '0000000',
            'municipio' => $dados['municipio'] ?: 'Não informado',
            'uf' => $dados['uf'] ?: 'SP',
            'cep' => $dados['cep'] ?: '00000000',
            'telefone' => $dados['telefone'] ?? null,
            'e_fornecedor' => true,
        ]);
    }

    /** CFOP de saída do fornecedor vira o nosso de entrada: 5102 → 1102. */
    private function cfopDeEntrada(?string $cfopSaida): ?string
    {
        if (blank($cfopSaida)) {
            return null;
        }

        $convertido = DB::table('cfop_entrada_saida')
            ->where('cfop_saida', $cfopSaida)
            ->value('cfop_entrada');

        if ($convertido !== null) {
            return $convertido;
        }

        // Sem par cadastrado, aplica a regra geral: 5xxx vira 1xxx, 6xxx vira 2xxx.
        return match ($cfopSaida[0]) {
            '5' => '1'.substr($cfopSaida, 1),
            '6' => '2'.substr($cfopSaida, 1),
            '7' => '3'.substr($cfopSaida, 1),
            default => null,
        };
    }

    private function guardarXml(string $chave, Emitente $emitente, string $xml): string
    {
        $path = "entradas/{$emitente->getKey()}/".substr($chave, 2, 4)."/{$chave}.xml";

        Storage::disk('fiscal')->put($path, $xml);

        return $path;
    }
}
