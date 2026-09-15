<?php

namespace App\Livewire\Produto;

use App\Enums\ModuloApi;
use App\Models\ApiCliente;
use App\Models\Pessoa;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Clientes do Nodo. Mesma regra do painel de Empresas: só abre para o dono
 * do produto, e não tem relação nenhuma com o tenant do sistema fiscal —
 * `ApiCliente` não tem escopo de tenant, então não precisa de
 * `withoutGlobalScope` nenhum aqui.
 *
 * Cadastro não é público: nasce aqui, com o admin escolhendo os módulos na
 * hora. Cada módulo é pago em separado, decisão de negócio — o admin decide
 * quem usa o quê, não a pessoa que se cadastra sozinha.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Clientes Nodo')]
class NodoClientes extends Component
{
    use WithPagination;

    public bool $formularioAberto = false;

    /** Busca por destinatário já cadastrado no sistema fiscal, para não digitar de novo. */
    public string $buscaDestinatario = '';

    public string $novoNome = '';

    public string $novoEmail = '';

    /** @var array<int, string> */
    public array $novosModulos = [];

    /** Id do cliente com a edição de módulos aberta, um por vez. */
    public ?int $editandoModulosDe = null;

    /** @var array<int, string> */
    public array $modulosEmEdicao = [];

    /** Token mostrado uma vez só, no cadastro ou ao reemitir. Nunca persistido. */
    public ?string $tokenRevelado = null;

    public ?int $clienteDoTokenRevelado = null;

    public function mount(): void
    {
        $this->authorize('produto.administrar');
    }

    /** @return LengthAwarePaginator<int, ApiCliente> */
    #[Computed]
    public function clientes(): LengthAwarePaginator
    {
        return ApiCliente::query()
            ->with('whatsappInstancia')
            ->orderByDesc('created_at')
            ->paginate(50);
    }

    /**
     * Destinatários marcados como cliente, de qualquer emitente — é tela de
     * dono do produto, então atravessa tenant como /empresas já faz.
     * Preenche o formulário em vez de criar cadastro duplicado: a mesma
     * empresa já existe como destinatário em algum emitente do sistema
     * fiscal na maioria dos casos reais.
     *
     * @return Collection<int, Pessoa>
     */
    #[Computed]
    public function resultadosBusca(): Collection
    {
        $termo = trim($this->buscaDestinatario);

        if (mb_strlen($termo) < 2) {
            return new Collection;
        }

        return Pessoa::query()
            ->withoutGlobalScope('tenant')
            ->where('e_cliente', true)
            ->where(fn ($q) => $q
                ->where('razao_social', 'like', "%{$termo}%")
                ->orWhere('nome_fantasia', 'like', "%{$termo}%")
                ->orWhere('documento', 'like', "%{$termo}%"))
            ->with('emitente')
            ->limit(10)
            ->get();
    }

    public function selecionarDestinatario(int $pessoaId): void
    {
        $pessoa = Pessoa::query()->withoutGlobalScope('tenant')->findOrFail($pessoaId);

        $this->novoNome = $pessoa->nome_fantasia ?: $pessoa->razao_social;
        $this->novoEmail = (string) $pessoa->email;
        $this->buscaDestinatario = '';
    }

    public function criarCliente(): void
    {
        $this->authorize('produto.administrar');

        $dados = $this->validate([
            'novoNome' => ['required', 'string', 'min:2', 'max:160'],
            'novoEmail' => ['required', 'string', 'email', 'max:254', Rule::unique(ApiCliente::class, 'email')],
            'novosModulos' => ['array'],
            'novosModulos.*' => [Rule::enum(ModuloApi::class)],
        ], attributes: ['novoNome' => 'nome', 'novoEmail' => 'e-mail']);

        $cliente = ApiCliente::create([
            'nome' => $dados['novoNome'],
            'email' => $dados['novoEmail'],
            'modulos' => $dados['novosModulos'],
        ]);

        $this->tokenRevelado = $cliente->createToken($cliente->nome)->plainTextToken;
        $this->clienteDoTokenRevelado = $cliente->id;

        $this->reset(['novoNome', 'novoEmail', 'novosModulos', 'formularioAberto', 'buscaDestinatario']);
        unset($this->clientes);
    }

    /**
     * Revoga os tokens existentes e emite um novo. Precisa ser reemitido, e
     * não recuperado: o Sanctum guarda só o hash, o texto puro nunca fica
     * no banco. O token em si não carrega módulo nenhum — reemitir não
     * muda o que o cliente pode usar, só troca a credencial.
     */
    public function reemitirToken(int $clienteId): void
    {
        $this->authorize('produto.administrar');

        $cliente = ApiCliente::findOrFail($clienteId);
        $cliente->tokens()->delete();

        $this->tokenRevelado = $cliente->createToken($cliente->nome)->plainTextToken;
        $this->clienteDoTokenRevelado = $cliente->id;
    }

    public function fecharToken(): void
    {
        $this->tokenRevelado = null;
        $this->clienteDoTokenRevelado = null;
    }

    public function iniciarEdicaoModulos(int $clienteId): void
    {
        $this->authorize('produto.administrar');

        $cliente = ApiCliente::findOrFail($clienteId);
        $this->editandoModulosDe = $clienteId;
        $this->modulosEmEdicao = $cliente->modulos ?? [];
    }

    public function cancelarEdicaoModulos(): void
    {
        $this->editandoModulosDe = null;
        $this->modulosEmEdicao = [];
    }

    public function salvarModulos(int $clienteId): void
    {
        $this->authorize('produto.administrar');

        $dados = $this->validate([
            'modulosEmEdicao' => ['array'],
            'modulosEmEdicao.*' => [Rule::enum(ModuloApi::class)],
        ]);

        ApiCliente::findOrFail($clienteId)->update(['modulos' => $dados['modulosEmEdicao']]);

        $this->cancelarEdicaoModulos();
        unset($this->clientes);
    }

    public function render()
    {
        return view('livewire.produto.nodo-clientes');
    }
}
