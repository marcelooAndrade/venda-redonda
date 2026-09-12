<?php

namespace App\Livewire\Nfse;

use App\Models\Emitente;
use App\Models\EmitenteNfse;
use App\Models\ServicoNfse;
use App\Services\Nfse\AtivarProducaoNfse;
use App\Support\Dinheiro;
use App\Support\EmitenteAtual;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Configuração da NFS-e do emitente e o catálogo de serviços fiscais.
 *
 * A senha nunca volta ao navegador: o campo só substitui quando preenchido,
 * e "remover" zera a coluna. Produção liga só com a palavra digitada e com
 * a senha daquele ambiente, como a virada da NF-e.
 */
#[Layout('components.layouts.fiscal')]
#[Title('NFS-e')]
class Configuracao extends Component
{
    private const IBGE_ARARAS = '3503307';

    public bool $habilitado = false;

    public string $serieRps = '1';

    public int $proximoRpsHomologacao = 1;

    public int $proximoRpsProducao = 1;

    public string $senhaHomologacao = '';

    public string $senhaProducao = '';

    public bool $removerSenhaHomologacao = false;

    public bool $removerSenhaProducao = false;

    public string $confirmacaoProducao = '';

    public ?int $servicoId = null;

    public bool $editandoServico = false;

    /** @var array<string, mixed> */
    public array $servico = [];

    public function mount(): void
    {
        abort_unless($this->emitente !== null, 404, 'Nenhum emitente vinculado a este usuário.');
        $this->authorize('nfse.configurar');

        $config = $this->configuracao;
        $this->habilitado = $config->habilitado;
        $this->serieRps = (string) $config->serie_rps;
        $this->proximoRpsHomologacao = $config->proximo_rps_homologacao;
        $this->proximoRpsProducao = $config->proximo_rps_producao;

        $this->limparServico();
    }

    #[Computed]
    public function emitente(): ?Emitente
    {
        return app(EmitenteAtual::class)->resolver();
    }

    /** A linha nasce na primeira abertura da tela, desligada e em homologação. */
    #[Computed]
    public function configuracao(): EmitenteNfse
    {
        return EmitenteNfse::query()->firstOrCreate(['emitente_id' => $this->emitente->getKey()]);
    }

    #[Computed]
    public function servicos(): Collection
    {
        return ServicoNfse::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->orderBy('nome')
            ->get();
    }

    /**
     * O que falta no emitente para a nota sair. É aviso, não bloqueio: a
     * pessoa pode cadastrar a senha hoje e o endereço amanhã.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function pendencias(): array
    {
        $e = $this->emitente;
        $lista = [];

        if ((string) $e->codigo_municipio !== self::IBGE_ARARAS) {
            $lista[] = 'O emitente não está em Araras/SP (IBGE 3503307), e o SIGISS só serve a prestador de Araras.';
        }

        if (blank($e->inscricao_municipal)) {
            $lista[] = 'O emitente não tem inscrição municipal.';
        }

        if (strlen((string) $e->cnpj) !== 14) {
            $lista[] = 'O emitente não tem CNPJ.';
        }

        return $lista;
    }

    public function salvar(): void
    {
        $this->authorize('nfse.configurar');

        $this->validate([
            'serieRps' => ['required', 'regex:/^\d{1,5}$/'],
            'proximoRpsHomologacao' => ['required', 'integer', 'min:1', 'max:999999999'],
            'proximoRpsProducao' => ['required', 'integer', 'min:1', 'max:999999999'],
            'senhaHomologacao' => ['nullable', 'string', 'min:3', 'max:200'],
            'senhaProducao' => ['nullable', 'string', 'min:3', 'max:200'],
        ], [
            'serieRps.regex' => 'A série do RPS tem só dígitos, até cinco.',
        ], [
            'serieRps' => 'série do RPS',
            'proximoRpsHomologacao' => 'próximo RPS de homologação',
            'proximoRpsProducao' => 'próximo RPS de produção',
            'senhaHomologacao' => 'senha de homologação',
            'senhaProducao' => 'senha de produção',
        ]);

        $config = $this->configuracao;

        $dados = [
            'habilitado' => $this->habilitado,
            'serie_rps' => $this->serieRps,
            'proximo_rps_homologacao' => $this->proximoRpsHomologacao,
            'proximo_rps_producao' => $this->proximoRpsProducao,
        ];

        if ($this->removerSenhaHomologacao) {
            $dados['senha_homologacao'] = null;
        } elseif ($this->senhaHomologacao !== '') {
            $dados['senha_homologacao'] = $this->senhaHomologacao;
        }

        if ($this->removerSenhaProducao) {
            $dados['senha_producao'] = null;
        } elseif ($this->senhaProducao !== '') {
            $dados['senha_producao'] = $this->senhaProducao;
        }

        $config->forceFill($dados);

        // Ligar sem senha do ambiente atual deixaria a primeira emissão falhar
        // na tela de contas a receber, longe de onde se corrige.
        if ($config->habilitado && blank($config->senha($config->ambiente))) {
            throw ValidationException::withMessages([
                'habilitado' => 'Cadastre a senha de '.mb_strtolower($config->ambiente->rotulo()).' antes de ligar a emissão.',
            ]);
        }

        $config->save();

        $this->reset('senhaHomologacao', 'senhaProducao', 'removerSenhaHomologacao', 'removerSenhaProducao');
        unset($this->configuracao);

        session()->flash('sucesso', 'Configuração da NFS-e salva.');
    }

    public function ativarProducao(AtivarProducaoNfse $service): void
    {
        $this->authorize('nfse.configurar');

        if ($this->confirmacaoProducao !== 'PRODUCAO') {
            throw ValidationException::withMessages([
                'confirmacaoProducao' => 'Digite PRODUCAO em maiúsculas para confirmar.',
            ]);
        }

        $service->ativar($this->configuracao, Auth::user());

        $this->reset('confirmacaoProducao');
        unset($this->configuracao);

        session()->flash('sucesso', 'NFS-e ativada em produção. As notas passam a ter valor fiscal.');
    }

    public function voltarParaHomologacao(AtivarProducaoNfse $service): void
    {
        $this->authorize('nfse.configurar');

        $service->voltarParaHomologacao($this->configuracao, Auth::user());
        unset($this->configuracao);

        session()->flash('sucesso', 'NFS-e devolvida para homologação.');
    }

    public function novoServico(): void
    {
        $this->limparServico();
        $this->editandoServico = true;
    }

    public function editarServico(int $id): void
    {
        $servico = ServicoNfse::query()->where('emitente_id', $this->emitente->getKey())->find($id);

        // `abort` e não `findOrFail`: a exceção de model não vira 404 numa
        // ação Livewire, e a de HTTP vira.
        abort_unless($servico !== null, 404);

        $this->servicoId = $servico->getKey();
        $this->servico = [
            'nome' => $servico->nome,
            'codigo_servico' => $servico->codigo_servico,
            'codigo_nbs' => (string) $servico->codigo_nbs,
            'c_class_trib' => (string) $servico->c_class_trib,
            'ind_op' => (string) $servico->ind_op,
            'aliquota_iss' => $servico->aliquotaFormatada(),
            'iss_retido' => $servico->iss_retido,
            'descricao_padrao' => (string) $servico->descricao_padrao,
            'ativo' => $servico->ativo,
        ];
        $this->editandoServico = true;
    }

    public function cancelarServico(): void
    {
        $this->limparServico();
    }

    public function salvarServico(): void
    {
        $this->authorize('nfse.configurar');

        $this->validate([
            'servico.nome' => ['required', 'string', 'min:2', 'max:120'],
            'servico.codigo_servico' => ['required', 'regex:/^\d{2}\.\d{2}\.\d{2}$/'],
            'servico.codigo_nbs' => ['nullable', 'regex:/^[\d.]{4,20}$/'],
            'servico.c_class_trib' => ['nullable', 'regex:/^\d{1,10}$/'],
            'servico.ind_op' => ['nullable', 'regex:/^\d{1,10}$/'],
            'servico.descricao_padrao' => ['nullable', 'string', 'max:1000'],
        ], [
            'servico.codigo_servico.regex' => 'Use o código do serviço no formato 00.00.00.',
            'servico.codigo_nbs.regex' => 'Informe um código NBS válido, só dígitos e pontos.',
            'servico.c_class_trib.regex' => 'O cClassTrib tem só dígitos.',
            'servico.ind_op.regex' => 'O indicador de operação tem só dígitos.',
        ], [
            'servico.nome' => 'nome',
            'servico.codigo_servico' => 'código do serviço',
            'servico.codigo_nbs' => 'código NBS',
            'servico.c_class_trib' => 'cClassTrib',
            'servico.ind_op' => 'indicador de operação',
            'servico.descricao_padrao' => 'descrição padrão',
        ]);

        // Lida como dinheiro de propósito: "2,00" vira 200, e nada passa por float.
        $bp = Dinheiro::emCentavos((string) ($this->servico['aliquota_iss'] ?? '0'));

        if ($bp < 0 || $bp > 500) {
            $this->addError('servico.aliquota_iss', 'A alíquota do ISS fica entre 0% e 5%.');

            return;
        }

        $nome = trim((string) $this->servico['nome']);

        $repetido = ServicoNfse::query()
            ->where('emitente_id', $this->emitente->getKey())
            ->where('nome', $nome)
            ->when($this->servicoId !== null, fn ($q) => $q->whereKeyNot($this->servicoId))
            ->exists();

        if ($repetido) {
            $this->addError('servico.nome', 'Já existe um serviço com esse nome.');

            return;
        }

        $dados = [
            'emitente_id' => $this->emitente->getKey(),
            'nome' => $nome,
            'codigo_servico' => $this->servico['codigo_servico'],
            'codigo_nbs' => $this->servico['codigo_nbs'] ?: null,
            'c_class_trib' => $this->servico['c_class_trib'] ?: null,
            'ind_op' => $this->servico['ind_op'] ?: null,
            'aliquota_iss_bp' => $bp,
            'iss_retido' => (bool) $this->servico['iss_retido'],
            'descricao_padrao' => $this->servico['descricao_padrao'] ?: null,
            'ativo' => (bool) $this->servico['ativo'],
        ];

        if ($this->servicoId !== null) {
            $existente = ServicoNfse::query()->where('emitente_id', $this->emitente->getKey())->find($this->servicoId);
            abort_unless($existente !== null, 404);
            $existente->update($dados);
        } else {
            ServicoNfse::create($dados);
        }

        unset($this->servicos);
        $this->limparServico();

        session()->flash('sucesso', 'Serviço fiscal salvo.');
    }

    public function render()
    {
        return view('livewire.nfse.configuracao');
    }

    private function limparServico(): void
    {
        $this->servicoId = null;
        $this->editandoServico = false;
        $this->servico = [
            'nome' => '', 'codigo_servico' => '', 'codigo_nbs' => '', 'c_class_trib' => '', 'ind_op' => '',
            'aliquota_iss' => '0,00', 'iss_retido' => false, 'descricao_padrao' => '', 'ativo' => true,
        ];
    }
}
