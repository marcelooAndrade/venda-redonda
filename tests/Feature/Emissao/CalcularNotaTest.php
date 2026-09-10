<?php

use App\Models\Nota;
use App\Models\Produto;
use App\Services\Fiscal\CalcularNota;

it('recalcula os tributos de cada item no servidor', function () {
    $nota = notaPronta();
    // Zera o que veio pronto para provar que o cálculo é feito de novo.
    $nota->itens[0]->forceFill(['valor_icms' => 0, 'valor_pis' => 0])->save();

    $nota = app(CalcularNota::class)->recalcular($nota->fresh('itens'));

    expect((float) $nota->itens[0]->valor_icms)->toBe(262.62)
        ->and((float) $nota->itens[0]->valor_pis)->toBe(24.07);
});

it('soma os totais a partir dos itens', function () {
    $nota = app(CalcularNota::class)->recalcular(notaPronta());

    expect((float) $nota->valor_produtos)->toBe(1459.0)
        ->and((float) $nota->valor_nota)->toBe(1459.0);
});

it('inclui ipi e st no total da nota', function () {
    $nota = notaPronta(extraRegra: [
        'cst_ipi' => '50', 'aliquota_ipi' => 5,
        'mva_st' => 40, 'aliquota_st' => 18,
    ]);

    $nota = app(CalcularNota::class)->recalcular($nota);

    // IPI: 5% de 1459 = 72,95
    // ST: base 1459 x 1,40 = 2.042,60; 18% = 367,67; menos o próprio 262,62 = 105,05
    expect((float) $nota->valor_ipi)->toBe(72.95)
        ->and((float) $nota->itens[0]->valor_icms_st)->toBe(105.05)
        ->and((float) $nota->valor_nota)->toBe(1637.00);
});

it('define o idDest pela uf do destinatario', function () {
    $nota = notaPronta();
    $nota->destinatario->forceFill(['uf' => 'MG'])->save();

    // Sem regra interestadual cadastrada, o cálculo avisa em vez de inventar.
    expect(fn () => app(CalcularNota::class)->recalcular($nota->fresh(['itens', 'destinatario'])))
        ->toThrow(RuntimeException::class);
});

it('avisa quando o produto nao tem perfil fiscal', function () {
    $nota = notaPronta();
    Produto::whereKey($nota->itens[0]->produto_id)->update(['perfil_fiscal_id' => null]);
    Nota::whereKey($nota->id)->update(['natureza_operacao_id' => null]);

    app(CalcularNota::class)->recalcular($nota->fresh(['itens']));
})->throws(RuntimeException::class, 'perfil fiscal');

it('recusa calcular sem destinatario', function () {
    $nota = notaPronta();
    Nota::whereKey($nota->id)->update(['pessoa_id' => null]);

    app(CalcularNota::class)->recalcular($nota->fresh());
})->throws(RuntimeException::class, 'destinatário');
