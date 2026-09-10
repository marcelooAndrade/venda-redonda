<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Registra criação, alteração e exclusão em audit_logs.
 *
 * Campo oculto na serialização é automaticamente omitido do registro.
 * Assim a senha do certificado A1 nunca chega ao log, sem depender de
 * alguém lembrar de listá-la aqui.
 */
trait Auditavel
{
    public static function bootAuditavel(): void
    {
        static::created(fn ($model) => $model->registrarAuditoria('criado'));
        static::updated(fn ($model) => $model->registrarAuditoria('alterado'));
        static::deleted(fn ($model) => $model->registrarAuditoria('excluido'));
    }

    protected function registrarAuditoria(string $evento): void
    {
        $alteracoes = null;

        if ($evento === 'alterado') {
            $alteracoes = [];

            foreach ($this->getChanges() as $campo => $novo) {
                if (in_array($campo, [static::CREATED_AT, static::UPDATED_AT], true)) {
                    continue;
                }

                $sensivel = $this->campoSensivelAuditoria($campo);

                $alteracoes[$campo] = [
                    'de' => $sensivel ? '[omitido]' : $this->getOriginal($campo),
                    'para' => $sensivel ? '[omitido]' : $novo,
                ];
            }

            if ($alteracoes === []) {
                return;
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'evento' => $evento,
            'auditavel_type' => $this->getMorphClass(),
            'auditavel_id' => $this->getKey(),
            'alteracoes' => $alteracoes,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255) ?: null,
        ]);
    }

    protected function campoSensivelAuditoria(string $campo): bool
    {
        return in_array($campo, $this->getHidden(), true);
    }
}
