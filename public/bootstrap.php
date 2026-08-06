<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * Cualquier excepción no capturada (típicamente la base de datos caída)
 * terminaba volcando el stack trace con rutas del servidor en el navegador.
 * Se registra en el log y se muestra una página sobria; el detalle solo
 * aparece con APP_DEBUG=true en el .env.
 */
set_exception_handler(function (\Throwable $e): void {
    error_log('[md] ' . $e);

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    $debug = filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL);
    $detalle = $debug
        ? '<pre style="white-space:pre-wrap;overflow-x:auto">'
            . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>'
        : '<p>Si el problema persiste, avisa a quien administra el sitio.</p>';

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Error del servidor</title>'
        . '<link rel="stylesheet" href="assets/css/app.css"></head><body>'
        . '<main class="container"><section class="panel">'
        . '<h2>Algo falló</h2>'
        . '<p class="alert alert-error">No se pudo completar la operación. '
        . 'Suele deberse a que la base de datos no está disponible.</p>'
        . $detalle
        . '<p><a href="index.php" class="btn btn-secondary">Volver al inicio</a></p>'
        . '</section></main></body></html>';
});

// ---- Cookie de sesión endurecida (S6) ----
$cookieSegura = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'secure' => $cookieSegura,
    'samesite' => 'Lax',
]);

session_start();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS']);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * URL de un asset con cache-busting por fecha de modificación. Permite
 * cachear CSS/JS de forma agresiva en el servidor sin servir versiones
 * viejas después de un despliegue.
 */
function asset(string $ruta): string
{
    $absoluta = __DIR__ . '/' . ltrim($ruta, '/');
    $mtime = is_file($absoluta) ? filemtime($absoluta) : false;

    return $mtime === false ? $ruta : $ruta . '?v=' . $mtime;
}

/**
 * <head> común de todas las páginas: metadatos, favicon SVG embebido (evita
 * un 404 de /favicon.ico en cada carga) y la hoja de estilos versionada.
 * $extra permite añadir hojas o etiquetas propias de una página.
 */
function htmlHead(string $titulo, string $extra = ''): string
{
    $favicon = rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
        . '<rect width="32" height="32" rx="7" fill="#c98a4b"/>'
        . '<path d="M8 23V9h3.4l4.6 7 4.6-7H24v14h-3.3v-8.5L16 21l-4.7-6.5V23z" fill="#16130d"/>'
        . '</svg>'
    );

    return '<!doctype html>' . "\n"
        . '<html lang="es">' . "\n"
        . '<head>' . "\n"
        . '<meta charset="utf-8">' . "\n"
        . '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n"
        . '<meta name="color-scheme" content="dark light">' . "\n"
        . '<title>' . h($titulo) . '</title>' . "\n"
        . '<link rel="icon" href="data:image/svg+xml,' . $favicon . '">' . "\n"
        . $extra
        . '<link rel="stylesheet" href="' . h(asset('assets/css/app.css')) . '">' . "\n"
        . '</head>';
}

/**
 * Barra de sesión de la cabecera. Va en un <div>: contiene el formulario de
 * logout (POST + CSRF) y un <form> dentro de un <p> es HTML inválido — el
 * navegador cerraba el párrafo antes del formulario y rompía la línea.
 */
function userbarHtml(?string $next = null): string
{
    $usuario = usuarioActual();

    if ($usuario !== null) {
        return '<div class="userbar">'
            . '<span>Hola, <span class="usuario-nombre">' . h($usuario['username']) . '</span></span>'
            . '<span class="sep" aria-hidden="true">·</span>'
            . '<form action="logout.php" method="post" class="inline-logout">'
            . csrfField()
            . '<button type="submit" class="link-button">Cerrar sesión</button>'
            . '</form>'
            . '</div>';
    }

    $login = 'login.php';
    if ($next !== null && $next !== '') {
        $login .= '?next=' . urlencode($next);
    }

    return '<div class="userbar">'
        . '<a href="' . h($login) . '">Iniciar sesión</a>'
        . '<span class="sep" aria-hidden="true">·</span>'
        . '<a href="registro.php">Registrarse</a>'
        . '</div>';
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function urlBase(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
}

/**
 * Valida que una URL de redirección sea una ruta local relativa (S10).
 * Rechaza URLs absolutas (http://, //host) y esquemas raros para evitar
 * open-redirect. Si no es segura, cae a un destino por defecto.
 */
function destinoSeguro(?string $url, string $porDefecto = 'index.php'): string
{
    if ($url === null || $url === '') {
        return $porDefecto;
    }
    // Rechazar absolutos y protocol-relative.
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) || str_starts_with($url, '//') || str_starts_with($url, '\\')) {
        return $porDefecto;
    }
    // Solo rutas relativas sin salir del directorio.
    if (str_starts_with($url, '/')) {
        return $porDefecto;
    }
    return $url;
}

// ---- CSRF (S2) ----
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function verificarCsrf(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';
    if ($esperado === '' || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
        http_response_code(419);
        echo 'Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.';
        exit;
    }
}

/**
 * Igual que verificarCsrf pero responde JSON (para endpoints AJAX como save).
 */
function verificarCsrfJson(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';
    if ($esperado === '' || !is_string($enviado) || !hash_equals($esperado, $enviado)) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido. Recarga la página.']);
        exit;
    }
}

// ---- Flash messages en sesión (S7) ----
function flashGuardar(string $clave, $valor): void
{
    $_SESSION['flash'][$clave] = $valor;
}

function flashLeer(string $clave)
{
    if (!isset($_SESSION['flash'][$clave])) {
        return null;
    }
    $valor = $_SESSION['flash'][$clave];
    unset($_SESSION['flash'][$clave]);
    return $valor;
}

function usuarioActual(): ?array
{
    static $usuario = null;
    static $consultado = false;

    if ($consultado) {
        return $usuario;
    }
    $consultado = true;

    if (empty($_SESSION['usuario_id'])) {
        return $usuario = null;
    }

    return $usuario = \App\Usuario::obtenerPorId((int) $_SESSION['usuario_id']);
}

function requiereLogin(?string $next = null): array
{
    $usuario = usuarioActual();
    if ($usuario === null) {
        $destino = 'login.php?error=' . urlencode('Debes iniciar sesión.');
        if ($next !== null) {
            $destino .= '&next=' . urlencode($next);
        }
        redirect($destino);
    }
    return $usuario;
}

/**
 * Inicia sesión de usuario de forma segura, regenerando el id para evitar
 * fijación de sesión (S6).
 */
function iniciarSesionUsuario(int $usuarioId): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $usuarioId;
}

/**
 * Nivel de acceso a un documento para la sesión actual:
 *   'edicion'  — puede ver, editar, revertir, descargar, gestionar.
 *   'lectura'  — solo puede ver el contenido renderizado.
 *   'ninguno'  — no puede ni ver (documento privado bloqueado).
 *
 * Reglas (modelo endurecido, S1):
 *   - Documento PÚBLICO:
 *       · usuario logueado           -> 'edicion' (colaborativo con cuenta)
 *       · anónimo                    -> 'lectura'
 *   - Documento PRIVADO:
 *       · dueño logueado             -> 'edicion'
 *       · colaborador (rol)          -> según rol
 *       · código de 6 dígitos ok     -> 'edicion' (sesión)
 *       · contraseña de vista ok     -> 'lectura' (sesión)
 *       · nada                       -> 'ninguno'
 *
 * El nivel de sesión (desbloqueo por código/contraseña o por un enlace de
 * compartir vía s.php) siempre puede ELEVAR el acceso base, público o
 * privado, pero nunca lo baja por debajo de lo que ya corresponde por
 * cuenta/rol (un dueño no queda en solo-lectura por un enlace viejo).
 */
function nivelAcceso(int $archivoId): string
{
    $archivo = \App\Archivo::obtener($archivoId);
    if ($archivo === null) {
        return 'ninguno';
    }

    $usuario = usuarioActual();
    $base = 'ninguno';

    if (!$archivo['es_privado']) {
        $base = $usuario !== null ? 'edicion' : 'lectura';
    } elseif ($usuario !== null) {
        if (\App\Archivo::esDueno($archivoId, $usuario['id'])) {
            return 'edicion';
        }
        $rol = \App\Archivo::rolColaborador($archivoId, (int) $usuario['id']);
        if ($rol === 'edicion') {
            return 'edicion';
        }
        if ($rol === 'lectura') {
            $base = 'lectura';
        }
    }

    $nivelSesion = $_SESSION['acceso_docs'][$archivoId] ?? null;
    if ($nivelSesion === 'edicion') {
        return 'edicion';
    }
    if ($nivelSesion === 'lectura' && $base !== 'edicion') {
        return 'lectura';
    }

    return $base;
}

function marcarAcceso(int $archivoId, string $nivel): void
{
    $_SESSION['acceso_docs'][$archivoId] = $nivel;
}
