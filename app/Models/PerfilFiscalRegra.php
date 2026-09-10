<?php

namespace App\Models;

use App\Enums\Fiscal\AmbitoOperacao;
use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerfilFiscalRegra extends Model
{
    use Auditavel;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ambito' => AmbitoOperacao::class,
            'vigente_de' => 'date',
            'vigente_ate' => 'date',
        ];
    }

    public function perfilFiscal(): BelongsTo
    {
        return $this->belongsTo(PerfilFiscal::class);
    }

    public function vigenteEm(\DateTimeInterface $data): bool
    {
        return $this->vigente_de->lessThanOrEqualTo($data)
            && ($this->vigente_ate === null || $this->vigente_ate->greaterThanOrEqualTo($data));
    }
}
