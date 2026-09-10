<?php

namespace App\Notifications;

use App\Models\EmitenteCertificado;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificadoVencendo extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly EmitenteCertificado $certificado,
        public readonly int $diasRestantes,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $emitente = $this->certificado->emitente;
        $vencimento = $this->certificado->valido_ate->format('d/m/Y');

        return (new MailMessage)
            ->subject("Certificado A1 vence em {$this->diasRestantes} dias: {$emitente->razao_social}")
            ->greeting('Certificado digital perto do vencimento')
            ->line("O certificado A1 de {$emitente->razao_social} vence em {$vencimento}, daqui a {$this->diasRestantes} dias.")
            ->line('Sem certificado válido o sistema não consegue assinar nem transmitir NF-e.')
            ->action('Abrir certificados', url('/certificados'))
            ->line('Providencie a renovação junto à sua certificadora antes dessa data.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'certificado_vencendo',
            'emitente_id' => $this->certificado->emitente_id,
            'certificado_id' => $this->certificado->id,
            'dias_restantes' => $this->diasRestantes,
            'vence_em' => $this->certificado->valido_ate->toDateString(),
        ];
    }
}
