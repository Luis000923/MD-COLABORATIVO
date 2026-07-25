<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;

$archivos = Archivo::listar();
$error = $_GET['error'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Documentos</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
  <h1>Documentos</h1>
  <p class="subtitle">Sube, edita y comparte documentos Markdown</p>
</header>

<main class="container">

  <section class="panel">
    <h2>Subir nuevo documento</h2>
    <?php if ($error): ?>
      <p class="alert alert-error"><?= h($error) ?></p>
    <?php endif; ?>
    <form action="upload.php" method="post" enctype="multipart/form-data" class="form-upload">
      <label>
        Archivo (.md)
        <input type="file" name="archivo" accept=".md,.markdown,text/markdown" required>
      </label>
      <label>
        Tu nombre
        <input type="text" name="autor_nombre" placeholder="ej. Tatiana" required class="js-autor-nombre">
      </label>
      <button type="submit">Subir</button>
    </form>
  </section>

  <section class="panel">
    <h2>Documentos (<?= count($archivos) ?>)</h2>
    <?php if (empty($archivos)): ?>
      <p class="empty">Aún no hay documentos. Sube el primero arriba.</p>
    <?php else: ?>
      <ul class="file-list">
        <?php foreach ($archivos as $archivo): ?>
          <li class="file-item">
            <a href="view.php?id=<?= (int) $archivo['id'] ?>" class="file-name">
              <?= h($archivo['nombre']) ?>
            </a>
            <span class="file-meta">
              v<?= (int) $archivo['total_versiones'] ?> · actualizado <?= h($archivo['actualizado_en']) ?>
            </span>
            <span class="file-actions">
              <a href="edit.php?id=<?= (int) $archivo['id'] ?>">Editar</a>
              <a href="history.php?id=<?= (int) $archivo['id'] ?>">Historial</a>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

</main>

<script>
(function () {
  var input = document.querySelector('.js-autor-nombre');
  if (!input) return;
  var saved = localStorage.getItem('md_autor_nombre');
  if (saved) input.value = saved;
  input.addEventListener('change', function () {
    localStorage.setItem('md_autor_nombre', input.value);
  });
})();
</script>
</body>
</html>
