<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR Code em SVG, para colar direto na página. Sem GD nem Imagick: a página
 * pública da fatura precisa funcionar em qualquer servidor, e SVG escala
 * sem borrar no celular.
 */
final class QrCode
{
    public static function svg(string $conteudo, int $tamanho = 220): string
    {
        $renderer = new ImageRenderer(new RendererStyle($tamanho, 0), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($conteudo);
    }
}
