<?php

namespace App\Livewire\Produtos;

use App\Models\Emitente;
use App\Models\PerfilFiscal;
use App\Models\Produto;
use App\Services\Produtos\ValidarProduto;
use App\Support\EmitenteAtual;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.fiscal')]
#[Title('Produtos')]
class Cadastro extends Component
{
    use WithPagination;

    /** @var array<string, mixed> */
    public array $form = [];

    public ?int $editandoId = null;

    public string $busca = '';

    /** Descrição oficial do NCM digitado, buscada na tabela do Siscomex. */
    public ?string $ncmDescricao = null;

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('produto.ver');
        $this->limpar();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function perfis()
    {
        return PerfilFiscal::query()->where('ativo', true)->orderBy('nome')->get();
    }

    #[Computed]
    public function unidades()
    {
        return DB::table('unidades_medida')->orderBy('sigla')->get();
    }

    #[Computed]
    public function produtos()
    {
        return Produto::query()
            ->with('perfilFiscal')
            ->when($this->busca !== '', function ($q) {
                $termo = '%'.$this->busca.'%';
                $q->where(fn ($s) => $s->where('descricao', 'like', $termo)
                    ->orWhere('codigo', 'like', $termo)
                    ->orWhere('ncm', 'like', $termo)
                    ->orWhere('gtin', 'like', $termo));
            })
            ->orderBy('descricao')
            ->paginate(15);
    }

    public function limpar(): void
    {
        $this->reset('editandoId', 'ncmDescricao');
        $this->resetErrorBag();

        $this->form = [
            'codigo' => '', 'descricao' => '', 'gtin' => '', 'gtin_tributavel' => '',
            'ncm' => '', 'cest' => '', 'ex_tipi' => '',
            'unidade_comercial' => 'PC', 'unidade_tributavel' => 'PC', 'fator_conversao' => 1,
            'origem' => '0', 'perfil_fiscal_id' => '',
            'preco_venda' => '', 'custo' => '',
            'peso_liquido' => '', 'peso_bruto' => '',
            'estoque_minimo' => 0, 'controla_estoque' => true, 'ativo' => true,
        ];
    }

    /** Confirma o NCM na tabela oficial enquanto o usuário digita. */
    public function updatedFormNcm(string $valor): void
    {
        $ncm = preg_replace('/\D/', '', $valor);
        $this->ncmDescricao = strlen($ncm) !== 8
            ? null
            : DB::table('ncms')->where('codigo', $ncm)->where('valido_nfe', true)->value('descricao');
    }

    public function editar(int $id): void
    {
        $this->authorize('produto.ver');

        $produto = Produto::findOrFail($id);
        $this->editandoId = $produto->id;
        $this->resetErrorBag();
        $this->form = array_merge($this->form, $produto->only(array_keys($this->form)));
        $this->updatedFormNcm((string) $this->form['ncm']);
    }

    public function salvar(ValidarProduto $validador): void
    {
        $this->authorize('produto.gerenciar');

        $dados = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $dados['fator_conversao'] = $dados['fator_conversao'] ?? 1;

        $validador->validar($dados);

        if ($this->editandoId !== null) {
            Produto::findOrFail($this->editandoId)->update($dados);
        } else {
            Produto::create([...$dados, 'emitente_id' => $this->emitente->getKey()]);
        }

        unset($this->produtos);
        $this->limpar();
        session()->flash('sucesso', 'Produto salvo.');
    }

    public function render()
    {
        return view('livewire.produtos.cadastro');
    }
}
