<?php

use App\Enums\Fiscal\Ambiente;
use App\Enums\Fiscal\NFeStatus;
use Illuminate\Support\Facades\Blade;

it('exibe a faixa de ambiente em homologacao', function () {
    $html = Blade::render('<x-ui.env-banner :ambiente="$a" />', ['a' => Ambiente::Homologacao]);

    expect($html)->toContain('homologa');
});

it('nao exibe faixa alguma em producao', function () {
    $html = Blade::render('<x-ui.env-banner :ambiente="$a" />', ['a' => Ambiente::Producao]);

    expect(trim($html))->toBe('');
});

it('usa tonalidade para status recuperavel e solido para terminal', function () {
    $rejeitada = Blade::render('<x-ui.badge-status :status="$s" />', ['s' => NFeStatus::Rejeitada]);
    $cancelada = Blade::render('<x-ui.badge-status :status="$s" />', ['s' => NFeStatus::Cancelada]);

    // Rejeitada é recuperável: fundo claro, texto escuro.
    expect($rejeitada)->toContain('bg-danger-100');
    // Cancelada é terminal: preenchimento sólido.
    expect($cancelada)->toContain('bg-graphite-800');
});

it('cobre os oito status da nf-e sem cair no padrao', function () {
    foreach (NFeStatus::cases() as $status) {
        expect($status->classesBadge())->not->toBe('');
        expect($status->rotulo())->not->toBe('');
    }

    expect(NFeStatus::cases())->toHaveCount(8);
});

it('renderiza botao primario em grafite, nunca no vermelho da marca', function () {
    $html = Blade::render('<x-ui.button>Transmitir</x-ui.button>');

    expect($html)->toContain('bg-graphite-900')
        ->and($html)->not->toContain('bg-primary-600');
});

it('renderiza botao destrutivo na cor de perigo', function () {
    $html = Blade::render('<x-ui.button variant="destructive">Cancelar</x-ui.button>');

    expect($html)->toContain('bg-danger-600');
});
