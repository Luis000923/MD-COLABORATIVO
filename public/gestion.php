<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;
use App\Etiqueta;
use App\Usuario;

$id = (int) ($_GET['id'] ?? 0);
$archivo = Archivo::obtener($id);

if ($archivo === null) {
    http_response_code(404);
    echo 'Documento no encontrado.';
    exit;
}

$usuario = usuarioActual();

// Gestionar (renombrar, visibilidad, borrar, colaboradores) es exclusivo del
// dueño logueado. Las etiquetas puede tocarlas cualquiera con edición.
$esDueno = $usuario !== null && Archivo::esDueno($id, (int) $usuario['id']);
$puedeEditar = nivelAcceso($id) === 'edicion';

if (!$puedeEditar) {
    http_response_code(403);
    echo 'No tienes permiso para gestionar este documento.';
    exit;
}

$mensaje = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'renombrar' && $esDueno) {
        $nuevo = trim($_POST['nombre'] ?? '');
        if ($nuevo === '') {
            $error = 'El nombre no puede estar vacío.';
        } else {
            if (!preg_match('/\.(md|markdown)$/i', $nuevo)) {
                $nuevo .= '.md';
            }
            Archivo::renombrar($id, $nuevo);
            $mensaje = 'Documento renombrado.';
            $archivo = Archivo::obtener($id);
        }
    } elseif ($accion === 'visibilidad' && $esDueno) {
        $esPrivado = isset($_POST['es_privado']);
        $passwordVista = $_POST['password_vista'] ?? '';
        $codigoEdicion = trim($_POST['codigo_edicion'] ?? '');

        if ($esPrivado) {
            // Al volver privado por primera vez exigimos ambas credenciales.
            $yaEraPrivado = (bool) $archivo['es_privado'];
            if (!$yaEraPrivado && strlen($passwordVista) < 4) {
                $error = 'La contraseña de vista debe tener al menos 4 caracteres.';
            } elseif (!$yaEraPrivado && !preg_match('/^\d{6}$/', $codigoEdicion)) {
                $error = 'El código de edición debe ser de exactamente 6 dígitos.';
            } elseif ($codigoEdicion !== '' && !preg_match('/^\d{6}$/', $codigoEdicion)) {
                $error = 'El código de edición debe ser de exactamente 6 dígitos.';
            } else {
                Archivo::actualizarVisibilidad($id, true, $passwordVista ?: null, $codigoEdicion ?: null);
                $mensaje = 'Visibilidad y credenciales actualizadas.';
                $archivo = Archivo::obtener($id);
            }
        } else {
            Archivo::actualizarVisibilidad($id, false, null, null);
            $mensaje = 'El documento ahora es público.';
            $archivo = Archivo::obtener($id);
        }
    } elseif ($accion === 'etiquetas') {
        $raw = $_POST['etiquetas'] ?? '';
        $nombres = array_filter(array_map('trim', explode(',', $raw)), fn ($n) => $n !== '');
        Etiqueta::asignar($id, $nombres);
        $mensaje = 'Etiquetas actualizadas.';
    } elseif ($accion === 'colaborador_add' && $esDueno) {
        $username = trim($_POST['username'] ?? '');
        $rol = $_POST['rol'] ?? 'edicion';
        $colaborador = Usuario::obtenerPorUsername($username);
        if ($colaborador === null) {
            $error = 'No existe un usuario con ese nombre.';
        } elseif ((int) $colaborador['id'] === (int) $archivo['usuario_id']) {
            $error = 'El dueño ya tiene acceso total.';
        } else {
            Archivo::agregarColaborador($id, (int) $colaborador['id'], $rol);
            $mensaje = 'Colaborador agregado.';
        }
    } elseif ($accion === 'colaborador_del' && $esDueno) {
        $colaboradorId = (int) ($_POST['usuario_id'] ?? 0);
        Archivo::quitarColaborador($id, $colaboradorId);
        $mensaje = 'Colaborador eliminado.';
    } elseif ($accion === 'borrar' && $esDueno) {
        Archivo::borrar($id);
        flashGuardar('mensaje_index', 'Documento borrado.');
        redirect('index.php');
    }
}

$etiquetasActuales = Etiqueta::deArchivo($id);
$etiquetasTexto = implode(', ', array_column($etiquetasActuales, 'nombre'));
$colaboradores = $esDueno ? Archivo::listarColaboradores($id) : [];
?>
<?= htmlHead('Gestionar ' . $archivo['nombre']) ?>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="topbar">
  <h1><a href="index.php" class="link-plain">Documentos</a></h1>
  <?= userbarHtml('gestion.php?id=' . $id) ?>
</header>

<main class="container" id="contenido">
  <section class="doc-toolbar">
    <div>
      <h2>Gestionar: <?= h($archivo['nombre']) ?></h2>
      <p class="file-meta">
        <?= $archivo['es_privado'] ? 'Privado' : 'Público' ?><?= $esDueno ? '' : ' · No eres el dueño (solo puedes editar etiquetas)' ?>
      </p>
    </div>
    <div class="doc-actions">
      <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Ver documento</a>
      <a href="index.php" class="btn btn-secondary">Volver</a>
    </div>
  </section>

  <?php if ($mensaje): ?><p class="alert alert-ok"><?= h($mensaje) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="alert alert-error"><?= h($error) ?></p><?php endif; ?>

  <?php if ($esDueno): ?>
  <section class="panel">
    <h2>Renombrar</h2>
    <form action="gestion.php?id=<?= $id ?>" method="post" class="form-auth">
      <?= csrfField() ?>
      <input type="hidden" name="accion" value="renombrar">
      <label>
        Nombre del documento
        <input type="text" name="nombre" value="<?= h($archivo['nombre']) ?>" required>
      </label>
      <button type="submit" class="btn-primary">Guardar nombre</button>
    </form>
  </section>

  <section class="panel">
    <h2>Visibilidad y credenciales</h2>
    <form action="gestion.php?id=<?= $id ?>" method="post" class="form-auth" id="form-visibilidad">
      <?= csrfField() ?>
      <input type="hidden" name="accion" value="visibilidad">
      <label class="form-privado-opciones">
        <input type="checkbox" name="es_privado" id="ges-privado" aria-controls="ges-campos-privado"
               aria-expanded="<?= $archivo['es_privado'] ? 'true' : 'false' ?>" <?= $archivo['es_privado'] ? 'checked' : '' ?>>
        Privado
      </label>
      <div id="ges-campos-privado" class="campos-privado<?= $archivo['es_privado'] ? '' : ' hidden' ?>">
        <label>
          Contraseña de vista <?= $archivo['es_privado'] ? '(dejar vacío para no cambiar)' : '' ?>
          <input type="password" name="password_vista" minlength="4" autocomplete="new-password">
        </label>
        <label>
          Código de edición (6 dígitos) <?= $archivo['es_privado'] ? '(dejar vacío para no cambiar)' : '' ?>
          <input type="text" name="codigo_edicion" pattern="\d{6}" maxlength="6" inputmode="numeric" placeholder="123456" autocomplete="off">
        </label>
      </div>
      <button type="submit" class="btn-primary">Actualizar visibilidad</button>
    </form>
  </section>
  <?php endif; ?>

  <section class="panel">
    <h2>Etiquetas</h2>
    <form action="gestion.php?id=<?= $id ?>" method="post" class="form-auth">
      <?= csrfField() ?>
      <input type="hidden" name="accion" value="etiquetas">
      <label>
        Etiquetas (separadas por comas)
        <input type="text" name="etiquetas" value="<?= h($etiquetasTexto) ?>" placeholder="proyecto, notas, borrador">
      </label>
      <button type="submit" class="btn-primary">Guardar etiquetas</button>
    </form>
  </section>

  <?php if ($esDueno): ?>
  <section class="panel">
    <h2>Colaboradores</h2>
    <?php if ($colaboradores): ?>
      <ul class="file-list">
        <?php foreach ($colaboradores as $col): ?>
          <li class="file-item">
            <span class="file-name"><?= h($col['username']) ?></span>
            <span class="tag-neutral"><?= h($col['rol']) ?></span>
            <div class="file-actions">
              <form action="gestion.php?id=<?= $id ?>" method="post" class="inline-form">
                <?= csrfField() ?>
                <input type="hidden" name="accion" value="colaborador_del">
                <input type="hidden" name="usuario_id" value="<?= (int) $col['usuario_id'] ?>">
                <button type="submit" class="link-button link-danger">Quitar</button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="empty">Sin colaboradores. Agrega usuarios registrados para darles acceso a este documento privado.</p>
    <?php endif; ?>
    <form action="gestion.php?id=<?= $id ?>" method="post" class="inline-form form-colaborador">
      <?= csrfField() ?>
      <input type="hidden" name="accion" value="colaborador_add">
      <label class="visually-hidden" for="colaborador-username">Usuario a agregar</label>
      <input type="text" id="colaborador-username" name="username" placeholder="usuario" required>
      <label class="visually-hidden" for="colaborador-rol">Rol del colaborador</label>
      <select id="colaborador-rol" name="rol">
        <option value="edicion">Edición</option>
        <option value="lectura">Lectura</option>
      </select>
      <button type="submit" class="btn-primary">Agregar</button>
    </form>
  </section>

  <section class="panel panel-peligro">
    <h2>Zona de peligro</h2>
    <form action="gestion.php?id=<?= $id ?>" method="post"
          onsubmit="return confirm('¿Borrar este documento? Se ocultará de la lista (no se elimina el historial en la base de datos).');">
      <?= csrfField() ?>
      <input type="hidden" name="accion" value="borrar">
      <button type="submit" class="btn-danger">Borrar documento</button>
    </form>
  </section>
  <?php endif; ?>
</main>

<script>
(function () {
  var checkbox = document.getElementById('ges-privado');
  var campos = document.getElementById('ges-campos-privado');
  if (!checkbox || !campos) return;

  function sincronizar() {
    campos.classList.toggle('hidden', !checkbox.checked);
    checkbox.setAttribute('aria-expanded', checkbox.checked ? 'true' : 'false');
  }

  sincronizar();
  checkbox.addEventListener('change', sincronizar);
})();
</script>
</body>
</html>
