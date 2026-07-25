<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;
use App\Comentario;
use App\Etiqueta;
use App\Markdown;
use App\RateLimit;
use App\Version;

$id = (int) ($_GET['id'] ?? 0);
$archivo = Archivo::obtener($id);

if ($archivo === null) {
    http_response_code(404);
    echo 'Documento no encontrado.';
    exit;
}

$usuario = usuarioActual();
$errorDesbloqueo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['secreto_doc'])) {
    verificarCsrf();

    // Rate-limit del desbloqueo por IP+documento (S3): corta fuerza bruta
    // contra el código de 6 dígitos y la contraseña de vista.
    $claveLimite = 'doc:' . $id . ':' . RateLimit::ip();
    if (RateLimit::bloqueado($claveLimite, 8, 900)) {
        $errorDesbloqueo = 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.';
    } else {
        $secreto = trim($_POST['secreto_doc']);

        if ($secreto !== '' && Archivo::verificarCodigoEdicion($id, $secreto)) {
            RateLimit::limpiar($claveLimite);
            marcarAcceso($id, 'edicion');
        } elseif ($secreto !== '' && Archivo::verificarPasswordVista($id, $secreto)) {
            RateLimit::limpiar($claveLimite);
            marcarAcceso($id, 'lectura');
        } else {
            RateLimit::registrarFallo($claveLimite);
            $errorDesbloqueo = 'Código o contraseña incorrectos.';
        }
    }
}

$nivel = nivelAcceso($id);
$credencialNueva = flashLeer('nueva_credencial');
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
  <p class="userbar">
    <?php if ($usuario): ?>
      Hola, <?= h($usuario['username']) ?> ·
      <form action="logout.php" method="post" class="inline-logout">
        <?= csrfField() ?>
        <button type="submit" class="link-button">Cerrar sesión</button>
      </form>
    <?php else: ?>
      <a href="login.php?next=<?= h(urlencode('view.php?id=' . $id)) ?>">Iniciar sesión</a> · <a href="registro.php">Registrarse</a>
    <?php endif; ?>
  </p>
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
      <?= csrfField() ?>
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
  $etiquetas = Etiqueta::deArchivo($id);
  $comentarios = Comentario::listarPorArchivo($id);
?>

  <?php if ($credencialNueva): ?>
    <p class="alert alert-ok">
      Documento privado creado. Contraseña de vista: <strong><?= h($credencialNueva['password']) ?></strong> ·
      Código de edición: <strong><?= h($credencialNueva['codigo']) ?></strong><br>
      Guarda ambos ahora — no se pueden recuperar después.
    </p>
  <?php endif; ?>

  <section class="doc-toolbar">
    <div>
      <h2>
        <?= h($archivo['nombre']) ?>
        <?php if ($archivo['es_privado']): ?>
          <span class="tag-privado">Privado</span>
        <?php else: ?>
          <span class="tag-neutral">Público</span>
        <?php endif; ?>
      </h2>
      <p class="file-meta">
        v<?= (int) $version['numero_version'] ?> por <?= h($version['autor_nombre']) ?> · <?= h($version['creado_en']) ?>
      </p>
      <?php if ($etiquetas): ?>
        <p class="etiqueta-lista">
          <?php foreach ($etiquetas as $et): ?>
            <a class="tag-etiqueta" href="index.php?etiqueta=<?= (int) $et['id'] ?>"><?= h($et['nombre']) ?></a>
          <?php endforeach; ?>
        </p>
      <?php endif; ?>
    </div>
    <div class="doc-actions">
      <?php if ($nivel === 'edicion'): ?>
        <a href="edit.php?id=<?= $id ?>" class="btn">Editar</a>
        <a href="history.php?id=<?= $id ?>" class="btn btn-secondary">Historial</a>
        <a href="download.php?id=<?= $id ?>" class="btn btn-secondary">Descargar .md</a>
        <a href="export.php?id=<?= $id ?>" class="btn btn-secondary">Exportar HTML</a>
        <a href="gestion.php?id=<?= $id ?>" class="btn btn-secondary">Gestionar</a>
      <?php endif; ?>
      <a href="index.php" class="btn btn-secondary">Volver</a>
    </div>
  </section>

  <?php if ($soloLectura): ?>
    <p class="file-meta">Vista de solo lectura.<?php if ($archivo['es_privado']): ?> Copiar y descargar requieren el código de edición del dueño.<?php endif; ?></p>
  <?php endif; ?>

  <article class="markdown-body<?= $soloLectura && $archivo['es_privado'] ? ' solo-lectura-protegido' : '' ?>">
    <?= $html ?>
  </article>

  <section class="panel comentarios">
    <h2>Comentarios (<?= count($comentarios) ?>)</h2>
    <?php if ($comentarios): ?>
      <ul class="comentario-lista">
        <?php foreach ($comentarios as $c): ?>
          <li class="comentario-item">
            <div class="comentario-head">
              <span class="comentario-autor"><?= h($c['autor_nombre']) ?></span>
              <span class="file-meta"><?= h($c['creado_en']) ?></span>
              <?php if ($nivel === 'edicion'): ?>
                <form action="comentario.php" method="post" class="comentario-borrar" onsubmit="return confirm('¿Borrar este comentario?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="accion" value="borrar">
                  <input type="hidden" name="comentario_id" value="<?= (int) $c['id'] ?>">
                  <input type="hidden" name="archivo_id" value="<?= $id ?>">
                  <button type="submit" class="link-button link-danger">Borrar</button>
                </form>
              <?php endif; ?>
            </div>
            <p class="comentario-cuerpo"><?= nl2br(h($c['cuerpo'])) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="empty">Aún no hay comentarios.</p>
    <?php endif; ?>

    <form action="comentario.php" method="post" class="comentario-form">
      <?= csrfField() ?>
      <input type="hidden" name="accion" value="crear">
      <input type="hidden" name="archivo_id" value="<?= $id ?>">
      <label>
        Tu nombre
        <input type="text" name="autor_nombre" class="js-autor-nombre" placeholder="ej. Tatiana" required
               value="<?= $usuario ? h($usuario['username']) : '' ?>">
      </label>
      <label>
        Comentario
        <textarea name="cuerpo" rows="3" required></textarea>
      </label>
      <button type="submit" class="btn-primary">Comentar</button>
    </form>
  </section>

<?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>
<script src="assets/js/render.js"></script>
<?php if ($nivel === 'lectura' && $archivo['es_privado']): ?>
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
<script>
(function () {
  var input = document.querySelector('.js-autor-nombre');
  if (!input) return;
  if (!input.value) {
    var saved = localStorage.getItem('md_autor_nombre');
    if (saved) input.value = saved;
  }
  input.addEventListener('change', function () {
    localStorage.setItem('md_autor_nombre', input.value);
  });
})();
</script>
</body>
</html>
