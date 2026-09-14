<?php

namespace App\Services\Fiscal;

use App\Enums\Fiscal\EstadoSefaz;
use App\Models\Emitente;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Mantém a última situação conhecida do serviço da SEFAZ por emitente.
 *
 * A tela nunca consulta a SEFAZ: a consulta usa o certificado, leva segundos
 * e a SEFAZ trata repetição sem necessidade como consumo indevido. Quem
 * consulta é o comando agendado, e a tela lê o que ele guardou.
 */
class MonitorSefaz
{
    /**
     * O serviço de status é conferência, não monitoramento. Seis consultas
     * por hora por emitente é o que um indicador precisa, e fica longe do
     * que a SEFAZ penaliza.
     */
    public const INTERVALO_MINUTOS = 10;

    /**
     * Três intervalos sem consulta nova e a leitura some. Um indicador que
     * mostra "em operação" de duas horas atrás está mentindo.
     */
    public const VALIDADE_MINUTOS = 30;

    public function __construct(
        private readonly SefazGateway $gateway,
        private readonly CertificateService $certificados,
    ) {}

    /**
     * Por ambiente, porque homologação e produção são autorizadores
     * distintos: virar para produção não pode herdar a leitura de homologação.
     */
    public static function chave(Emitente $emitente): string
    {
        return "sefaz:situacao:{$emitente->getKey()}:{$emitente->ambiente->tpAmb()}";
    }

    /** O que a tela mostra. Nunca sai para a rede. */
    public function situacao(Emitente $emitente): SituacaoSefaz
    {
        $guardada = Cache::get(self::chave($emitente));

        if (is_array($guardada)) {
            return SituacaoSefaz::fromArray($guardada);
        }

        if (! $this->temCertificadoValido($emitente)) {
            return $this->semCertificado();
        }

        return new SituacaoSefaz(
            EstadoSefaz::SemConsulta,
            'A situação é consultada a cada '.self::INTERVALO_MINUTOS.' minutos.',
        );
    }

    /** Consulta a SEFAZ com o certificado do emitente e guarda o resultado. */
    public function consultar(Emitente $emitente): SituacaoSefaz
    {
        if (! $this->temCertificadoValido($emitente)) {
            return $this->semCertificado();
        }

        $situacao = $this->perguntar($emitente);

        Cache::put(self::chave($emitente), $situacao->toArray(), now()->addMinutes(self::VALIDADE_MINUTOS));

        return $situacao;
    }

    /**
     * Roda fora de requisição, então não há tenant resolvido e o escopo
     * global devolveria lista vazia. O `withoutGlobalScope` é deliberado.
     */
    public function consultarTodos(): int
    {
        $total = 0;

        Emitente::query()
            ->withoutGlobalScope('tenant')
            ->where('ativo', true)
            ->whereHas('certificados', fn ($q) => $q->where('ativo', true)->where('valido_ate', '>', now()))
            ->each(function (Emitente $emitente) use (&$total): void {
                $this->consultar($emitente);
                $total++;
            });

        return $total;
    }

    private function perguntar(Emitente $emitente): SituacaoSefaz
    {
        try {
            $resposta = $this->gateway->statusServico($emitente);
        } catch (ValidationException $e) {
            // O registro existe mas o certificado não abre: arquivo sumiu da
            // área privada, ou a senha não confere.
            return new SituacaoSefaz(
                EstadoSefaz::SemCertificado,
                collect($e->errors())->flatten()->implode(' '),
                null,
                now()->toImmutable(),
            );
        } catch (Throwable $e) {
            return new SituacaoSefaz(
                EstadoSefaz::SemResposta,
                'Sem resposta da SEFAZ: '.$e->getMessage(),
                null,
                now()->toImmutable(),
            );
        }

        return new SituacaoSefaz(
            $resposta->cStat === '107' ? EstadoSefaz::Operando : EstadoSefaz::Paralisada,
            "cStat {$resposta->cStat}: {$resposta->xMotivo}.",
            $resposta->cStat,
            now()->toImmutable(),
        );
    }

    private function temCertificadoValido(Emitente $emitente): bool
    {
        $ativo = $this->certificados->ativo($emitente);

        return $ativo !== null && ! $ativo->vencido();
    }

    private function semCertificado(): SituacaoSefaz
    {
        return new SituacaoSefaz(
            EstadoSefaz::SemCertificado,
            // Sem nomear a tela: o Contador vê este texto e não tem o item de menu.
            'Envie o certificado A1 do emitente para consultar a SEFAZ.',
        );
    }
}
