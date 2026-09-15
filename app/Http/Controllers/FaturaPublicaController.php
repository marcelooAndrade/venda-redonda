<?php

namespace App\Http\Controllers;

use App\Models\Emitente;
use App\Models\Fatura;
use App\Models\FaturaParcela;
use App\Models\Pessoa;
use App\Models\Tenant;
use App\Support\QrCode;
use App\Support\TenantAtual;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A fatura como o cliente a vê: sem login, pelo link com token.
 *
 * O token é o único identificador, e não há tenant no host: a página roda no
 * domínio do produto para qualquer empresa. Por isso toda leitura aqui é sem
 * o escopo de tenant, e o tenant da requisição é definido a partir da
 * fatura, para a marca (cores, logo, nome) sair da empresa dona dela.
 * Portado de `PublicInvoice.tsx` e `fatura/[token]/page.tsx` do projeto
 * Marcelo Andrade.
 */
class FaturaPublicaController extends Controller
{
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    public function show(string $token, TenantAtual $tenantAtual): View
    {
        [$fatura, $emitente, $tenant] = $this->resolver($token);

        $tenantAtual->definir($tenant);

        $cliente = $fatura->pessoa_id ? Pessoa::withoutGlobalScope('tenant')->find($fatura->pessoa_id) : null;
        $hoje = today()->toDateString();

        $parcelas = $fatura->parcelas->map(function (FaturaParcela $p) use ($hoje): array {
            $estado = $p->status === 'pago' ? 'paga' : ($p->status === 'cancelado' ? 'cancelada' : ($p->vencimento->toDateString() < $hoje ? 'vencida' : 'aguardando'));

            return [
                'numero' => $p->numero,
                'descricao' => $p->descricao,
                'valorCentavos' => (int) $p->valor_centavos,
                'vencimento' => $p->vencimento,
                'estado' => $estado,
                'pix' => $estado === 'paga' || $estado === 'cancelada' ? null : $p->pix_payload,
                'qr' => $estado !== 'paga' && $estado !== 'cancelada' && filled($p->pix_payload) ? QrCode::svg($p->pix_payload) : null,
            ];
        })->values();

        return view('fatura-publica', [
            'fatura' => $fatura,
            'emitente' => $emitente,
            'tenant' => $tenant,
            'cliente' => $cliente,
            'parcelas' => $parcelas,
            'temLogo' => filled($tenant?->logo_path),
            'totais' => [
                'total' => (int) $parcelas->where('estado', '!=', 'cancelada')->sum('valorCentavos'),
                'pago' => (int) $parcelas->where('estado', 'paga')->sum('valorCentavos'),
                'emAberto' => (int) $parcelas->whereIn('estado', ['aguardando', 'vencida'])->sum('valorCentavos'),
            ],
            // A primeira em aberto abre selecionada: é a que o cliente veio pagar.
            'selecionada' => max(0, (int) $parcelas->search(fn (array $p): bool => in_array($p['estado'], ['aguardando', 'vencida'], true))),
        ]);
    }

    /** A logo do tenant dono da fatura, pelo mesmo token. Sem host, sem sessão. */
    public function logo(Request $request, string $token): Response
    {
        [, , $tenant] = $this->resolver($token);

        $path = $tenant?->logo_path;

        abort_if(blank($path) || ! Storage::disk('fiscal')->exists($path), 404);

        $resposta = new Response;
        $resposta->setEtag(md5($path))->setPrivate();

        if ($resposta->isNotModified($request)) {
            return $resposta;
        }

        return Storage::disk('fiscal')->response($path, null, [
            'ETag' => (string) $resposta->getEtag(),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /** @return array{0: Fatura, 1: Emitente, 2: Tenant|null} */
    private function resolver(string $token): array
    {
        abort_unless((bool) preg_match(self::UUID, $token), 404);

        $fatura = Fatura::withoutGlobalScope('tenant')
            ->where('public_token', $token)
            ->where('status', 'ativa')
            ->with('parcelas')
            ->first();

        abort_if($fatura === null, 404);

        $emitente = Emitente::withoutGlobalScope('tenant')->find($fatura->emitente_id);

        abort_if($emitente === null, 404);

        return [$fatura, $emitente, Tenant::find($emitente->tenant_id)];
    }
}
