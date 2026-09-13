<?php

namespace App\Livewire\Pessoas;

use App\Enums\Fiscal\IndIEDest;
use App\Enums\Fiscal\TipoPessoa;
use App\Models\Emitente;
use App\Models\Pessoa;
use App\Services\Integrations\ReceitaWsService;
use App\Services\Integrations\ViaCepService;
use App\Services\Pessoas\ValidarPessoa;
use App\Support\EmitenteAtual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('components.layouts.fiscal')]
#[Title('Destinatários')]
class Cadastro extends Component
{
    use WithPagination;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $editandoId = null;

    public string $busca = '';

    public string $papel = 'todos';

    /** Situação cadastral quando diferente de ATIVA. Avisa, não bloqueia. */
    public ?string $avisoSituacao = null;

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('pessoa.ver');
        $this->limparFormulario();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function pessoas()
    {
        return Pessoa::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->when($this->papel === 'clientes', fn ($q) => $q->clientes())
            ->when($this->papel === 'fornecedores', fn ($q) => $q->fornecedores())
            ->when($this->papel === 'transportadoras', fn ($q) => $q->transportadoras())
            ->when($this->busca !== '', function ($q) {
                $termo = '%'.$this->busca.'%';
                $q->where(fn ($s) => $s->where('razao_social', 'like', $termo)
                    ->orWhere('nome_fantasia', 'like', $termo)
                    ->orWhere('documento', 'like', $termo));
            })
            ->orderBy('razao_social')
            ->paginate(15);
    }

    public function limparFormulario(): void
    {
        $this->reset('editandoId', 'avisoSituacao');
        $this->resetErrorBag();

        $this->form = [
            'tipo_pessoa' => TipoPessoa::Juridica->value,
            'documento' => '',
            'razao_social' => '',
            'nome_fantasia' => '',
            'ind_ie_dest' => IndIEDest::Contribuinte->value,
            'inscricao_estadual' => '',
            'inscricao_municipal' => '',
            'suframa' => '',
            'consumidor_final' => false,
            'logradouro' => '',
            'numero' => '',
            'complemento' => '',
            'bairro' => '',
            'codigo_municipio' => '',
            'municipio' => '',
            'uf' => '',
            'cep' => '',
            'telefone' => '',
            'email' => '',
            'observacoes' => '',
            'e_cliente' => true,
            'e_fornecedor' => false,
            'e_transportadora' => false,
            'placa' => '',
            'placa_uf' => '',
            'rntc' => '',
            'ativo' => true,
        ];
    }

    public function editar(int $id): void
    {
        $this->authorize('pessoa.ver');

        $pessoa = Pessoa::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->findOrFail($id);

        $this->editandoId = $pessoa->id;
        $this->avisoSituacao = null;
        $this->resetErrorBag();
        $this->form = array_merge($this->form, $pessoa->only(array_keys($this->form)));
        $this->form['tipo_pessoa'] = $pessoa->tipo_pessoa->value;
        $this->form['ind_ie_dest'] = $pessoa->ind_ie_dest->value;
    }

    public function buscarCnpj(ReceitaWsService $receita): void
    {
        $this->avisoSituacao = null;

        try {
            $dados = $receita->consultar((string) $this->form['documento']);
        } catch (RuntimeException $e) {
            $this->addError('documento', $e->getMessage());

            return;
        }

        $this->form['razao_social'] = $dados->razaoSocial;
        $this->form['nome_fantasia'] = $dados->nomeFantasia ?? '';
        $this->form['logradouro'] = $dados->logradouro ?? '';
        $this->form['numero'] = $dados->numero ?? '';
        $this->form['complemento'] = $dados->complemento ?? '';
        $this->form['bairro'] = $dados->bairro ?? '';
        $this->form['municipio'] = $dados->municipio ?? '';
        $this->form['uf'] = $dados->uf ?? '';
        $this->form['cep'] = $dados->cep ?? '';
        $this->form['telefone'] = $dados->telefone ?? '';
        $this->form['email'] = $dados->email ?? '';

        if (! $dados->ativa) {
            $this->avisoSituacao = $dados->situacao;
        }

        // A ReceitaWS não devolve o código IBGE, que a NF-e exige. Ele vem do
        // ViaCEP, disparado com o CEP que acabou de chegar.
        if ($this->form['cep'] !== '') {
            $this->buscarCep(app(ViaCepService::class));
        }
    }

    public function buscarCep(ViaCepService $viaCep): void
    {
        try {
            $dados = $viaCep->consultar((string) $this->form['cep']);
        } catch (RuntimeException $e) {
            $this->addError('cep', $e->getMessage());

            return;
        }

        $this->form['logradouro'] = $dados->logradouro ?: $this->form['logradouro'];
        $this->form['bairro'] = $dados->bairro ?: $this->form['bairro'];
        $this->form['municipio'] = $dados->municipio ?: $this->form['municipio'];
        $this->form['uf'] = $dados->uf ?: $this->form['uf'];

        // Preserva o que já estava, em vez de apagar: o ViaCEP nem sempre
        // devolve o IBGE, e antes um CEP sem IBGE zerava um código correto
        // que a pessoa tinha acabado de digitar.
        $this->form['codigo_municipio'] = $dados->codigoIbge ?: $this->form['codigo_municipio'];

        $this->completarCodigoIbge();
    }

    /**
     * Resolve o código IBGE pela tabela oficial, a partir de município e UF.
     *
     * A tabela vem de `fiscal:importar-municipios` e é a própria lista do
     * IBGE, então ela manda sempre que souber responder: é o que garante o
     * par consistente que a decisão de 10/09 protege, inclusive no caso em
     * que a consulta de CNPJ troca o município e deixaria para trás o código
     * da cidade anterior.
     *
     * Quando a tabela não sabe, seja porque está vazia ou porque o nome não
     * casa, o que estiver no campo permanece. É aí que digitar na mão vale.
     */
    private function completarCodigoIbge(): void
    {
        $municipio = trim((string) $this->form['municipio']);
        $uf = strtoupper(trim((string) $this->form['uf']));

        if ($municipio === '' || $uf === '') {
            return;
        }

        $codigo = DB::table('municipios')
            ->where('uf', $uf)
            ->whereRaw('LOWER(nome) = ?', [Str::lower($municipio)])
            ->value('codigo_ibge');

        if ($codigo !== null) {
            $this->form['codigo_municipio'] = (string) $codigo;
        }
    }

    public function salvar(ValidarPessoa $validador): void
    {
        $this->authorize('pessoa.gerenciar');

        // Quem digitou município e UF na mão, sem passar pelo CEP, também
        // ganha o código pela tabela oficial antes da validação cobrar.
        $this->completarCodigoIbge();

        $dados = $this->form;
        $dados['inscricao_estadual'] = blank($dados['inscricao_estadual']) ? null : $dados['inscricao_estadual'];

        $validador->validar($dados);

        $dados['emitente_id'] = $this->emitente->getKey();

        if ($this->editandoId !== null) {
            Pessoa::query()
                ->where('emitente_id', $this->emitente->getKey())
                ->findOrFail($this->editandoId)
                ->update($dados);
        } else {
            Pessoa::create($dados);
        }

        unset($this->pessoas);
        $this->limparFormulario();
        session()->flash('sucesso', 'Cadastro salvo.');
    }

    public function render()
    {
        return view('livewire.pessoas.cadastro');
    }
}
