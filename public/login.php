<?php

require __DIR__ . '/bootstrap.php';

use App\Usuario;

if (usuarioActual() !== null) {
    redirect('index.php');
}

$error = $_GET['error'] ?? null;
$next = $_GET['next'] ?? $_POST['next'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $usuario = Usuario::verificarLogin($username, $password);
    if ($usuario === null) {
        redirect('login.php?error=' . urlencode('Usuario o contraseña incorrectos.') . '&next=' . urlencode($next));
    }

    $_SESSION['usuario_id'] = $usuario['id'];
    redirect($next);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
  <h1><a href="index.php" class="link-plain">Documentos</a></h1>
</header>

<main class="container">
  <section class="panel">
    <h2>Iniciar sesión</h2>
    <?php if ($error): ?>
      <p class="alert alert-error"><?= h($error) ?></p>
    <?php endif; ?>
    <form action="login.php" method="post" class="form-auth">
      <input type="hidden" name="next" value="<?= h($next) ?>">
      <label>
        Usuario
        <input type="text" name="username" required autofocus>
      </label>
      <label>
        Contraseña
        <input type="password" name="password" required>
      </label>
      <button type="submit" class="btn-primary">Entrar</button>
    </form>
    <p class="file-meta">¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>
  </section>
</main>
</body>
</html>
