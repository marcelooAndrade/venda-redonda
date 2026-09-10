<?php

namespace App\Services\Fiscal;

use App\Models\Emitente;
use App\Models\EmitenteCertificado;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use NFePHP\Common\Certificate;

/**
 * Ciclo de vida do certificado A1.
 *
 * Difere do app-transm em três pontos, apontados na análise da Fase 0:
 * o arquivo vai cifrado em repouso, o anterior vira histórico em vez de ser
 * apagado, e o erro de algoritmo antigo do OpenSSL 3 é tratado em vez de
 * virar "confira o arquivo e a senha".
 */
class CertificateService
{
    /** Assinatura do erro do OpenSSL 3 para algoritmo desabilitado. */
    private const ERRO_LEGADO = '0308010C';

    public function __construct(
        private readonly ConversorLegado $conversor,
    ) {}

    public function enviar(
        Emitente $emitente,
        UploadedFile $arquivo,
        string $senha,
        User $user,
    ): EmitenteCertificado {
        $conteudo = (string) $arquivo->get();
        $convertido = false;

        try {
            $certificate = Certificate::readPfx($conteudo, $senha);
        } catch (\Throwable $e) {
            if (! $this->pareceAlgoritmoAntigo($e)) {
                throw ValidationException::withMessages([
                    'certificado' => 'Não foi possível abrir o certificado. Confira o arquivo A1 e a senha informada.',
                ]);
            }

            $recuperado = $this->conversor->converter($conteudo, $senha);

            if ($recuperado === null) {
                throw ValidationException::withMessages([
                    'certificado' => 'Este certificado usa um algoritmo antigo que o OpenSSL 3 desabilitou, '
                        .'e a conversão automática não foi possível neste servidor. '
                        .'Peça à sua certificadora um arquivo A1 reexportado em formato atual.',
                ]);
            }

            try {
                $certificate = Certificate::readPfx($recuperado, $senha);
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'certificado' => 'Este certificado usa um algoritmo antigo e, mesmo após a conversão, '
                        .'não pôde ser lido. Peça à sua certificadora um arquivo A1 em formato atual.',
                ]);
            }

            $conteudo = $recuperado;
            $convertido = true;
        }

        $this->conferirTitular($certificate, $emitente);
        $this->conferirValidade($certificate);

        return $this->guardar($emitente, $certificate, $conteudo, $senha, $user, $convertido);
    }

    /**
     * Reabre o certificado ativo para assinar e transmitir.
     */
    public function certificado(Emitente $emitente): Certificate
    {
        $registro = $this->ativo($emitente);

        if ($registro === null) {
            throw ValidationException::withMessages([
                'certificado' => 'Cadastre um certificado A1 válido antes de transmitir.',
            ]);
        }

        if ($registro->vencido()) {
            throw ValidationException::withMessages([
                'certificado' => 'O certificado ativo venceu em '.$registro->valido_ate->format('d/m/Y').'.',
            ]);
        }

        if (! Storage::disk('fiscal')->exists($registro->arquivo_path)) {
            throw ValidationException::withMessages([
                'certificado' => 'O arquivo do certificado não foi encontrado na área privada.',
            ]);
        }

        $cifrado = (string) Storage::disk('fiscal')->get($registro->arquivo_path);

        return Certificate::readPfx(Crypt::decrypt($cifrado, false), $registro->senha);
    }

    public function ativo(Emitente $emitente): ?EmitenteCertificado
    {
        return EmitenteCertificado::query()
            ->where('emitente_id', $emitente->getKey())
            ->where('ativo', true)
            ->latest('id')
            ->first();
    }

    private function pareceAlgoritmoAntigo(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), self::ERRO_LEGADO)
            || str_contains(strtolower($e->getMessage()), 'unsupported');
    }

    private function conferirTitular(Certificate $certificate, Emitente $emitente): void
    {
        $cnpj = preg_replace('/\D/', '', (string) $certificate->getCnpj());

        if ($cnpj !== $emitente->cnpj) {
            throw ValidationException::withMessages([
                'certificado' => 'O CNPJ do certificado não corresponde ao CNPJ do emitente.',
            ]);
        }
    }

    private function conferirValidade(Certificate $certificate): void
    {
        if ($certificate->isExpired()) {
            throw ValidationException::withMessages([
                'certificado' => 'O certificado digital informado está vencido.',
            ]);
        }
    }

    private function guardar(
        Emitente $emitente,
        Certificate $certificate,
        string $conteudo,
        string $senha,
        User $user,
        bool $convertido,
    ): EmitenteCertificado {
        $path = 'certificados/'.$emitente->getKey().'/'.Str::uuid()->toString().'.pfx.enc';

        // Cifrado em repouso. Quem ler o bucket não tem o certificado.
        if (! Storage::disk('fiscal')->put($path, Crypt::encrypt($conteudo, false))) {
            throw ValidationException::withMessages([
                'certificado' => 'Não foi possível armazenar o certificado na área privada.',
            ]);
        }

        $parsed = openssl_x509_parse((string) $certificate) ?: [];

        return DB::transaction(function () use ($emitente, $certificate, $path, $senha, $user, $convertido, $parsed) {
            // O anterior permanece no histórico, apenas deixa de ser o ativo.
            EmitenteCertificado::query()
                ->where('emitente_id', $emitente->getKey())
                ->where('ativo', true)
                ->update(['ativo' => false]);

            return EmitenteCertificado::create([
                'emitente_id' => $emitente->getKey(),
                'arquivo_path' => $path,
                'senha' => $senha,
                'titular' => (string) $certificate->getCompanyName(),
                'cnpj' => preg_replace('/\D/', '', (string) $certificate->getCnpj()),
                'serial' => (string) ($parsed['serialNumberHex'] ?? $parsed['serialNumber'] ?? ''),
                'fingerprint' => hash('sha256', (string) $certificate),
                'valido_de' => $certificate->getValidFrom(),
                'valido_ate' => $certificate->getValidTo(),
                'ativo' => true,
                'convertido_de_legado' => $convertido,
                'enviado_por' => $user->getKey(),
            ]);
        });
    }
}
