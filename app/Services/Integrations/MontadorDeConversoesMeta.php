<?php

namespace App\Services\Integrations;

use App\Models\Emitente;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Monta o evento de conversão do cadastro para a Conversions API do Meta.
 *
 * E-mail, telefone e nome vão hasheados (SHA-256), como a especificação de
 * "advanced matching" do Meta exige: são os dados que permitem casar o
 * evento com a pessoa sem mandar o dado em claro pela rede.
 */
class MontadorDeConversoesMeta
{
    /**
     * @param  array{fbp?: ?string, fbc?: ?string, ip?: ?string, user_agent?: ?string}  $sinaisDoNavegador
     * @return array<string, mixed>
     */
    public function paraCadastro(User $user, Emitente $emitente, string $eventId, string $eventSourceUrl, array $sinaisDoNavegador = []): array
    {
        $userData = [
            'em' => [$this->hash(mb_strtolower(trim($user->email)))],
            'external_id' => [$this->hash($eventId)],
        ];

        if (filled($emitente->telefone)) {
            $userData['ph'] = [$this->hash($this->normalizarTelefone($emitente->telefone))];
        }

        [$primeiroNome, $sobrenome] = $this->separarNome($user->name);

        if ($primeiroNome !== '') {
            $userData['fn'] = [$this->hash($primeiroNome)];
        }

        if ($sobrenome !== '') {
            $userData['ln'] = [$this->hash($sobrenome)];
        }

        // fbp/fbc/ip/user_agent vêm crus, não hasheados: são sinais de rede e
        // de navegador, não dado pessoal do "advanced matching".
        foreach (['fbp' => 'fbp', 'fbc' => 'fbc', 'ip' => 'client_ip_address', 'user_agent' => 'client_user_agent'] as $origem => $campo) {
            if (filled($sinaisDoNavegador[$origem] ?? null)) {
                $userData[$campo] = $sinaisDoNavegador[$origem];
            }
        }

        return [
            'event_name' => 'CompleteRegistration',
            'event_time' => now()->timestamp,
            'event_id' => $eventId,
            'event_source_url' => $eventSourceUrl,
            'action_source' => 'website',
            'user_data' => $userData,
        ];
    }

    private function hash(string $valor): string
    {
        return hash('sha256', $valor);
    }

    /**
     * Prefixa DDI 55 quando o telefone só tem DDD e número (10 ou 11
     * dígitos), do jeito que o Meta espera receber o telefone brasileiro.
     */
    private function normalizarTelefone(string $valor): string
    {
        $digitos = preg_replace('/\D/', '', $valor) ?? '';

        return in_array(strlen($digitos), [10, 11], true) ? "55{$digitos}" : $digitos;
    }

    /** @return array{0: string, 1: string} */
    private function separarNome(string $nome): array
    {
        $semAcento = preg_replace('/[^a-z\s]/', ' ', Str::ascii(mb_strtolower(trim($nome)))) ?? '';
        $partes = array_values(array_filter(explode(' ', $semAcento)));

        return [
            $partes[0] ?? '',
            count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : '',
        ];
    }
}
