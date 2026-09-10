<?php

namespace App\Services\Fiscal;

use App\Models\EmitenteCertificado;
use App\Notifications\CertificadoVencendo;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa quem tem acesso ao emitente quando o certificado ativo se aproxima
 * do vencimento. Cada marco avisa uma vez só.
 */
class AlertaVencimentoCertificado
{
    /** @var array<int, int> */
    public const MARCOS = [30, 15, 7];

    public function executar(): int
    {
        $maiorMarco = max(self::MARCOS);
        $enviados = 0;

        EmitenteCertificado::query()
            ->with('emitente.users')
            ->where('ativo', true)
            ->whereBetween('valido_ate', [now(), now()->addDays($maiorMarco)->endOfDay()])
            ->each(function (EmitenteCertificado $certificado) use (&$enviados) {
                $marco = $this->marcoAtingido($certificado->diasParaVencer());

                if ($marco === null) {
                    return;
                }

                $jaAvisados = $certificado->alertas_enviados ?? [];

                if (in_array($marco, $jaAvisados, true)) {
                    return;
                }

                $destinatarios = $certificado->emitente->users;

                if ($destinatarios->isNotEmpty()) {
                    Notification::send(
                        $destinatarios,
                        new CertificadoVencendo($certificado, $certificado->diasParaVencer()),
                    );
                    $enviados++;
                }

                $certificado->forceFill([
                    'alertas_enviados' => [...$jaAvisados, $marco],
                ])->save();
            });

        return $enviados;
    }

    /**
     * O marco mais apertado que os dias restantes já cruzaram.
     *
     * A ordem crescente importa: faltando 15 dias, o certificado cruzou tanto
     * o marco de 30 quanto o de 15, e o que interessa avisar é o de 15. Sem
     * isso, o primeiro aviso consumiria o marco de 30 e os seguintes nunca
     * disparariam.
     */
    private function marcoAtingido(int $diasRestantes): ?int
    {
        if ($diasRestantes < 0) {
            return null;
        }

        $marcos = self::MARCOS;
        sort($marcos);

        foreach ($marcos as $marco) {
            if ($diasRestantes <= $marco) {
                return $marco;
            }
        }

        return null;
    }
}
