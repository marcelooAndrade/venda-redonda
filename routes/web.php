<?php

use App\Http\Controllers\LogoTenantController;
use App\Http\Controllers\NotaServicoArquivoController;
use App\Http\Controllers\RaizController;
use App\Livewire\Certificados\Gerenciar;
use App\Livewire\Contador\Exportacao;
use App\Livewire\Estoque\Painel;
use App\Livewire\Financeiro\ContasPagar;
use App\Livewire\Financeiro\ContasReceber;
use App\Livewire\Nfse\Configuracao;
use App\Livewire\Nfse\Notas;
use App\Livewire\Notas\Emissao;
use App\Livewire\Painel\Inicio;
use App\Livewire\Pessoas\Cadastro;
use App\Livewire\Produto\Empresas;
use App\Livewire\Tenancy\Marca;
use App\Livewire\Tributacao\Regras;
use Illuminate\Support\Facades\Route;

// A raiz muda de superfície conforme o host: apresentação no domínio nu
// do produto, aplicação em todo o resto.
Route::get('/', RaizController::class)->name('home');

// Pública de propósito: a tela de login precisa mostrar a logo de quem está
// entrando, e ela roda antes de haver usuário. Não é exposição nova, porque
// quem alcança o host já alcança a tela de login. O isolamento continua vindo
// do host resolvido, não de identificador na URL.
Route::get('logo', LogoTenantController::class)->name('logo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Inicio::class)->name('dashboard');

    Route::get('contabilidade', Exportacao::class)->name('contabilidade');
    Route::get('notas', Emissao::class)->name('notas');
    Route::get('importacao', App\Livewire\Importacao\Painel::class)->name('importacao');
    Route::get('estoque', Painel::class)->name('estoque');
    // `Painel` já nomeia o do estoque neste arquivo, então este vai pelo
    // nome completo em vez de um alias que confundiria os dois.
    Route::get('financeiro', App\Livewire\Financeiro\Painel::class)->name('financeiro');
    Route::get('contas-a-pagar', ContasPagar::class)->name('contas-a-pagar');
    Route::get('contas-a-receber', ContasReceber::class)->name('contas-a-receber');
    Route::get('marca', Marca::class)->name('marca');
    Route::get('produtos', App\Livewire\Produtos\Cadastro::class)->name('produtos');
    Route::get('regras-fiscais', Regras::class)->name('regras-fiscais');
    Route::get('destinatarios', Cadastro::class)->name('destinatarios');
    Route::get('certificados', Gerenciar::class)->name('certificados');
    // `Cadastro` já nomeia o de pessoas neste arquivo, então este vai pelo
    // nome completo.
    Route::get('emitente', App\Livewire\Emitentes\Cadastro::class)->name('emitente');
    Route::get('nfse', Configuracao::class)->name('nfse');
    Route::get('notas-servico', Notas::class)->name('notas-servico');
    Route::get('notas-servico/{nota}/pdf', [NotaServicoArquivoController::class, 'pdf'])->name('notas-servico.pdf');
    Route::get('notas-servico/{nota}/xml', [NotaServicoArquivoController::class, 'xml'])->name('notas-servico.xml');

    // Atravessa tenants. O componente exige `produto.administrar`, que só o
    // dono do produto tem.
    Route::get('empresas', Empresas::class)->name('empresas');
});

require __DIR__.'/settings.php';
