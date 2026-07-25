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

$version = Version::actual($id);
$html = Markdown::toHtml($version['contenido']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($archivo['nombre']) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
  <h1><a href="index.php" class="link-plain">Documentos</a></h1>
</header>

<main class="container">
  <section class="doc-toolbar">
    <div>
      <h2><?= h($archivo['nombre']) ?></h2>
      <p class="file-meta">
        v<?= (int) $version['numero_version'] ?> por <?= h($version['autor_nombre']) ?> · <?= h($version['creado_en']) ?>
      </p>
    </div>
    <div class="doc-actions">
      <a href="edit.php?id=<?= $id ?>" class="btn">Editar</a>
      <a href="history.php?id=<?= $id ?>" class="btn btn-secondary">Historial</a>
      <a href="index.php" class="btn btn-secondary">Volver</a>
    </div>
  </section>

  <article class="markdown-body">
    <?= $html ?>
  </article>
</main>

<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<script src="assets/js/render.js"></script>
</body>
</html>
