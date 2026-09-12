<?php

namespace App\Livewire\Emitentes;

use App\Models\Emitente;
use App\Services\Integrations\ViaCepService;
use App\Support\EmitenteAtual;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * Dados cadastrais do emitente.
 *
 * O cadastro cria o emitente com o mínimo para entrar: razão social, CNPJ,
 * IE, CRT, telefone e e-mail. O que a nota exige além disso, inscrição
 * municipal, endereço e chave Pix, é preenchido aqui. Ambiente da NF-e,
 * série e certificado continuam nas telas próprias.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Emitente')]
class Cadastro extends Component
{
    private const CAMPOS = [
        'razao_social', 'nome_fantasia', 'inscricao_estadual', 'inscricao_municipal', 'crt', 'cnae',
        'logradouro', 'numero', 'complemento', 'bairro', 'codigo_municipio', 'municipio', 'uf', 'cep',
        'telefone', 'email', 'chave_pix',
    ];

    /** @var array<string, string> */
    public array $form = [];

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('emitente.gerenciar');

        foreach (self::CAMPOS as $campo) {
            $this->form[$campo] = (string) ($this->emitente->{$campo} ?? '');
        }
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    public function cnpjFormatado(): string
    {
        return (string) preg_replace('/(.{2})(.{3})(.{3})(.{4})(.{2})/', '$1.$2.$3/$4-$5', (string) $this->emitente?->cnpj);
    }

    public function buscarCep(ViaCepService $viaCep): void
    {
        try {
            $dados = $viaCep->consultar((string) $this->form['cep']);
        } catch (RuntimeException $e) {
            $this->addError('form.cep', $e->getMessage());

            return;
        }

        $this->form['logradouro'] = $dados->logradouro ?: $this->form['logradouro'];
        $this->form['bairro'] = $dados->bairro ?: $this->form['bairro'];
        $this->form['municipio'] = $dados->municipio ?: $this->form['municipio'];
        $this->form['uf'] = $dados->uf ?: $this->form['uf'];
        $this->form['codigo_municipio'] = $dados->codigoIbge ?? '';
    }

    public function salvar(): void
    {
        $this->authorize('emitente.gerenciar');

        $this->form['cep'] = (string) preg_replace('/\D/', '', (string) $this->form['cep']);
        $this->form['uf'] = strtoupper(trim((string) $this->form['uf']));

        $dados = $this->validate([
            'form.razao_social' => ['required', 'string', 'min:2', 'max:200'],
            'form.nome_fantasia' => ['nullable', 'string', 'max:200'],
            'form.inscricao_estadual' => ['required', 'string', 'max:20'],
            'form.inscricao_municipal' => ['nullable', 'string', 'max:20'],
            'form.crt' => ['required', 'in:1,2,3,4'],
            'form.cnae' => ['nullable', 'digits:7'],
            'form.logradouro' => ['nullable', 'string', 'max:255'],
            'form.numero' => ['nullable', 'string', 'max:60'],
            'form.complemento' => ['nullable', 'string', 'max:255'],
            'form.bairro' => ['nullable', 'string', 'max:255'],
            'form.codigo_municipio' => ['nullable', 'digits:7'],
            'form.municipio' => ['nullable', 'string', 'max:255'],
            'form.uf' => ['nullable', 'string', 'size:2'],
            'form.cep' => ['nullable', 'digits:8'],
            'form.telefone' => ['nullable', 'string', 'max:20'],
            'form.email' => ['nullable', 'email', 'max:254'],
            'form.chave_pix' => ['nullable', 'string', 'max:77'],
        ], [], [
            'form.razao_social' => 'razão social',
            'form.nome_fantasia' => 'nome fantasia',
            'form.inscricao_estadual' => 'inscrição estadual',
            'form.inscricao_municipal' => 'inscrição municipal',
            'form.crt' => 'CRT',
            'form.cnae' => 'CNAE',
            'form.logradouro' => 'logradouro',
            'form.numero' => 'número',
            'form.complemento' => 'complemento',
            'form.bairro' => 'bairro',
            'form.codigo_municipio' => 'código IBGE',
            'form.municipio' => 'município',
            'form.uf' => 'UF',
            'form.cep' => 'CEP',
            'form.telefone' => 'telefone',
            'form.email' => 'e-mail',
            'form.chave_pix' => 'chave Pix',
        ])['form'];

        // Vazio vira nulo: coluna nulável com string vazia engana quem
        // confere com `filled`, e a NFS-e confere a inscrição municipal assim.
        $this->emitente->update(array_map(fn ($valor) => $valor === '' ? null : $valor, $dados));

        unset($this->emitente);

        session()->flash('sucesso', 'Emitente salvo.');
    }

    public function render()
    {
        return view('livewire.emitentes.cadastro');
    }
}
