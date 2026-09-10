<?php

namespace App\Services\Produtos;

use App\Support\Gtin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Regras do cadastro de produto que a SEFAZ cobra na autorização.
 *
 * O NCM é conferido contra a tabela oficial importada do Siscomex, e só
 * valem os itens de oito dígitos: capítulo e posição existem para navegação,
 * não para preencher a nota.
 */
class ValidarProduto
{
    /**
     * @param  array<string, mixed>  $dados
     *
     * @throws ValidationException
     */
    public function validar(array $dados): void
    {
        $validator = Validator::make($dados, [
            'codigo' => ['required', 'string', 'max:60'],
            'descricao' => ['required', 'string', 'max:255'],
            'ncm' => ['required', 'string'],
            'unidade_comercial' => ['required', 'string', 'max:6'],
            'unidade_tributavel' => ['required', 'string', 'max:6'],
            'fator_conversao' => ['required', 'numeric', 'gt:0'],
            'origem' => ['required', 'in:0,1,2,3,4,5,6,7,8'],
            'preco_venda' => ['nullable', 'numeric', 'min:0'],
            'cest' => ['nullable', 'digits:7'],
        ]);

        $validator->after(function ($validator) use ($dados): void {
            $this->conferirNcm($validator, $dados);
            $this->conferirGtin($validator, $dados);
            $this->conferirConversao($validator, $dados);
        });

        $validator->validate();
    }

    private function conferirNcm($validator, array $dados): void
    {
        $ncm = preg_replace('/\D/', '', (string) ($dados['ncm'] ?? ''));

        $existe = DB::table('ncms')
            ->where('codigo', $ncm)
            ->where('valido_nfe', true)
            ->where(fn ($q) => $q->whereNull('vigente_ate')->orWhereDate('vigente_ate', '>=', now()))
            ->exists();

        if (! $existe) {
            $validator->errors()->add(
                'ncm',
                "O NCM {$ncm} não consta na tabela oficial vigente, ou não é um item de oito dígitos. "
                .'Confira no Portal Único Siscomex ou rode `php artisan fiscal:importar-ncm`.',
            );
        }
    }

    private function conferirGtin($validator, array $dados): void
    {
        foreach (['gtin' => 'GTIN', 'gtin_tributavel' => 'GTIN tributável'] as $campo => $rotulo) {
            $valor = $dados[$campo] ?? null;

            if ($valor !== null && ! Gtin::valido($valor)) {
                $validator->errors()->add(
                    $campo,
                    "{$rotulo} inválido. Informe um código de barras de 8, 12, 13 ou 14 dígitos, ou deixe vazio.",
                );
            }
        }
    }

    private function conferirConversao($validator, array $dados): void
    {
        $comercial = $dados['unidade_comercial'] ?? null;
        $tributavel = $dados['unidade_tributavel'] ?? null;
        $fator = (float) ($dados['fator_conversao'] ?? 1);

        if ($comercial !== $tributavel && $fator === 1.0) {
            $validator->errors()->add(
                'fator_conversao',
                "A unidade comercial ({$comercial}) difere da tributável ({$tributavel}), "
                .'então o fator de conversão precisa dizer quantas unidades tributáveis cabem em uma comercial.',
            );
        }
    }
}
