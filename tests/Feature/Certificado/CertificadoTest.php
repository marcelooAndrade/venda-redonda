<?php

use App\Models\Emitente;
use App\Models\EmitenteCertificado;
use App\Models\User;
use App\Services\Fiscal\CertificateService;
use App\Services\Fiscal\ConversorLegado;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

const SENHA = 'teste123';

function arquivo(string $nome): UploadedFile
{
    return new UploadedFile(base_path("tests/Fixtures/certificados/{$nome}.pfx"), "{$nome}.pfx", null, null, true);
}

function emitenteRcm(): Emitente
{
    return Emitente::factory()->create(['cnpj' => '11222333000181']);
}

beforeEach(function () {
    Storage::fake('fiscal');
    $this->service = app(CertificateService::class);
    $this->user = User::factory()->create();
});

it('aceita certificado com o cnpj do emitente', function () {
    $cert = $this->service->enviar(emitenteRcm(), arquivo('valido'), SENHA, $this->user);

    expect($cert->cnpj)->toBe('11222333000181')
        ->and($cert->ativo)->toBeTrue()
        ->and($cert->titular)->toContain('RCM DO BRASIL');
});

it('recusa certificado de outro cnpj', function () {
    $this->service->enviar(emitenteRcm(), arquivo('outro-cnpj'), SENHA, $this->user);
})->throws(ValidationException::class, 'não corresponde');

it('recusa certificado vencido', function () {
    $this->service->enviar(emitenteRcm(), arquivo('vencido'), SENHA, $this->user);
})->throws(ValidationException::class, 'vencido');

it('converte sozinho o certificado com algoritmo antigo', function () {
    // OpenSSL 3 recusa RC2-40 com error:0308010C. Em vez de devolver erro ao
    // usuário, o sistema converte pelo provider legacy e segue.
    $cert = $this->service->enviar(emitenteRcm(), arquivo('legado'), SENHA, $this->user);

    expect($cert->cnpj)->toBe('11222333000181')
        ->and($cert->convertido_de_legado)->toBeTrue();
});

it('explica o algoritmo antigo quando a conversao nao e possivel', function () {
    // Simula ambiente sem o binário openssl ou sem o provider legacy.
    $this->app->instance(ConversorLegado::class, new class implements ConversorLegado
    {
        public function converter(string $pfx, string $senha): ?string
        {
            return null;
        }
    });

    try {
        app(CertificateService::class)->enviar(emitenteRcm(), arquivo('legado'), SENHA, $this->user);
        $this->fail('deveria ter lançado ValidationException');
    } catch (ValidationException $e) {
        $msg = implode(' ', $e->errors()['certificado']);
        expect($msg)->toContain('algoritmo antigo')
            ->and($msg)->not->toContain('senha');
    }
});

it('culpa a senha quando a senha esta errada', function () {
    try {
        $this->service->enviar(emitenteRcm(), arquivo('valido'), 'senha-errada', $this->user);
        $this->fail('deveria ter lançado ValidationException');
    } catch (ValidationException $e) {
        expect(implode(' ', $e->errors()['certificado']))->toContain('senha');
    }
});

it('grava o pfx cifrado, nunca em texto claro', function () {
    $cert = $this->service->enviar(emitenteRcm(), arquivo('valido'), SENHA, $this->user);

    $gravado = Storage::disk('fiscal')->get($cert->arquivo_path);
    $original = file_get_contents(base_path('tests/Fixtures/certificados/valido.pfx'));

    expect($gravado)->not->toBe($original)
        ->and(Crypt::decrypt($gravado, false))->toBe($original);
});

it('mantem historico e deixa apenas um ativo por emitente', function () {
    $emitente = emitenteRcm();

    $primeiro = $this->service->enviar($emitente, arquivo('valido'), SENHA, $this->user);
    $segundo = $this->service->enviar($emitente, arquivo('valido'), SENHA, $this->user);

    expect(EmitenteCertificado::query()->where('emitente_id', $emitente->id)->count())->toBe(2)
        ->and($primeiro->fresh()->ativo)->toBeFalse()
        ->and($segundo->fresh()->ativo)->toBeTrue();
});

it('nao expoe a senha na serializacao', function () {
    $cert = $this->service->enviar(emitenteRcm(), arquivo('valido'), SENHA, $this->user);

    expect(json_encode($cert->toArray()))->not->toContain(SENHA)
        ->and(json_encode($cert->toArray()))->not->toContain('arquivo_path');
});

it('reabre o certificado ativo para assinar', function () {
    $emitente = emitenteRcm();
    $this->service->enviar($emitente, arquivo('valido'), SENHA, $this->user);

    expect($this->service->certificado($emitente->fresh())->getCnpj())->toBe('11222333000181');
});
