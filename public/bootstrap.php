<?php

require __DIR__ . '/../vendor/autoload.php';

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

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
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
 */
function nivelAcceso(int $archivoId): string
{
    $archivo = \App\Archivo::obtener($archivoId);
    if ($archivo === null) {
        return 'ninguno';
    }

    $usuario = usuarioActual();

    if (!$archivo['es_privado']) {
        return $usuario !== null ? 'edicion' : 'lectura';
    }

    // Privado a partir de aquí.
    if ($usuario !== null) {
        if (\App\Archivo::esDueno($archivoId, $usuario['id'])) {
            return 'edicion';
        }
        $rol = \App\Archivo::rolColaborador($archivoId, (int) $usuario['id']);
        if ($rol === 'edicion') {
            return 'edicion';
        }
        if ($rol === 'lectura') {
            return 'lectura';
        }
    }

    $nivelSesion = $_SESSION['acceso_docs'][$archivoId] ?? null;
    return $nivelSesion === 'edicion' || $nivelSesion === 'lectura' ? $nivelSesion : 'ninguno';
}

function marcarAcceso(int $archivoId, string $nivel): void
{
    $_SESSION['acceso_docs'][$archivoId] = $nivel;
}
