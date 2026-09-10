<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tabelas oficiais pequenas e estáveis, seguras para semear localmente.
 *
 * CFOP, CEST e cClassTrib NÃO estão aqui de propósito: são tabelas grandes,
 * mutáveis, e a regra 9 do projeto proíbe inventar regra fiscal. Ver a
 * pendência registrada em docs/decisoes-fiscais.md.
 */
class TabelasFiscaisSeeder extends Seeder
{
    public function run(): void
    {
        $cst = [
            // CST de ICMS, para CRT 3
            ['icms', '00', 'Tributada integralmente'],
            ['icms', '10', 'Tributada e com cobrança do ICMS por substituição tributária'],
            ['icms', '20', 'Com redução de base de cálculo'],
            ['icms', '30', 'Isenta ou não tributada e com cobrança do ICMS por substituição tributária'],
            ['icms', '40', 'Isenta'],
            ['icms', '41', 'Não tributada'],
            ['icms', '50', 'Suspensão'],
            ['icms', '51', 'Diferimento'],
            ['icms', '60', 'ICMS cobrado anteriormente por substituição tributária'],
            ['icms', '70', 'Com redução de base de cálculo e cobrança do ICMS por substituição tributária'],
            ['icms', '90', 'Outras'],

            // CSOSN, para CRT 1, 2 e 4
            ['csosn', '101', 'Tributada pelo Simples Nacional com permissão de crédito'],
            ['csosn', '102', 'Tributada pelo Simples Nacional sem permissão de crédito'],
            ['csosn', '103', 'Isenção do ICMS no Simples Nacional para faixa de receita bruta'],
            ['csosn', '201', 'Tributada pelo Simples Nacional com permissão de crédito e com cobrança do ICMS por substituição tributária'],
            ['csosn', '202', 'Tributada pelo Simples Nacional sem permissão de crédito e com cobrança do ICMS por substituição tributária'],
            ['csosn', '203', 'Isenção do ICMS no Simples Nacional para faixa de receita bruta e com cobrança do ICMS por substituição tributária'],
            ['csosn', '300', 'Imune'],
            ['csosn', '400', 'Não tributada pelo Simples Nacional'],
            ['csosn', '500', 'ICMS cobrado anteriormente por substituição tributária ou por antecipação'],
            ['csosn', '900', 'Outros'],

            // CST de IPI
            ['ipi', '00', 'Entrada com recuperação de crédito'],
            ['ipi', '01', 'Entrada tributada com alíquota zero'],
            ['ipi', '02', 'Entrada isenta'],
            ['ipi', '03', 'Entrada não tributada'],
            ['ipi', '04', 'Entrada imune'],
            ['ipi', '05', 'Entrada com suspensão'],
            ['ipi', '49', 'Outras entradas'],
            ['ipi', '50', 'Saída tributada'],
            ['ipi', '51', 'Saída tributada com alíquota zero'],
            ['ipi', '52', 'Saída isenta'],
            ['ipi', '53', 'Saída não tributada'],
            ['ipi', '54', 'Saída imune'],
            ['ipi', '55', 'Saída com suspensão'],
            ['ipi', '99', 'Outras saídas'],
        ];

        // CST de PIS e COFINS compartilham a mesma tabela de códigos.
        $pisCofins = [
            ['01', 'Operação tributável com alíquota básica'],
            ['02', 'Operação tributável com alíquota diferenciada'],
            ['03', 'Operação tributável com alíquota por unidade de medida de produto'],
            ['04', 'Operação tributável monofásica, revenda a alíquota zero'],
            ['05', 'Operação tributável por substituição tributária'],
            ['06', 'Operação tributável a alíquota zero'],
            ['07', 'Operação isenta da contribuição'],
            ['08', 'Operação sem incidência da contribuição'],
            ['09', 'Operação com suspensão da contribuição'],
            ['49', 'Outras operações de saída'],
            ['99', 'Outra operação'],
        ];

        foreach ($pisCofins as [$codigo, $descricao]) {
            $cst[] = ['pis', $codigo, $descricao];
            $cst[] = ['cofins', $codigo, $descricao];
        }

        DB::table('codigos_situacao_tributaria')->upsert(
            array_map(fn (array $l): array => [
                'imposto' => $l[0], 'codigo' => $l[1], 'descricao' => $l[2],
            ], $cst),
            ['imposto', 'codigo'],
            ['descricao'],
        );

        $unidades = [
            ['UN', 'Unidade'], ['PC', 'Peça'], ['KG', 'Quilograma'], ['G', 'Grama'],
            ['TO', 'Tonelada'], ['MT', 'Metro'], ['M2', 'Metro quadrado'], ['M3', 'Metro cúbico'],
            ['CM', 'Centímetro'], ['MM', 'Milímetro'], ['LT', 'Litro'], ['ML', 'Mililitro'],
            ['CX', 'Caixa'], ['PT', 'Pacote'], ['PAR', 'Par'], ['CJ', 'Conjunto'],
            ['DZ', 'Dúzia'], ['ROL', 'Rolo'], ['BAR', 'Barra'], ['SC', 'Saco'],
        ];

        DB::table('unidades_medida')->upsert(
            array_map(fn (array $u): array => ['sigla' => $u[0], 'descricao' => $u[1]], $unidades),
            ['sigla'],
            ['descricao'],
        );
    }
}
