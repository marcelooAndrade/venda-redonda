<?php

namespace App\Services\Fiscal;

use Illuminate\Support\Facades\Process;

/**
 * Usa o binário openssl com `-legacy` para reabrir o arquivo e reexportá-lo.
 *
 * Certificados A1 emitidos até alguns anos atrás vêm cifrados em RC2-40, que o
 * OpenSSL 3 desabilitou por padrão e recusa com `error:0308010C`. Sem isso o
 * usuário recebe um erro incompreensível para um certificado que é válido.
 */
class ConversorLegadoOpenssl implements ConversorLegado
{
    public function converter(string $pfx, string $senha): ?string
    {
        $entrada = tempnam(sys_get_temp_dir(), 'pfx_in_');
        $intermediario = tempnam(sys_get_temp_dir(), 'pfx_pem_');
        $saida = tempnam(sys_get_temp_dir(), 'pfx_out_');

        try {
            file_put_contents($entrada, $pfx);

            $extrair = Process::timeout(30)->run([
                'openssl', 'pkcs12', '-legacy', '-in', $entrada, '-nodes',
                '-passin', 'pass:'.$senha, '-out', $intermediario,
            ]);

            if ($extrair->failed() || filesize($intermediario) === 0) {
                return null;
            }

            $exportar = Process::timeout(30)->run([
                'openssl', 'pkcs12', '-export', '-in', $intermediario,
                '-passout', 'pass:'.$senha, '-out', $saida,
            ]);

            if ($exportar->failed() || filesize($saida) === 0) {
                return null;
            }

            return file_get_contents($saida) ?: null;
        } catch (\Throwable) {
            return null;
        } finally {
            // O intermediário carrega a chave privada em texto claro.
            foreach ([$entrada, $intermediario, $saida] as $arquivo) {
                if (is_file($arquivo)) {
                    @unlink($arquivo);
                }
            }
        }
    }
}
