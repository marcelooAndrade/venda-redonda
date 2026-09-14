<?php

namespace App\Support;

use App\Models\Emitente;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Resolve qual emitente está em foco.
 *
 * Todo dado fiscal é isolado por emitente, então a escolha nunca confia na
 * sessão sozinha: o vínculo é reconferido a cada resolução. Assim uma sessão
 * adulterada, ou um vínculo revogado depois da escolha, não dá acesso a nada.
 */
class EmitenteAtual
{
    private const CHAVE = 'emitente_atual_id';

    public function resolver(): ?Emitente
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $escolhido = session()->get(self::CHAVE);

        if ($escolhido !== null) {
            $emitente = $this->consulta($user)->whereKey($escolhido)->first();

            if ($emitente !== null) {
                return $emitente;
            }

            session()->forget(self::CHAVE);
        }

        return $this->consulta($user)->orderBy('razao_social')->first();
    }

    /**
     * Com o host já tendo fixado um tenant (domínio próprio de um cliente),
     * o escopo natural de `Emitente` já restringe a busca a ele, e é isso
     * que impede a sessão de escapar para outra empresa ali. Sem tenant
     * fixado (domínio comum, onde todo cliente entra hoje), a busca
     * atravessa todas as empresas do login, do mesmo jeito que o painel de
     * empresas atravessa tenants para o dono do produto.
     *
     * @return BelongsToMany<Emitente, User>
     */
    private function consulta(User $user): BelongsToMany
    {
        return app(TenantAtual::class)->id() !== null
            ? $user->emitentes()
            : $user->emitentes()->withoutGlobalScope('tenant');
    }

    /**
     * Todas as empresas que este login alcança, para o seletor no topo.
     * Mesma regra de alcance do `resolver()`.
     *
     * @return \Illuminate\Support\Collection<int, Emitente>
     */
    public function alcancaveis(): \Illuminate\Support\Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        return $this->consulta($user)->with('tenant')->orderBy('razao_social')->get();
    }

    /**
     * @throws AuthorizationException
     */
    public function escolher(Emitente $emitente): void
    {
        $user = auth()->user();

        if (! $user || ! $user->podeAcessar($emitente)) {
            throw new AuthorizationException('Sem acesso a este emitente.');
        }

        session()->put(self::CHAVE, $emitente->getKey());
    }

    public function limpar(): void
    {
        session()->forget(self::CHAVE);
    }
}
