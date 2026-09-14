<?php

namespace App\Livewire\Usuarios;

use App\Models\Emitente;
use App\Models\User;
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
    public function mount(): void
    {
        $this->authorize('usuario.gerenciar');
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

    public function render()
    {
        return view('livewire.usuarios.cadastro');
    }
}
