<?php

namespace App\Services\Fiscal;

use App\Enums\Fiscal\EstadoSefaz;
use Carbon\CarbonImmutable;

/**
 * Resultado de uma consulta ao serviço de status, pronto para o indicador.
 *
 * Vai para o cache como array, e não como objeto: o cache em banco
 * desserializa com `allowed_classes: false`, e um objeto guardado voltaria
 * como `__PHP_Incomplete_Class`. Mesma armadilha da consulta de CNPJ.
 */
readonly class SituacaoSefaz
{
    public function __construct(
        public EstadoSefaz $estado,
        public string $detalhe,
        public ?string $cStat = null,
        public ?CarbonImmutable $consultadoEm = null,
    ) {}

    /** "SEFAZ-SP em operação". Sem UF no cadastro, só "SEFAZ". */
    public function rotulo(?string $uf): string
    {
        $autorizador = $uf ? "SEFAZ-{$uf}" : 'SEFAZ';

        return "{$autorizador} {$this->estado->rotulo()}";
    }

    /** O detalhe mais a hora da consulta, para o title do indicador. */
    public function descricao(): string
    {
        if ($this->consultadoEm === null) {
            return $this->detalhe;
        }

        $hora = $this->consultadoEm->setTimezone('America/Sao_Paulo')->format('H:i');

        return "{$this->detalhe} Consultado às {$hora}.";
    }

    /** @return array{estado: string, detalhe: string, cStat: string|null, consultadoEm: string|null} */
    public function toArray(): array
    {
        return [
            'estado' => $this->estado->value,
            'detalhe' => $this->detalhe,
            'cStat' => $this->cStat,
            'consultadoEm' => $this->consultadoEm?->toIso8601String(),
        ];
    }

    /**
     * O que vem do cache é `mixed`: cada campo é conferido antes de virar
     * tipo, em vez de assumir a forma que `toArray` gravou.
     *
     * @param  array<string, mixed>  $dados
     */
    public static function fromArray(array $dados): self
    {
        $cStat = $dados['cStat'] ?? null;
        $consultadoEm = $dados['consultadoEm'] ?? null;

        return new self(
            EstadoSefaz::from(is_string($dados['estado'] ?? null) ? $dados['estado'] : EstadoSefaz::SemConsulta->value),
            is_string($dados['detalhe'] ?? null) ? $dados['detalhe'] : '',
            is_string($cStat) ? $cStat : null,
            is_string($consultadoEm) ? CarbonImmutable::parse($consultadoEm) : null,
        );
    }
}
