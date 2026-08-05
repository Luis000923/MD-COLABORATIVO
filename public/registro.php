<?php

require __DIR__ . '/bootstrap.php';

use App\Usuario;

if (usuarioActual() !== null) {
    redirect('index.php');
}

$error = $_GET['error'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirmacion = $_POST['password_confirmacion'] ?? '';

    if ($username === '' || strlen($username) > 50) {
        redirect('registro.php?error=' . urlencode('Usuario inválido.'));
    } elseif (strlen($password) < 8) {
        redirect('registro.php?error=' . urlencode('La contraseña debe tener al menos 8 caracteres.'));
    } elseif ($password !== $passwordConfirmacion) {
        redirect('registro.php?error=' . urlencode('Las contraseñas no coinciden.'));
    } elseif (!Usuario::usernameDisponible($username)) {
        redirect('registro.php?error=' . urlencode('Ese usuario ya existe.'));
    } else {
        $usuarioId = Usuario::crear($username, $password);
        iniciarSesionUsuario($usuarioId);
        redirect('index.php');
    }
}
?>
<?= htmlHead('Crear cuenta') ?>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="topbar">
  <h1><a href="index.php" class="link-plain">Documentos</a></h1>
</header>

<main class="container" id="contenido">
  <section class="panel">
    <h2>Crear cuenta</h2>
    <?php if ($error): ?>
      <p class="alert alert-error"><?= h($error) ?></p>
    <?php endif; ?>
    <form action="registro.php" method="post" class="form-auth">
      <?= csrfField() ?>
      <label>
        Usuario
        <input type="text" name="username" maxlength="50" required autofocus autocomplete="username">
      </label>
      <label>
        Contraseña
        <input type="password" name="password" minlength="8" required autocomplete="new-password">
      </label>
      <label>
        Confirmar contraseña
        <input type="password" name="password_confirmacion" minlength="8" required autocomplete="new-password">
      </label>
      <button type="submit" class="btn-primary">Crear cuenta</button>
    </form>
    <p class="file-meta">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
  </section>
</main>
</body>
</html>
