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
    echo 'No tienes permiso para ver el historial de este documento.';
    exit;
}

$versiones = Version::listarPorArchivo($id);
$mensaje = $_GET['mensaje'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Historial de <?= h($archivo['nombre']) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
  <h1><a href="index.php" class="link-plain">Documentos</a></h1>
</header>

<main class="container">
  <section class="doc-toolbar">
    <div>
      <h2>Historial: <?= h($archivo['nombre']) ?></h2>
      <p class="file-meta"><?= count($versiones) ?> versiones</p>
    </div>
    <div class="doc-actions">
      <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Ver documento</a>
      <a href="gestion.php?id=<?= $id ?>" class="btn btn-secondary">Gestionar</a>
      <a href="index.php" class="btn btn-secondary">Volver</a>
    </div>
  </section>

  <?php if ($mensaje): ?>
    <p class="alert alert-ok"><?= h($mensaje) ?></p>
  <?php endif; ?>

  <div class="tabla-scroll">
  <table class="history-table">
    <thead>
      <tr>
        <th>Versión</th>
        <th>Autor</th>
        <th>Fecha</th>
        <th>Tipo</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($versiones as $i => $v): ?>
        <tr>
          <td>v<?= (int) $v['numero_version'] ?></td>
          <td><?= h($v['autor_nombre']) ?></td>
          <td><?= h($v['creado_en']) ?></td>
          <td><?= $v['es_reversion'] ? 'Reversión' : 'Edición' ?></td>
          <td class="history-acciones">
            <?php if ($i === 0): ?>
              <span class="tag-actual">Actual</span>
            <?php else: ?>
              <a href="diff.php?id=<?= $id ?>&desde=<?= (int) $v['numero_version'] ?>" class="btn-inline">Ver cambios</a>
              <form action="revert.php" method="post" class="inline-form" onsubmit="return confirm('¿Revertir el documento a esta versión? Esto crea una nueva versión con este contenido, no se pierde el historial.');">
                <?= csrfField() ?>
                <input type="hidden" name="version_id" value="<?= (int) $v['id'] ?>">
                <input type="text" name="autor_nombre" placeholder="Tu nombre" required class="js-autor-nombre-inline">
                <button type="submit">Revertir a esta</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</main>

<script>
(function () {
  var saved = localStorage.getItem('md_autor_nombre');
  document.querySelectorAll('.js-autor-nombre-inline').forEach(function (input) {
    if (saved) input.value = saved;
    input.addEventListener('change', function () {
      localStorage.setItem('md_autor_nombre', input.value);
    });
  });
})();
</script>
</body>
</html>
