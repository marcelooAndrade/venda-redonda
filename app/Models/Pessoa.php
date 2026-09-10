<?php

namespace App\Models;

use App\Enums\Fiscal\IndIEDest;
use App\Enums\Fiscal\TipoPessoa;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\DoTenantViaEmitente;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pessoa extends Model
{
    use Auditavel, DoTenantViaEmitente, HasFactory;

    protected $fillable = [
        'emitente_id', 'tipo_pessoa', 'documento', 'razao_social', 'nome_fantasia',
        'ind_ie_dest', 'inscricao_estadual', 'inscricao_municipal', 'suframa',
        'consumidor_final', 'logradouro', 'numero', 'complemento', 'bairro',
        'codigo_municipio', 'municipio', 'uf', 'cep', 'telefone', 'email',
        'observacoes', 'e_cliente', 'e_fornecedor', 'e_transportadora',
        'placa', 'placa_uf', 'rntc', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_pessoa' => TipoPessoa::class,
            'ind_ie_dest' => IndIEDest::class,
            'consumidor_final' => 'boolean',
            'e_cliente' => 'boolean',
            'e_fornecedor' => 'boolean',
            'e_transportadora' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // O documento é guardado sempre normalizado, para a unicidade por
        // emitente funcionar independente de como foi digitado.
        static::saving(function (Pessoa $pessoa): void {
            if ($pessoa->isDirty('documento')) {
                $pessoa->documento = $pessoa->tipo_pessoa === TipoPessoa::Fisica
                    ? Documento::normalizarCpf((string) $pessoa->documento)
                    : Documento::normalizarCnpj((string) $pessoa->documento);
            }

            if ($pessoa->isDirty('cep')) {
                $pessoa->cep = preg_replace('/\D/', '', (string) $pessoa->cep);
            }
        });
    }

    public function emitente(): BelongsTo
    {
        return $this->belongsTo(Emitente::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(PessoaEmail::class);
    }

    /** @param Builder<Pessoa> $query */
    public function scopeClientes(Builder $query): Builder
    {
        return $query->where('e_cliente', true);
    }

    /** @param Builder<Pessoa> $query */
    public function scopeFornecedores(Builder $query): Builder
    {
        return $query->where('e_fornecedor', true);
    }

    /** @param Builder<Pessoa> $query */
    public function scopeTransportadoras(Builder $query): Builder
    {
        return $query->where('e_transportadora', true);
    }

    public function documentoFormatado(): string
    {
        $d = $this->documento;

        return $this->tipo_pessoa === TipoPessoa::Fisica
            ? preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $d)
            : preg_replace('/(.{2})(.{3})(.{3})(.{4})(.{2})/', '$1.$2.$3/$4-$5', $d);
    }
}
