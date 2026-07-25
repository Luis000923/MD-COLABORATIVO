<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;
use App\Diff;
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
    echo 'No tienes permiso para ver los cambios de este documento.';
    exit;
}

$numeroActual = Version::numeroActual($id);

// 'desde' es la versión más antigua a comparar; 'hasta' por defecto es la
// versión inmediatamente siguiente (para ver qué introdujo cada cambio).
$desde = isset($_GET['desde']) ? (int) $_GET['desde'] : max(1, $numeroActual - 1);
$hasta = isset($_GET['hasta']) ? (int) $_GET['hasta'] : $desde + 1;

if ($hasta > $numeroActual) {
    $hasta = $numeroActual;
}
if ($desde < 1) {
    $desde = 1;
}
if ($desde >= $hasta) {
    $desde = max(1, $hasta - 1);
}

$vDesde = Version::porNumero($id, $desde);
$vHasta = Version::porNumero($id, $hasta);

if ($vDesde === null || $vHasta === null) {
    http_response_code(404);
    echo 'Versión no encontrada.';
    exit;
}

$ops = Diff::lineas($vDesde['contenido'], $vHasta['contenido']);
$resumen = Diff::resumen($ops);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cambios de <?= h($archivo['nombre']) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
  <h1><a href="index.php" class="link-plain">Documentos</a></h1>
</header>

<main class="container">
  <section class="doc-toolbar">
    <div>
      <h2>Cambios: <?= h($archivo['nombre']) ?></h2>
      <p class="file-meta">
        Comparando v<?= $desde ?> → v<?= $hasta ?> ·
        <span class="diff-add-count">+<?= $resumen['add'] ?></span>
        <span class="diff-del-count">−<?= $resumen['del'] ?></span>
      </p>
    </div>
    <div class="doc-actions">
      <a href="history.php?id=<?= $id ?>" class="btn btn-secondary">Historial</a>
      <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Ver documento</a>
    </div>
  </section>

  <form action="diff.php" method="get" class="diff-selector">
    <input type="hidden" name="id" value="<?= $id ?>">
    <label>Desde v
      <select name="desde">
        <?php for ($n = 1; $n <= $numeroActual; $n++): ?>
          <option value="<?= $n ?>"<?= $n === $desde ? ' selected' : '' ?>><?= $n ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <label>Hasta v
      <select name="hasta">
        <?php for ($n = 1; $n <= $numeroActual; $n++): ?>
          <option value="<?= $n ?>"<?= $n === $hasta ? ' selected' : '' ?>><?= $n ?></option>
        <?php endfor; ?>
      </select>
    </label>
    <button type="submit" class="btn-primary">Comparar</button>
  </form>

  <div class="diff-view">
    <?php foreach ($ops as $op): ?>
      <?php
        $clase = 'diff-line diff-igual';
        $signo = ' ';
        if ($op['tipo'] === 'add') { $clase = 'diff-line diff-add'; $signo = '+'; }
        elseif ($op['tipo'] === 'del') { $clase = 'diff-line diff-del'; $signo = '−'; }
      ?>
      <div class="<?= $clase ?>"><span class="diff-signo"><?= $signo ?></span><span class="diff-texto"><?= h($op['texto']) ?></span></div>
    <?php endforeach; ?>
    <?php if (empty($ops)): ?>
      <p class="empty">Sin diferencias.</p>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
