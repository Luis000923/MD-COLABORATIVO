<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;
use App\Etiqueta;

$usuario = usuarioActual();

$busqueda = trim($_GET['q'] ?? '');
$etiquetaFiltro = isset($_GET['etiqueta']) ? (int) $_GET['etiqueta'] : 0;

if ($busqueda !== '') {
    $todosArchivos = Archivo::buscar($busqueda);
} elseif ($etiquetaFiltro > 0) {
    $todosArchivos = Etiqueta::archivosConEtiqueta($etiquetaFiltro);
} else {
    $todosArchivos = Archivo::listar();
}

// Ocultar documentos privados ajenos: solo se listan si el usuario es dueño.
$archivos = array_values(array_filter($todosArchivos, function (array $archivo) use ($usuario) {
    if (!$archivo['es_privado']) {
        return true;
    }
    return $usuario !== null && (int) $archivo['usuario_id'] === (int) $usuario['id'];
}));

$etiquetas = Etiqueta::todas();
$error = $_GET['error'] ?? null;
$mensajeIndex = flashLeer('mensaje_index');
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
  <p class="userbar">
    <?php if ($usuario): ?>
      Hola, <?= h($usuario['username']) ?> ·
      <form action="logout.php" method="post" class="inline-logout">
        <?= csrfField() ?>
        <button type="submit" class="link-button">Cerrar sesión</button>
      </form>
    <?php else: ?>
      <a href="login.php">Iniciar sesión</a> · <a href="registro.php">Registrarse</a>
    <?php endif; ?>
  </p>
</header>

<main class="container">

  <?php if ($mensajeIndex): ?>
    <p class="alert alert-ok"><?= h($mensajeIndex) ?></p>
  <?php endif; ?>

  <section class="panel">
    <h2>Subir nuevo documento</h2>
    <?php if ($error): ?>
      <p class="alert alert-error"><?= h($error) ?></p>
    <?php endif; ?>
    <?php if ($usuario === null): ?>
      <p class="empty">Debes <a href="login.php">iniciar sesión</a> para subir documentos.</p>
    <?php else: ?>
      <form action="upload.php" method="post" enctype="multipart/form-data" class="form-upload">
        <?= csrfField() ?>
        <label>
          Archivo (.md)
          <input type="file" name="archivo" accept=".md,.markdown,text/markdown" required>
        </label>
        <label>
          Tu nombre
          <input type="text" name="autor_nombre" placeholder="ej. Tatiana" required class="js-autor-nombre">
        </label>
        <label class="form-privado-opciones">
          <input type="checkbox" id="es-privado" name="es_privado">
          Privado
        </label>
        <div id="campos-privado" class="hidden">
          <label>
            Contraseña de vista
            <input type="password" name="password_vista" minlength="4" id="password-vista">
          </label>
          <label>
            Código de edición (6 dígitos)
            <input type="text" name="codigo_edicion" pattern="\d{6}" maxlength="6" id="codigo-edicion" placeholder="123456">
          </label>
        </div>
        <button type="submit">Subir</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="panel">
    <form action="index.php" method="get" class="form-busqueda" role="search">
      <label class="visually-hidden" for="q">Buscar documentos</label>
      <input type="search" id="q" name="q" placeholder="Buscar por nombre o contenido…" value="<?= h($busqueda) ?>">
      <button type="submit" class="btn-primary">Buscar</button>
      <?php if ($busqueda !== '' || $etiquetaFiltro > 0): ?>
        <a href="index.php" class="btn btn-secondary">Limpiar</a>
      <?php endif; ?>
    </form>
    <?php if ($etiquetas): ?>
      <p class="etiqueta-lista">
        <?php foreach ($etiquetas as $et): ?>
          <a class="tag-etiqueta<?= $etiquetaFiltro === (int) $et['id'] ? ' activa' : '' ?>"
             href="index.php?etiqueta=<?= (int) $et['id'] ?>"><?= h($et['nombre']) ?> <span class="hint-key"><?= (int) $et['total'] ?></span></a>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>
  </section>

  <section class="panel">
    <h2>
      <?php if ($busqueda !== ''): ?>
        Resultados para "<?= h($busqueda) ?>" (<?= count($archivos) ?>)
      <?php elseif ($etiquetaFiltro > 0): ?>
        Documentos etiquetados (<?= count($archivos) ?>)
      <?php else: ?>
        Documentos (<?= count($archivos) ?>)
      <?php endif; ?>
    </h2>
    <?php if (empty($archivos)): ?>
      <p class="empty">
        <?php if ($busqueda !== '' || $etiquetaFiltro > 0): ?>
          No se encontraron documentos con ese criterio.
        <?php else: ?>
          Aún no hay documentos. Sube el primero arriba.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <ul class="file-list">
        <?php foreach ($archivos as $archivo): ?>
          <li class="file-item">
            <a href="view.php?id=<?= (int) $archivo['id'] ?>" class="file-name">
              <?= h($archivo['nombre']) ?>
            </a>
            <?php if ($archivo['es_privado']): ?>
              <span class="tag-privado">Privado</span>
            <?php else: ?>
              <span class="tag-neutral">Público</span>
            <?php endif; ?>
            <span class="file-meta">
              v<?= (int) $archivo['total_versiones'] ?> · actualizado <?= h($archivo['actualizado_en']) ?>
            </span>
            <span class="file-actions">
              <a href="view.php?id=<?= (int) $archivo['id'] ?>">Ver</a>
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
  if (input) {
    var saved = localStorage.getItem('md_autor_nombre');
    if (saved) input.value = saved;
    input.addEventListener('change', function () {
      localStorage.setItem('md_autor_nombre', input.value);
    });
  }

  var checkbox = document.getElementById('es-privado');
  var camposPrivado = document.getElementById('campos-privado');
  if (!checkbox || !camposPrivado) return;
  checkbox.addEventListener('change', function () {
    camposPrivado.classList.toggle('hidden', !checkbox.checked);
  });
})();
</script>
</body>
</html>
