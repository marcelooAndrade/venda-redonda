<?php

use App\Services\Fiscal\Tabelas\NcmParser;

it('remove os pontos do codigo', function () {
    $linhas = (new NcmParser)->parse([
        ['Codigo' => '0101.21.00', 'Descricao' => 'Reprodutores de raça pura', 'Data_Inicio' => '01/04/2022', 'Data_Fim' => '31/12/9999'],
    ]);

    expect($linhas[0]['codigo'])->toBe('01012100');
});

it('marca como valido para nfe apenas codigo de oito digitos', function () {
    $linhas = (new NcmParser)->parse([
        ['Codigo' => '01', 'Descricao' => 'Animais vivos.', 'Data_Inicio' => '01/04/2022', 'Data_Fim' => '31/12/9999'],
        ['Codigo' => '01.01', 'Descricao' => 'Cavalos.', 'Data_Inicio' => '01/04/2022', 'Data_Fim' => '31/12/9999'],
        ['Codigo' => '0101.21.00', 'Descricao' => 'Reprodutores.', 'Data_Inicio' => '01/04/2022', 'Data_Fim' => '31/12/9999'],
    ]);

    expect(array_column($linhas, 'valido_nfe'))->toBe([false, false, true]);
});

it('converte as datas do formato brasileiro', function () {
    $linhas = (new NcmParser)->parse([
        ['Codigo' => '0101.21.00', 'Descricao' => 'Reprodutores.', 'Data_Inicio' => '01/04/2022', 'Data_Fim' => '31/12/9999'],
    ]);

    expect($linhas[0]['vigente_de'])->toBe('2022-04-01')
        ->and($linhas[0]['vigente_ate'])->toBeNull();
});

it('descarta linha sem codigo', function () {
    $linhas = (new NcmParser)->parse([
        ['Codigo' => '', 'Descricao' => 'Vazio', 'Data_Inicio' => '01/04/2022', 'Data_Fim' => '31/12/9999'],
        ['Codigo' => '0101.21.00', 'Descricao' => 'Reprodutores.', 'Data_Inicio' => '01/04/2022', 'Data_Fim' => '31/12/9999'],
    ]);

    expect($linhas)->toHaveCount(1);
});
