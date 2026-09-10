<?php

namespace App\Livewire\Certificados;

use App\Models\Emitente;
use App\Models\EmitenteCertificado;
use App\Services\Fiscal\AtivarProducao;
use App\Services\Fiscal\CertificateService;
use App\Support\EmitenteAtual;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.fiscal')]
#[Title('Certificado digital')]
class Gerenciar extends Component
{
    use WithFileUploads;

    public $arquivo;

    public string $senha = '';

    /** Confirmação digitada para virar o ambiente. Ver DF-004. */
    public string $confirmacaoProducao = '';

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('certificado.ver');
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    #[Computed]
    public function certificados()
    {
        return EmitenteCertificado::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->with('enviadoPor')
            ->latest('id')
            ->get();
    }

    #[Computed]
    public function ativo(): ?EmitenteCertificado
    {
        return app(CertificateService::class)->ativo($this->emitente);
    }

    public function enviar(CertificateService $service): void
    {
        $this->authorize('certificado.gerenciar');

        $this->validate([
            'arquivo' => ['required', 'file', 'max:5120', 'extensions:pfx,p12'],
            'senha' => ['required', 'string', 'max:255'],
        ], attributes: [
            'arquivo' => 'certificado',
            'senha' => 'senha do certificado',
        ]);

        $service->enviar($this->emitente, $this->arquivo, $this->senha, Auth::user());

        // A senha não fica em memória depois do uso.
        $this->reset('arquivo', 'senha');
        unset($this->certificados, $this->ativo);

        session()->flash('sucesso', 'Certificado enviado e validado.');
    }

    public function ativarProducao(AtivarProducao $service): void
    {
        $this->authorize('emitente.ativar-producao');

        if ($this->confirmacaoProducao !== 'PRODUCAO') {
            throw ValidationException::withMessages([
                'confirmacaoProducao' => 'Digite PRODUCAO em maiúsculas para confirmar.',
            ]);
        }

        $service->ativar($this->emitente, Auth::user());

        $this->reset('confirmacaoProducao');
        unset($this->emitente);

        session()->flash('sucesso', 'Emitente ativado em produção. As notas passam a ter valor fiscal.');
    }

    public function voltarParaHomologacao(AtivarProducao $service): void
    {
        $this->authorize('emitente.ativar-producao');

        $service->voltarParaHomologacao($this->emitente, Auth::user());
        unset($this->emitente);

        session()->flash('sucesso', 'Emitente devolvido para homologação.');
    }

    public function render()
    {
        return view('livewire.certificados.gerenciar');
    }
}
