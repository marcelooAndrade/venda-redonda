<?php

use App\Models\Fatura;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A página que o cliente abre pelo link. Roda sem login e sem tenant no
 * host: o token é o único identificador, e a marca vem da empresa dona.
 */
beforeEach(function () {
    $this->travelTo('2026-09-15 10:00:00');
    $this->emitente = emitenteCompleto(['chave_pix' => '11222333000181', 'nome_fantasia' => 'RCM do Brasil']);
    $this->cliente = destinatarioCompleto($this->emitente);

    $this->fatura = Fatura::create([
        'emitente_id' => $this->emitente->id, 'pessoa_id' => $this->cliente->id,
        'titulo' => 'Consultoria de setembro', 'observacoes' => 'Obrigado pela parceria.',
    ]);
    $this->fatura->parcelas()->create(['numero' => 1, 'descricao' => 'Entrada', 'valor_centavos' => 30_000, 'vencimento' => '2026-09-01', 'status' => 'pago', 'pago_em' => now()]);
    $vencida = $this->fatura->parcelas()->create(['numero' => 2, 'descricao' => 'Parcela vencida', 'valor_centavos' => 20_000, 'vencimento' => '2026-09-10']);
    $aberta = $this->fatura->parcelas()->create(['numero' => 3, 'descricao' => 'Saldo', 'valor_centavos' => 50_000, 'vencimento' => '2026-10-10']);
    $vencida->setRelation('fatura', $this->fatura)->gerarCobrancaPix();
    $aberta->setRelation('fatura', $this->fatura)->gerarCobrancaPix();

    // Sem tenant no host, como no domínio do produto.
    $this->url = 'https://vendaredonda.com.br/fatura/'.$this->fatura->public_token;
});

it('abre sem login, com cliente, titulo, observacoes, totais e parcelas', function () {
    $this->get($this->url)
        ->assertOk()
        ->assertSee('METALURGICA PIRACICABA LTDA')
        ->assertSee('11.444.777/0001-61')
        ->assertSee('Consultoria de setembro')
        ->assertSee('Obrigado pela parceria.')
        ->assertSee('1.000,00')
        ->assertSee('300,00')
        ->assertSee('700,00')
        ->assertSee('Parcela paga')
        ->assertSee('Parcela vencida')
        ->assertSee('Aguardando pagamento')
        ->assertSee('Copiar código Pix')
        ->assertSee('<svg', false)
        ->assertSee('Esta página não solicita senha');
});

it('a marca e da empresa dona da fatura', function () {
    Tenant::find($this->emitente->tenant_id)->update(['nome_curto' => 'RCM']);

    $this->get($this->url)->assertOk()->assertSee('RCM')->assertSee('Fatura digital');
});

it('token errado, fatura cancelada e formato invalido dao 404', function () {
    $this->get('https://vendaredonda.com.br/fatura/'.Str::uuid())->assertNotFound();
    $this->get('https://vendaredonda.com.br/fatura/nao-e-uuid')->assertNotFound();

    $this->fatura->update(['status' => 'cancelada']);
    $this->get($this->url)->assertNotFound();
});

it('a parcela paga nao mostra codigo pix', function () {
    $this->fatura->parcelas()->where('numero', '!=', 1)->update(['status' => 'pago', 'pago_em' => now()]);

    $this->get($this->url)->assertOk()->assertDontSee('Copiar código Pix')->assertSee('Pagamento recebido');
});

it('serve a logo do tenant pelo mesmo token', function () {
    Storage::fake('fiscal');
    Storage::disk('fiscal')->put('logos/rcm.png', 'png-falso');
    Tenant::find($this->emitente->tenant_id)->update(['logo_path' => 'logos/rcm.png']);

    $this->get($this->url.'/logo')->assertOk();
    $this->get($this->url)->assertOk()->assertSee($this->url.'/logo');
    $this->get('https://vendaredonda.com.br/fatura/'.Str::uuid().'/logo')->assertNotFound();
});
