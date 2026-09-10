<?php

namespace App\Models;

use App\Enums\Fiscal\AmbitoOperacao;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Natureza da operação: o que a nota representa e qual CFOP usar.
 *
 * O CFOP muda conforme o destino, então a natureza guarda um por âmbito.
 * Errar o CFOP é rejeição na SEFAZ ou problema na escrituração do cliente.
 */
class NaturezaOperacao extends Model
{
    use Auditavel, DoTenantViaEmitente;

    /**
     * Defaults também em memória, não só no banco: coluna booleana `true`
     * vem `null` num model recém instanciado, e `null` é falsy.
     */
    protected $attributes = [
        'ativo' => true,
        'movimenta_estoque' => true,
        'gera_financeiro' => true,
    ];

    protected $table = 'naturezas_operacao';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'movimenta_estoque' => 'boolean',
            'gera_financeiro' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function perfilFiscal(): BelongsTo
    {
        return $this->belongsTo(PerfilFiscal::class);
    }

    public function cfopPara(AmbitoOperacao $ambito): string
    {
        $cfop = match ($ambito) {
            AmbitoOperacao::Interna => $this->cfop_interno,
            AmbitoOperacao::Interestadual => $this->cfop_interestadual,
            AmbitoOperacao::Exterior => $this->cfop_exterior,
        };

        if (blank($cfop)) {
            throw new RuntimeException(
                "A natureza \"{$this->descricao}\" não tem CFOP cadastrado para operação "
                .mb_strtolower($ambito->rotulo()).'. Peça ao contador para completar o cadastro.'
            );
        }

        return $cfop;
    }

    /**
     * Devolução e nota complementar precisam apontar a nota original.
     * Desde 01/09/2026 isso é feito no grupo DFeReferenciado. Ver DF-002.
     */
    public function exigeReferencia(): bool
    {
        return in_array($this->fin_nfe, ['2', '4'], true);
    }

    public function entrada(): bool
    {
        return $this->tipo === '0';
    }
}
