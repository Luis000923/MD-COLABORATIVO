<?php

namespace App;

use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
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
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
            self::$converter = new MarkdownConverter($environment);
        }

        return self::$converter;
    }

    public static function toHtml(string $markdown): string
    {
        return (string) self::converter()->convert($markdown);
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
