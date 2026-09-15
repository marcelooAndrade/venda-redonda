<?php

namespace App\Models;

use App\Enums\EtapaCrm;
use Illuminate\Database\Eloquent\Model;

/**
 * Contato do funil de negócio, na área administrativa. Sem tenant, sem
 * emitente: é dado do dono do produto, não do sistema fiscal.
 *
 * @property EtapaCrm $etapa
 */
class ContatoCrm extends Model
{
    protected $table = 'contatos_crm';

    protected $attributes = [
        'etapa' => 'base',
    ];

    protected $fillable = ['nome', 'empresa', 'telefone', 'email', 'etapa', 'observacao'];

    protected function casts(): array
    {
        return ['etapa' => EtapaCrm::class];
    }
}
