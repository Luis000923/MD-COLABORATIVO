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

$errorDesbloqueo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['secreto_doc'])) {
    $secreto = trim($_POST['secreto_doc']);

    if ($secreto !== '' && Archivo::verificarCodigoEdicion($id, $secreto)) {
        marcarAcceso($id, 'edicion');
    } elseif ($secreto !== '' && Archivo::verificarPasswordVista($id, $secreto)) {
        marcarAcceso($id, 'lectura');
    } else {
        $errorDesbloqueo = 'Código o contraseña incorrectos.';
    }
}

$nivel = nivelAcceso($id);
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
<?php if ($nivel === 'ninguno'): ?>

  <section class="panel">
    <h2><?= h($archivo['nombre']) ?> <span class="tag-privado">Privado</span></h2>
    <p class="file-meta">Este documento es privado. Ingresa la contraseña de vista o el código de edición para verlo.</p>
    <?php if ($errorDesbloqueo): ?>
      <p class="alert alert-error"><?= h($errorDesbloqueo) ?></p>
    <?php endif; ?>
    <form action="view.php?id=<?= $id ?>" method="post" class="form-desbloqueo">
      <label>
        Contraseña de vista o código de 6 dígitos
        <input type="password" name="secreto_doc" required autofocus>
      </label>
      <button type="submit" class="btn-primary">Ver documento</button>
    </form>
  </section>

<?php else:
  $version = Version::actual($id);
  $html = Markdown::toHtml($version['contenido']);
  $soloLectura = $nivel === 'lectura';
?>

  <?php if (isset($_GET['nueva_password'], $_GET['nuevo_codigo'])): ?>
    <p class="alert alert-ok">
      Documento privado creado. Contraseña de vista: <strong><?= h($_GET['nueva_password']) ?></strong> ·
      Código de edición: <strong><?= h($_GET['nuevo_codigo']) ?></strong><br>
      Guarda ambos ahora — no se pueden recuperar después.
    </p>
  <?php endif; ?>

  <section class="doc-toolbar">
    <div>
      <h2>
        <?= h($archivo['nombre']) ?>
        <?php if ($archivo['es_privado']): ?><span class="tag-privado">Privado</span><?php endif; ?>
      </h2>
      <p class="file-meta">
        v<?= (int) $version['numero_version'] ?> por <?= h($version['autor_nombre']) ?> · <?= h($version['creado_en']) ?>
      </p>
    </div>
    <div class="doc-actions">
      <?php if ($nivel === 'edicion'): ?>
        <a href="edit.php?id=<?= $id ?>" class="btn">Editar</a>
        <a href="history.php?id=<?= $id ?>" class="btn btn-secondary">Historial</a>
        <a href="download.php?id=<?= $id ?>" class="btn btn-secondary">Descargar .md</a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary">Volver</a>
    </div>
  </section>

  <?php if ($soloLectura): ?>
    <p class="file-meta">Vista de solo lectura. Copiar y descargar requieren el código de edición del dueño.</p>
  <?php endif; ?>

  <article class="markdown-body<?= $soloLectura ? ' solo-lectura-protegido' : '' ?>">
    <?= $html ?>
  </article>

<?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js"></script>
<script src="assets/js/render.js"></script>
<?php if ($nivel === 'lectura'): ?>
<script>
(function () {
  // Fricción anti-copia, no es una garantía de seguridad: cualquiera con
  // DevTools puede sortear esto. Solo desalienta copiar/pegar casual.
  var protegido = document.querySelector('.solo-lectura-protegido');
  if (!protegido) return;
  protegido.addEventListener('contextmenu', function (e) { e.preventDefault(); });
  protegido.addEventListener('selectstart', function (e) { e.preventDefault(); });
  protegido.addEventListener('copy', function (e) { e.preventDefault(); });
})();
</script>
<?php endif; ?>
</body>
</html>
