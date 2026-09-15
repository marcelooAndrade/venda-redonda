<?php

namespace App\Livewire\Produto;

use App\Models\ApiCliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Quem se cadastrou no Nodo. Mesma regra do painel de Empresas: só abre
 * para o dono do produto, e não tem relação nenhuma com o tenant do
 * sistema fiscal — `ApiCliente` não tem escopo de tenant, então não
 * precisa de `withoutGlobalScope` nenhum aqui.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Clientes Nodo')]
class NodoClientes extends Component
{
    use WithPagination;

    /** Token mostrado uma vez só, depois de reemitido. Nunca persistido. */
    public ?string $tokenReemitido = null;

    public ?int $clienteDoTokenReemitido = null;

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
     * Revoga os tokens existentes e emite um novo, com a mesma habilidade
     * do cadastro público. Precisa ser reemitido, e não recuperado: o
     * Sanctum guarda só o hash, o texto puro nunca fica no banco.
     */
    public function reemitirToken(int $clienteId): void
    {
        $this->authorize('produto.administrar');

        $cliente = ApiCliente::findOrFail($clienteId);
        $cliente->tokens()->delete();

        $this->tokenReemitido = $cliente->createToken($cliente->nome, ['whatsapp'])->plainTextToken;
        $this->clienteDoTokenReemitido = $cliente->id;
    }

    public function fecharToken(): void
    {
        $this->tokenReemitido = null;
        $this->clienteDoTokenReemitido = null;
    }

    public function render()
    {
        return view('livewire.produto.nodo-clientes');
    }
}
