<?php

namespace App\Http\Controllers;

use App\Support\TenantAtual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve a logo do tenant da requisição.
 *
 * O arquivo vive no disco privado, junto do resto do acervo fiscal, então não
 * tem URL pública. Quem manda no arquivo é o `TenantAtual`, resolvido pelo
 * host: como não há identificador na URL, não existe pedido possível para a
 * logo de outro cliente.
 */
class LogoTenantController extends Controller
{
    public function __invoke(Request $request, TenantAtual $tenantAtual): Response
    {
        $path = $tenantAtual->obter()?->logo_path;

        abort_if(blank($path) || ! Storage::disk('fiscal')->exists($path), 404);

        // A logo aparece em toda tela, e a URL é sempre a mesma. Sem ETag o
        // navegador ou baixaria o arquivo a cada página, ou guardaria a logo
        // antiga depois de uma troca. O nome do arquivo é sorteado a cada
        // upload, então serve de versão.
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
}
