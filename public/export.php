<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;
use App\Markdown;
use App\Version;

$id = (int) ($_GET['id'] ?? 0);
$archivo = Archivo::obtener($id);

if ($archivo === null) {
    http_response_code(404);
    echo 'Documento no encontrado.';
    exit;
}

if (nivelAcceso($id) !== 'edicion') {
    redirect('view.php?id=' . $id);
}

$version = Version::actual($id);
$html = Markdown::toHtml($version['contenido']);

$nombreBase = preg_replace('/\.(md|markdown)$/i', '', $archivo['nombre']);
$nombreDescarga = $nombreBase . '.html';

// HTML autocontenido con estilos "warm editorial" inline, para compartir el
// documento fuera de la app.
$titulo = h($archivo['nombre']);
$css = <<<CSS
:root { color-scheme: light dark; }
body { max-width: 820px; margin: 2rem auto; padding: 0 1.25rem;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  line-height: 1.7; color: #26241f; background: #f7f5f1; }
h1, h2, h3 { line-height: 1.3; }
a { color: #a8672c; }
pre { background: #f1ece2; padding: 0.9rem 1rem; border-radius: 6px; overflow-x: auto; border: 1px solid #e6e1d7; }
code { background: #f1e3d0; color: #8a5623; padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.9em; }
pre code { background: none; padding: 0; color: inherit; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #e6e1d7; padding: 0.45rem 0.75rem; }
blockquote { border-left: 3px solid #a8672c; margin-left: 0; padding-left: 1rem; color: #7a7669; }
.meta { color: #a29d8d; font-size: 0.85rem; margin-bottom: 2rem; }
@media (prefers-color-scheme: dark) {
  body { color: #ececea; background: #121212; }
  a { color: #e8b784; }
  pre { background: #181816; border-color: #2a2a26; }
  code { background: #2b241a; color: #e8b784; }
  th, td { border-color: #2a2a26; }
  blockquote { border-color: #c98a4b; color: #98968c; }
  .meta { color: #6b6a63; }
}
CSS;

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . rawurlencode($nombreDescarga) . '"');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $titulo ?></title>
<style><?= $css ?></style>
</head>
<body>
<h1><?= $titulo ?></h1>
<p class="meta">v<?= (int) $version['numero_version'] ?> · <?= h($version['autor_nombre']) ?> · <?= h($version['creado_en']) ?></p>
<?= $html ?>
</body>
</html>
