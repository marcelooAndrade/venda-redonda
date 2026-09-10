<?php

use App\Models\AuditLog;
use App\Models\Emitente;
use App\Models\User;

it('registra a criacao com usuario e ip', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $emitente = Emitente::factory()->create();

    $log = AuditLog::query()->latest('id')->first();

    expect($log->evento)->toBe('criado')
        ->and($log->auditavel_type)->toBe(Emitente::class)
        ->and($log->auditavel_id)->toBe($emitente->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->ip)->not->toBeNull();
});

it('registra alteracao com valores antes e depois', function () {
    $emitente = Emitente::factory()->create(['razao_social' => 'Antes Ltda']);

    $emitente->update(['razao_social' => 'Depois Ltda']);

    $log = AuditLog::query()->where('evento', 'alterado')->latest('id')->first();

    expect($log->alteracoes['razao_social']['de'])->toBe('Antes Ltda')
        ->and($log->alteracoes['razao_social']['para'])->toBe('Depois Ltda');
});

it('nunca grava o valor de campo oculto, como a senha', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'uma-senha-que-nao-pode-vazar']);

    $log = AuditLog::query()
        ->where('auditavel_type', User::class)
        ->where('evento', 'alterado')
        ->latest('id')
        ->first();

    expect($log->alteracoes)->toHaveKey('password')
        ->and($log->alteracoes['password']['de'])->toBe('[omitido]')
        ->and($log->alteracoes['password']['para'])->toBe('[omitido]');
});

it('registra exclusao', function () {
    $emitente = Emitente::factory()->create();
    $id = $emitente->id;

    $emitente->delete();

    $log = AuditLog::query()->latest('id')->first();

    expect($log->evento)->toBe('excluido')
        ->and($log->auditavel_id)->toBe($id);
});
