<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;
use App\Version;

$id = (int) ($_GET['id'] ?? 0);
$archivo = Archivo::obtener($id);

if ($archivo === null) {
    http_response_code(404);
    echo 'Documento no encontrado.';
    exit;
}

$version = Version::actual($id);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Editando <?= h($archivo['nombre']) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
  <h1><a href="index.php" class="link-plain">Documentos</a></h1>
</header>

<main class="container">
  <section class="doc-toolbar">
    <div>
      <h2>Editando: <?= h($archivo['nombre']) ?></h2>
      <p class="file-meta">Basado en v<?= (int) $version['numero_version'] ?></p>
    </div>
    <div class="doc-actions">
      <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancelar</a>
      <a href="history.php?id=<?= $id ?>" class="btn btn-secondary">Historial</a>
    </div>
  </section>

  <div class="editor-extra-toolbar">
    <label>
      Tu nombre
      <input type="text" id="autor-nombre" class="js-autor-nombre" placeholder="ej. Tatiana" required>
    </label>
    <button type="button" id="btn-insertar-tabla">Insertar tabla</button>
    <button type="button" id="btn-insertar-mermaid">Insertar diagrama</button>
    <button type="button" id="btn-insertar-outline">Insertar esquema</button>
    <button type="button" id="btn-guardar" class="btn-primary">Guardar</button>
    <span id="guardar-estado" class="guardar-estado"></span>
  </div>

  <textarea id="editor" data-archivo-id="<?= $id ?>"><?= h($version['contenido']) ?></textarea>

  <div id="modal-tabla" class="modal hidden">
    <div class="modal-content">
      <h3>Insertar tabla</h3>
      <label>Filas <input type="number" id="tabla-filas" min="1" max="20" value="3"></label>
      <label>Columnas <input type="number" id="tabla-columnas" min="1" max="10" value="3"></label>
      <div class="modal-actions">
        <button type="button" id="tabla-cancelar" class="btn-secondary">Cancelar</button>
        <button type="button" id="tabla-confirmar" class="btn-primary">Insertar</button>
      </div>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script src="assets/js/editor.js"></script>
</body>
</html>
