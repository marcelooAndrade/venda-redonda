<?php

namespace App\Livewire\Tributacao;

use App\Enums\Fiscal\AmbitoOperacao;
use App\Models\Emitente;
use App\Models\PerfilFiscal;
use App\Models\PerfilFiscalRegra;
use App\Support\EmitenteAtual;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Página do contador.
 *
 * A regra fiscal é responsabilidade de quem entende de tributação, não do
 * desenvolvedor. Aqui ela é escrita, datada e assinada: cada alteração fica
 * na auditoria com autor e horário.
 */
#[Layout('components.layouts.fiscal')]
#[Title('Regras fiscais')]
class Regras extends Component
{
    public string $perfilNome = '';

    public ?int $perfilSelecionadoId = null;

    /** @var array<string, mixed> */
    public array $regra = [];

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('tributacao.gerenciar');
        $this->limparRegra();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function perfis()
    {
        return PerfilFiscal::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->withCount('regras')
            ->orderBy('nome')
            ->get();
    }

    #[Computed]
    public function perfilSelecionado(): ?PerfilFiscal
    {
        if ($this->perfilSelecionadoId === null) {
            return null;
        }

        return PerfilFiscal::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->with('regras')
            ->find($this->perfilSelecionadoId);
    }

    public function limparRegra(): void
    {
        $this->resetErrorBag();

        $this->regra = [
            'ambito' => AmbitoOperacao::Interna->value,
            'crt' => '',
            'vigente_de' => now()->toDateString(),
            'cst_icms' => '', 'csosn' => '', 'mod_bc' => '',
            'aliquota_icms' => '', 'reducao_bc' => '', 'aliquota_credito_sn' => '',
            'mva_st' => '', 'reducao_bc_st' => '', 'aliquota_st' => '',
            'aliquota_fcp' => '', 'percentual_diferimento' => '',
            'cst_ipi' => '', 'codigo_enquadramento_ipi' => '', 'aliquota_ipi' => '',
            'cst_pis' => '', 'aliquota_pis' => '',
            'cst_cofins' => '', 'aliquota_cofins' => '',
            'cst_ibscbs' => '', 'cclasstrib' => '',
            'aliquota_ibs_uf' => '', 'aliquota_ibs_mun' => '', 'aliquota_cbs' => '',
            'cst_is' => '', 'aliquota_is' => '',
            'observacao_contador' => '',
        ];
    }

    public function criarPerfil(): void
    {
        $this->authorize('tributacao.gerenciar');

        $this->validate(
            ['perfilNome' => ['required', 'string', 'max:255']],
            attributes: ['perfilNome' => 'nome do perfil'],
        );

        $perfil = PerfilFiscal::create([
            'emitente_id' => $this->emitente->getKey(),
            'nome' => $this->perfilNome,
        ]);

        $this->reset('perfilNome');
        unset($this->perfis);
        $this->selecionarPerfil($perfil->id);

        session()->flash('sucesso', 'Perfil fiscal criado. Agora escreva a regra.');
    }

    public function selecionarPerfil(int $id): void
    {
        $this->perfilSelecionadoId = $id;
        unset($this->perfilSelecionado);
        $this->limparRegra();
    }

    public function salvarRegra(): void
    {
        $this->authorize('tributacao.gerenciar');

        $perfil = $this->perfilSelecionado;
        abort_if($perfil === null, 404);

        $this->validate([
            'regra.ambito' => ['required', 'in:interna,interestadual,exterior'],
            'regra.vigente_de' => ['required', 'date'],
            'regra.crt' => ['nullable', 'in:1,2,3,4'],
            'regra.cst_icms' => ['nullable', 'string', 'max:2'],
            'regra.csosn' => ['nullable', 'string', 'max:3'],
            'regra.cclasstrib' => ['nullable', 'string', 'max:6'],
            'regra.observacao_contador' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'regra.vigente_de' => 'data de início da vigência',
            'regra.ambito' => 'âmbito da operação',
        ]);

        DB::transaction(function () use ($perfil): void {
            $inicio = Carbon::parse($this->regra['vigente_de'])->startOfDay();

            // A regra anterior do mesmo âmbito não é apagada: ganha fim de
            // vigência na véspera, para que nota antiga continue conferindo.
            PerfilFiscalRegra::query()
                ->where('perfil_fiscal_id', $perfil->getKey())
                ->where('ambito', $this->regra['ambito'])
                ->whereNull('vigente_ate')
                ->whereDate('vigente_de', '<', $inicio)
                ->each(function (PerfilFiscalRegra $anterior) use ($inicio): void {
                    $anterior->update(['vigente_ate' => $inicio->copy()->subDay()->toDateString()]);
                });

            PerfilFiscalRegra::create([
                'perfil_fiscal_id' => $perfil->getKey(),
                ...collect($this->regra)
                    ->map(fn ($v) => $v === '' ? null : $v)
                    ->all(),
            ]);
        });

        unset($this->perfilSelecionado, $this->perfis);
        $this->limparRegra();

        session()->flash('sucesso', 'Regra registrada com a vigência informada.');
    }

    public function render()
    {
        return view('livewire.tributacao.regras');
    }
}
