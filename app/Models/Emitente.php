<?php

namespace App\Models;

use App\Enums\Fiscal\Ambiente;
use App\Models\Concerns\Auditavel;
use Database\Factories\EmitenteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Emitente extends Model
{
    /** @use HasFactory<EmitenteFactory> */
    use Auditavel, HasFactory;

    /**
     * O ambiente nunca é preenchível em massa. Emitente nasce em homologação
     * e só vai para produção por ação própria, auditada. Regra 3 do projeto.
     */
    protected $attributes = [
        'ambiente' => 'homologacao',
    ];

    protected $fillable = [
        'razao_social',
        'nome_fantasia',
        'cnpj',
        'inscricao_estadual',
        'inscricao_municipal',
        'crt',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ambiente' => Ambiente::class,
            'ativo' => 'boolean',
        ];
    }
}
