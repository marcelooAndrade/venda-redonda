<?php

namespace App\Services\Pessoas;

use App\Enums\Fiscal\IndIEDest;
use App\Enums\Fiscal\TipoPessoa;
use App\Support\Documento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Regras do cadastro de pessoa que a SEFAZ cobra na hora de autorizar.
 *
 * A coerência entre indIEDest e Inscrição Estadual é a que mais rejeita nota
 * na prática: contribuinte sem IE, ou isento com IE preenchida, não passa.
 * Melhor barrar no cadastro do que descobrir na transmissão.
 */
class ValidarPessoa
{
    /**
     * @param  array<string, mixed>  $dados
     *
     * @throws ValidationException
     */
    public function validar(array $dados): void
    {
        $validator = Validator::make($dados, [
            'tipo_pessoa' => ['required', 'in:F,J'],
            'documento' => ['required', 'string'],
            'razao_social' => ['required', 'string', 'max:255'],
            'ind_ie_dest' => ['required', 'in:1,2,9'],
            'inscricao_estadual' => ['nullable', 'string', 'max:20'],
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:60'],
            'bairro' => ['required', 'string', 'max:255'],
            'codigo_municipio' => ['required', 'digits:7'],
            'municipio' => ['required', 'string', 'max:255'],
            'uf' => ['required', 'string', 'size:2'],
            'cep' => ['required', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
        ]);

        $validator->after(function ($validator) use ($dados): void {
            $this->conferirDocumento($validator, $dados);
            $this->conferirInscricaoEstadual($validator, $dados);
            $this->conferirPapel($validator, $dados);
            $this->conferirMunicipio($validator, $dados);
        });

        $validator->validate();
    }

    /**
     * O código IBGE precisa ser de um município da UF informada.
     *
     * Desde 13/09 o campo é digitável, porque o ViaCEP nem sempre devolve o
     * código e antes disso a pessoa ficava travada sem poder salvar. Digitável
     * pede conferência: código de outra UF é rejeição na transmissão, e a ideia
     * da decisão de 10/09 é barrar o par inconsistente no cadastro.
     *
     * A conferência só acontece quando a tabela oficial conhece o código. Com
     * `fiscal:importar-municipios` ainda não rodado, a tabela está vazia e não
     * há o que conferir: aí vale o `digits:7` e mais nada, porque inventar
     * recusa sem base seria pior do que deixar passar.
     */
    private function conferirMunicipio($validator, array $dados): void
    {
        $codigo = (string) ($dados['codigo_municipio'] ?? '');
        $uf = strtoupper(trim((string) ($dados['uf'] ?? '')));

        if ($codigo === '' || $uf === '') {
            return;
        }

        $municipio = DB::table('municipios')->where('codigo_ibge', $codigo)->first();

        if ($municipio === null) {
            return;
        }

        if (strtoupper($municipio->uf) !== $uf) {
            $validator->errors()->add(
                'codigo_municipio',
                "O código {$codigo} é de {$municipio->nome}/{$municipio->uf}, e não da UF {$uf}."
            );
        }
    }

    private function conferirDocumento($validator, array $dados): void
    {
        $tipo = $dados['tipo_pessoa'] ?? null;
        $documento = (string) ($dados['documento'] ?? '');

        if ($tipo === TipoPessoa::Fisica->value) {
            if (! Documento::cpfValido($documento)) {
                $validator->errors()->add('documento', 'CPF inválido.');
            }

            return;
        }

        if ($tipo === TipoPessoa::Juridica->value && ! Documento::cnpjValido($documento)) {
            $validator->errors()->add('documento', 'CNPJ inválido.');
        }
    }

    private function conferirInscricaoEstadual($validator, array $dados): void
    {
        $ind = $dados['ind_ie_dest'] ?? null;
        $ie = $dados['inscricao_estadual'] ?? null;

        if ($ind === IndIEDest::Contribuinte->value && blank($ie)) {
            $validator->errors()->add(
                'inscricao_estadual',
                'Contribuinte de ICMS exige Inscrição Estadual. Se o destinatário não tem IE, marque como isento.',
            );

            return;
        }

        if ($ind === IndIEDest::Isento->value && filled($ie)) {
            $validator->errors()->add(
                'inscricao_estadual',
                'Contribuinte isento não pode ter Inscrição Estadual informada. Deixe o campo vazio ou marque como contribuinte.',
            );

            return;
        }

        if ($ind === IndIEDest::NaoContribuinte->value && filled($ie)) {
            $validator->errors()->add(
                'inscricao_estadual',
                'Não contribuinte não pode ter Inscrição Estadual informada.',
            );
        }
    }

    private function conferirPapel($validator, array $dados): void
    {
        $temPapel = ($dados['e_cliente'] ?? false)
            || ($dados['e_fornecedor'] ?? false)
            || ($dados['e_transportadora'] ?? false);

        if (! $temPapel) {
            $validator->errors()->add(
                'e_cliente',
                'Marque ao menos um papel: cliente, fornecedor ou transportadora.',
            );
        }
    }
}
