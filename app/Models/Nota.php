<?php

namespace App\Models;

use App\Enums\Fiscal\Ambiente;
use App\Enums\Fiscal\NFeStatus;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Nota extends Model
{
    use Auditavel, DoTenantViaEmitente;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'rascunho',
        'tipo' => '1',
        'fin_nfe' => '1',
        'tp_emis' => '1',
    ];

    protected function casts(): array
    {
        return [
            'status' => NFeStatus::class,
            'ambiente' => Ambiente::class,
            'data_emissao' => 'datetime',
            'data_saida' => 'datetime',
            'autorizada_em' => 'datetime',
            'contingencia_em' => 'datetime',
            'consumidor_final' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // XML autorizado é imutável. Depois da autorização, a nota só muda
        // por evento: cancelamento ou carta de correção.
        static::updating(function (Nota $nota): void {
            if (! $nota->getOriginal('status') instanceof NFeStatus) {
                return;
            }

            $anterior = $nota->getOriginal('status');

            if ($anterior === NFeStatus::Autorizada && $nota->isDirty($nota->camposImutaveis())) {
                throw new RuntimeException(
                    'Nota autorizada não pode ser alterada. Use cancelamento ou carta de correção.'
                );
            }
        });
    }

    /** @return array<int, string> */
    public function camposImutaveis(): array
    {
        return [
            'numero', 'serie', 'chave_acesso', 'pessoa_id', 'valor_nota',
            'valor_produtos', 'data_emissao', 'ambiente',
        ];
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
    }

    public function transportadora(): BelongsTo
    {
        return $this->belongsTo(Pessoa::class, 'transportadora_id');
    }

    public function naturezaOperacao(): BelongsTo
    {
        return $this->belongsTo(NaturezaOperacao::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NotaItem::class)->orderBy('numero');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(NotaPagamento::class);
    }

    public function duplicatas(): HasMany
    {
        return $this->hasMany(NotaDuplicata::class)->orderBy('vencimento');
    }

    public function volumes(): HasMany
    {
        return $this->hasMany(NotaVolume::class);
    }

    public function referencias(): HasMany
    {
        return $this->hasMany(NotaReferencia::class);
    }

    public function arquivos(): HasMany
    {
        return $this->hasMany(NotaArquivo::class)->latest('id');
    }

    public function editavel(): bool
    {
        return in_array($this->status, [NFeStatus::Rascunho, NFeStatus::Rejeitada], true);
    }

    public function numeroFormatado(): string
    {
        return $this->numero === null
            ? 'sem número'
            : number_format($this->numero, 0, '', '.').'/'.$this->serie;
    }
}
