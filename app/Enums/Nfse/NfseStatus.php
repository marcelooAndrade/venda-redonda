<?php

namespace App\Enums\Nfse;

/**
 * Ciclo de vida da NFS-e.
 *
 * Não há rascunho: a nota só existe a partir do momento em que o RPS foi
 * reservado e o envio começou. Rejeitada e erro não são terminais: a nova
 * tentativa reaproveita o mesmo RPS e o mesmo registro.
 */
enum NfseStatus: string
{
    case Processando = 'processando';
    case Autorizada = 'autorizada';
    case Rejeitada = 'rejeitada';
    case Erro = 'erro';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Processando => 'Processando',
            self::Autorizada => 'Autorizada',
            self::Rejeitada => 'Rejeitada',
            self::Erro => 'Erro de comunicação',
            self::Cancelada => 'Cancelada',
        };
    }

    public function classesBadge(): string
    {
        return match ($this) {
            self::Processando => 'bg-steel-100 text-steel-800',
            self::Autorizada => 'bg-success-100 text-success-800',
            self::Rejeitada => 'bg-danger-100 text-danger-800',
            self::Erro => 'bg-ember-100 text-ember-800',
            self::Cancelada => 'bg-graphite-800 text-white',
        };
    }

    /** Reemitir reaproveita o RPS e o registro. */
    public function permiteNovaTentativa(): bool
    {
        return in_array($this, [self::Rejeitada, self::Erro], true);
    }

    /** Existe NFS-e no SIGISS, então existe PDF e XML autorizado. */
    public function temDocumento(): bool
    {
        return in_array($this, [self::Autorizada, self::Cancelada], true);
    }
}
