<?php

require __DIR__ . '/bootstrap.php';

use App\Usuario;
use App\RateLimit;

if (usuarioActual() !== null) {
    redirect('index.php');
}

$error = $_GET['error'] ?? null;
$next = destinoSeguro($_GET['next'] ?? $_POST['next'] ?? 'index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Rate-limit por IP para frenar fuerza bruta de cuentas (S8).
    $claveLimite = 'login:' . RateLimit::ip();
    if (RateLimit::bloqueado($claveLimite, 10, 900)) {
        redirect('login.php?error=' . urlencode('Demasiados intentos. Espera unos minutos e inténtalo de nuevo.') . '&next=' . urlencode($next));
    }

    $usuario = Usuario::verificarLogin($username, $password);
    if ($usuario === null) {
        RateLimit::registrarFallo($claveLimite);
        redirect('login.php?error=' . urlencode('Usuario o contraseña incorrectos.') . '&next=' . urlencode($next));
    }

    RateLimit::limpiar($claveLimite);
    iniciarSesionUsuario((int) $usuario['id']);
    redirect($next);
}
?>
<?= htmlHead('Iniciar sesión') ?>
<body>
<a class="skip-link" href="#contenido">Saltar al contenido</a>
<header class="topbar">
  <h1><a href="index.php" class="link-plain">Documentos</a></h1>
</header>

<main class="container" id="contenido">
  <section class="panel">
    <h2>Iniciar sesión</h2>
    <?php if ($error): ?>
      <p class="alert alert-error"><?= h($error) ?></p>
    <?php endif; ?>
    <form action="login.php" method="post" class="form-auth">
      <?= csrfField() ?>
      <input type="hidden" name="next" value="<?= h($next) ?>">
      <label>
        Usuario
        <input type="text" name="username" required autofocus autocomplete="username">
      </label>
      <label>
        Contraseña
        <input type="password" name="password" required autocomplete="current-password">
      </label>
      <button type="submit" class="btn-primary">Entrar</button>
    </form>
    <p class="file-meta">¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
  </section>
</main>
</body>
</html>
