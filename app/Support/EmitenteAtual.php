<?php

namespace App\Support;

use App\Models\Emitente;
use Illuminate\Auth\Access\AuthorizationException;

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
            $emitente = $user->emitentes()->whereKey($escolhido)->first();

            if ($emitente !== null) {
                return $emitente;
            }

            session()->forget(self::CHAVE);
        }

        return $user->emitentes()->orderBy('razao_social')->first();
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
