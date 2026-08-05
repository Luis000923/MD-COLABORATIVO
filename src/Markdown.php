<?php

namespace App;

use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Environment\Environment;
use League\CommonMark\MarkdownConverter;

class Markdown
{
    private static ?MarkdownConverter $converter = null;

    private static function converter(): MarkdownConverter
    {
        if (self::$converter === null) {
            $environment = new Environment([
                'html_input' => 'allow',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            $environment->addExtension(new DisallowedRawHtmlExtension());
            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }

    public static function toHtml(string $markdown): string
    {
        $html = (string) self::converter()->convert($markdown);

        return self::sanitize($html);
    }

    /**
     * Filtra el HTML crudo permitido por CommonMark: quita atributos
     * on* (onerror, onclick, ...) y esquemas de URL peligrosos
     * (javascript:, data:text/html) que DisallowedRawHtmlExtension no cubre.
     */
    private static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $documento = new \DOMDocument();
        libxml_use_internal_errors(true);
        $documento->loadHTML(
            '<?xml encoding="utf-8" ?><div id="mdroot">' . $html . '</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        $xpath = new \DOMXPath($documento);
        foreach ($xpath->query('//*') as $nodo) {
            if (!$nodo instanceof \DOMElement) {
                continue;
            }

            foreach (iterator_to_array($nodo->attributes) as $atributo) {
                $nombre = strtolower($atributo->nodeName);
                $valor = trim($atributo->nodeValue);

                if (str_starts_with($nombre, 'on')) {
                    $nodo->removeAttribute($atributo->nodeName);
                    continue;
                }

                if (in_array($nombre, ['src', 'href'], true)
                    && preg_match('/^\s*(javascript|data)\s*:/i', $valor)) {
                    $nodo->removeAttribute($atributo->nodeName);
                }
            }
        }

        $raiz = $documento->getElementById('mdroot');
        $resultado = '';
        foreach ($raiz->childNodes as $hijo) {
            $resultado .= $documento->saveHTML($hijo);
        }

        return $resultado;
    }

    /**
     * Extrae un esquema (outline) jerárquico a partir de los encabezados
     * del documento. Devuelve una lista de ['level' => int, 'text' => string].
     */
    public static function extractOutline(string $markdown): array
    {
        $outline = [];
        $lines = preg_split('/\R/', $markdown);

        $inFence = false;
        $fenceMarker = '';

        foreach ($lines as $line) {
            if (preg_match('/^\s*(```+|~~~+)/', $line, $m)) {
                if (!$inFence) {
                    $inFence = true;
                    $fenceMarker = $m[1][0];
                } elseif (str_starts_with(trim($line), str_repeat($fenceMarker, 3))) {
                    $inFence = false;
                }
                continue;
            }

            if ($inFence) {
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $line, $m)) {
                $outline[] = [
                    'level' => strlen($m[1]),
                    'text' => trim($m[2]),
                ];
            }
        }

        return $outline;
    }
}
