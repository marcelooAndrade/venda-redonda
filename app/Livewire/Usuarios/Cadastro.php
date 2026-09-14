<?php

namespace App\Livewire\Usuarios;

use App\Models\Emitente;
use App\Models\User;
use App\Support\TenantAtual;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Quem tem acesso a esta empresa, e com qual perfil em cada emitente dela.
 *
 * A unidade é o perfil (`Perfil` enum), não permissão avulsa: decisão da
 * Fase 2, mantida. Um e-mail é uma pessoa em qualquer empresa: cadastrar um
 * e-mail já existente em outro lugar não cria conta nova, só anexa aquele
 * login a esta empresa. Ver
 * docs/superpowers/specs/2026-09-14-gestao-usuarios-design.md.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Usuários')]
class Cadastro extends Component
{
    /** @var array<string, string> */
    public array $form = ['name' => '', 'email' => '', 'senha' => ''];

    /** @var array<int, string> emitente_id => perfil (vazio = sem acesso) */
    public array $papeis = [];

    public ?int $editandoId = null;

    public bool $emailVerificado = false;

    public bool $contaExistente = false;

    public function mount(): void
    {
        $this->authorize('usuario.gerenciar');
        $this->prepararPapeisVazios();
    }

    /**
     * Os emitentes desta empresa. Toda a tela gira em torno desta lista:
     * é o que separa "usuário desta empresa" de "usuário de qualquer outra".
     *
     * @return Collection<int, Emitente>
     */
    #[Computed]
    public function emitentesDaEmpresa(): Collection
    {
        return Emitente::query()->orderBy('razao_social')->get();
    }

    /**
     * Usuários vinculados a pelo menos um emitente desta empresa.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function usuarios(): Collection
    {
        $idsDaEmpresa = $this->emitentesDaEmpresa->pluck('id');

        return User::query()
            ->whereHas('emitentes', fn ($q) => $q->whereIn('emitentes.id', $idsDaEmpresa))
            ->with(['emitentes' => fn ($q) => $q->whereIn('emitentes.id', $idsDaEmpresa)])
            ->orderBy('name')
            ->get();
    }

    private function prepararPapeisVazios(): void
    {
        $this->papeis = $this->emitentesDaEmpresa->mapWithKeys(fn (Emitente $e): array => [$e->id => ''])->all();
    }

    public function novoUsuario(): void
    {
        $this->reset('form', 'editandoId', 'emailVerificado', 'contaExistente');
        $this->prepararPapeisVazios();
    }

    public function verificarEmail(): void
    {
        $dados = $this->validate([
            'form.email' => ['required', 'email', 'max:254'],
        ], [], ['form.email' => 'e-mail'])['form'];

        $existente = User::withoutGlobalScope('tenant')->where('email', $dados['email'])->first();

        $this->emailVerificado = true;
        $this->contaExistente = $existente !== null;
        $this->editandoId = $existente?->id;

        if ($existente !== null) {
            $this->form['name'] = $existente->name;
        }
    }

    public function salvar(): void
    {
        $this->authorize('usuario.gerenciar');

        $dados = $this->validate([
            'form.email' => ['required', 'email', 'max:254'],
            'form.name' => $this->contaExistente ? ['nullable'] : ['required', 'string', 'min:2', 'max:160'],
            'form.senha' => $this->contaExistente ? ['nullable'] : ['required', 'string', 'min:8'],
        ], [], ['form.email' => 'e-mail', 'form.name' => 'nome', 'form.senha' => 'senha'])['form'];

        if (collect($this->papeis)->filter(fn (string $p): bool => $p !== '')->isEmpty()) {
            $this->addError('papeis', 'Escolha um perfil em pelo menos um emitente.');

            return;
        }

        $usuario = $this->contaExistente
            ? User::withoutGlobalScope('tenant')->findOrFail($this->editandoId)
            : User::create([
                'tenant_id' => app(TenantAtual::class)->id(),
                'name' => $dados['name'],
                'email' => $dados['email'],
                'password' => $dados['senha'],
                'email_verified_at' => now(),
            ]);

        foreach ($this->papeis as $emitenteId => $perfil) {
            if ($perfil === '') {
                $usuario->emitentes()->detach($emitenteId);

                continue;
            }

            $usuario->emitentes()->syncWithoutDetaching([$emitenteId]);
        }

        session()->flash('sucesso', 'Usuário salvo.');
        $this->novoUsuario();
        unset($this->usuarios);
    }

    public function render()
    {
        return view('livewire.usuarios.cadastro');
    }
}
