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

if (nivelAcceso($id) !== 'edicion') {
    http_response_code(403);
    echo 'No tienes permiso para editar este documento.';
    exit;
}

$version = Version::actual($id);

// La hoja de EasyMDE va antes que app.css: así las reglas de tema de la app
// (que adaptan el editor al modo oscuro) ganan por orden de cascada.
$headExtra = '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>' . "\n"
    . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde@2/dist/easymde.min.css">' . "\n";
?>
<?= htmlHead('Editando ' . $archivo['nombre'], $headExtra) ?>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="topbar">
  <h1><a href="index.php" class="link-plain">Documentos</a></h1>
  <?= userbarHtml('edit.php?id=' . $id) ?>
</header>

<main class="container container-wide" id="contenido">
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
    <span id="guardar-estado" class="guardar-estado" role="status" aria-live="polite"></span>
    <button type="button" id="btn-guardar" class="btn-primary">Guardar</button>
  </div>

  <label class="visually-hidden" for="editor">Contenido del documento en Markdown</label>
  <textarea id="editor"
            data-archivo-id="<?= $id ?>"
            data-base-version="<?= (int) $version['numero_version'] ?>"
            data-csrf="<?= h(csrfToken()) ?>"><?= h($version['contenido']) ?></textarea>

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

<script src="https://cdn.jsdelivr.net/npm/easymde@2/dist/easymde.min.js"></script>
<script src="<?= h(asset('assets/js/editor.js')) ?>"></script>
</body>
</html>
