<?php

use App\Models\Emitente;
use App\Models\User;
use App\Support\EmitenteAtual;
use Illuminate\Auth\Access\AuthorizationException;

it('devolve nulo quando o usuario nao tem emitente', function () {
    $this->actingAs(User::factory()->create());

    expect(app(EmitenteAtual::class)->resolver())->toBeNull();
});

it('usa o primeiro emitente vinculado quando nada foi escolhido', function () {
    $user = User::factory()->create();
    $emitente = Emitente::factory()->create();
    $user->emitentes()->attach($emitente);
    $this->actingAs($user);

    expect(app(EmitenteAtual::class)->resolver()->id)->toBe($emitente->id);
});

it('respeita o emitente escolhido na sessao', function () {
    $user = User::factory()->create();
    $primeiro = Emitente::factory()->create();
    $segundo = Emitente::factory()->create();
    $user->emitentes()->attach([$primeiro->id, $segundo->id]);
    $this->actingAs($user);

    app(EmitenteAtual::class)->escolher($segundo);

    expect(app(EmitenteAtual::class)->resolver()->id)->toBe($segundo->id);
});

it('ignora emitente da sessao ao qual o usuario perdeu acesso', function () {
    $user = User::factory()->create();
    $vinculado = Emitente::factory()->create();
    $alheio = Emitente::factory()->create();
    $user->emitentes()->attach($vinculado);
    $this->actingAs($user);

    // Sessão adulterada, ou vínculo revogado depois da escolha.
    session()->put('emitente_atual_id', $alheio->id);

    expect(app(EmitenteAtual::class)->resolver()->id)->toBe($vinculado->id);
});

it('recusa escolher emitente sem vinculo', function () {
    $user = User::factory()->create();
    $alheio = Emitente::factory()->create();
    $this->actingAs($user);

    expect(fn () => app(EmitenteAtual::class)->escolher($alheio))
        ->toThrow(AuthorizationException::class);
});
