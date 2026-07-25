<?php

require __DIR__ . '/../vendor/autoload.php';

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

function requiereLogin(): array
{
    $usuario = usuarioActual();
    if ($usuario === null) {
        redirect('login.php?error=' . urlencode('Debes iniciar sesión.'));
    }
    return $usuario;
}

/**
 * Nivel de acceso a un documento privado para la sesión actual:
 * 'edicion' (dueño logueado o código de 6 dígitos desbloqueado),
 * 'lectura' (solo contraseña de vista desbloqueada), o 'ninguno'.
 * Documentos públicos siempre son 'edicion' (mismo comportamiento
 * colaborativo sin restricciones que ya existía antes de las cuentas).
 */
function nivelAcceso(int $archivoId): string
{
    $archivo = \App\Archivo::obtener($archivoId);
    if ($archivo === null) {
        return 'ninguno';
    }

    if (!$archivo['es_privado']) {
        return 'edicion';
    }

    $usuario = usuarioActual();
    if ($usuario !== null && \App\Archivo::esDueno($archivoId, $usuario['id'])) {
        return 'edicion';
    }

    $nivelSesion = $_SESSION['acceso_docs'][$archivoId] ?? null;
    return $nivelSesion === 'edicion' || $nivelSesion === 'lectura' ? $nivelSesion : 'ninguno';
}

function marcarAcceso(int $archivoId, string $nivel): void
{
    $_SESSION['acceso_docs'][$archivoId] = $nivel;
}
