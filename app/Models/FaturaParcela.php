<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use App\Support\Pix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * O título de contas a receber.
 *
 * Não usa `DoTenantViaEmitente` porque não tem `emitente_id`: o escopo vem da
 * fatura, que já é escopada. Duplicar a coluna criaria duas verdades sobre de
 * quem é a parcela.
 */
class FaturaParcela extends Model
{
    use Auditavel;

    protected $table = 'fatura_parcelas';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'pendente'];

    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'numero' => 'integer',
            'vencimento' => 'date',
            'pago_em' => 'datetime',
        ];
    }

    /**
     * Gera e grava o BR Code desta parcela, se o emitente tiver chave.
     *
     * Grava em vez de calcular na exibição porque o payload é o que o cliente
     * recebeu. Se a chave mudar amanhã, a cobrança já enviada continua valendo
     * o que valia quando foi enviada.
     *
     * Falha de cobrança não derruba o lançamento: o título é o que não pode
     * faltar, e a cobrança é conveniência em cima dele.
     */
    public function gerarCobrancaPix(): void
    {
        $emitente = $this->fatura?->emitente;

        if ($emitente === null || blank($emitente->chave_pix)) {
            return;
        }

        try {
            $this->forceFill(['pix_payload' => Pix::payload(
                chave: (string) $emitente->chave_pix,
                nomeRecebedor: (string) ($emitente->nome_fantasia ?: $emitente->razao_social),
                cidadeRecebedor: (string) $emitente->municipio,
                centavos: (int) $this->valor_centavos,
                identificador: Pix::identificador((string) $this->fatura_id, (int) $this->numero),
            )])->save();
        } catch (InvalidArgumentException) {
            // Chave malformada, cidade vazia: o título fica, a cobrança não.
        }
    }

    public function estaPendente(): bool
    {
        return $this->status === 'pendente';
    }

    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(ContaFinanceira::class, 'conta_financeira_id');
    }
}
