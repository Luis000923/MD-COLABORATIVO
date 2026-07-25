<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;

$usuario = requiereLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verificarCsrf();

$autorNombre = trim($_POST['autor_nombre'] ?? '');
if ($autorNombre === '') {
    redirect('index.php?error=' . urlencode('Debes indicar tu nombre.'));
}

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    redirect('index.php?error=' . urlencode('No se pudo subir el archivo.'));
}

$nombreOriginal = $_FILES['archivo']['name'];
$extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

if (!in_array($extension, ['md', 'markdown'], true)) {
    redirect('index.php?error=' . urlencode('Solo se aceptan archivos .md o .markdown.'));
}

$contenido = file_get_contents($_FILES['archivo']['tmp_name']);
if ($contenido === false) {
    redirect('index.php?error=' . urlencode('No se pudo leer el archivo subido.'));
}

// Validar que sea texto plano UTF-8 (no binario disfrazado de .md)
if (!mb_check_encoding($contenido, 'UTF-8')) {
    $contenido = mb_convert_encoding($contenido, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
}

$esPrivado = isset($_POST['es_privado']);
$passwordVista = null;
$codigoEdicion = null;

if ($esPrivado) {
    $passwordVista = $_POST['password_vista'] ?? '';
    $codigoEdicion = trim($_POST['codigo_edicion'] ?? '');

    if (strlen($passwordVista) < 4) {
        redirect('index.php?error=' . urlencode('La contraseña de vista debe tener al menos 4 caracteres.'));
    }
    if (!preg_match('/^\d{6}$/', $codigoEdicion)) {
        redirect('index.php?error=' . urlencode('El código de edición debe ser de exactamente 6 dígitos.'));
    }
}

$nombre = basename($nombreOriginal);
$original = $nombre;
$sufijo = 1;
while (!Archivo::nombreDisponible($nombre)) {
    $sufijo++;
    $pathInfo = pathinfo($original);
    $base = $pathInfo['filename'];
    $ext = $pathInfo['extension'] ?? 'md';
    $nombre = "{$base} ({$sufijo}).{$ext}";
}

$archivoId = Archivo::crear($nombre, $contenido, $autorNombre, $usuario['id'], $esPrivado, $passwordVista, $codigoEdicion);

// El dueño ya tiene acceso de edición por serlo; no hace falta marcar sesión.
// Los secretos se pasan por flash de sesión, no por URL (S7).
if ($esPrivado) {
    flashGuardar('nueva_credencial', [
        'password' => $passwordVista,
        'codigo' => $codigoEdicion,
    ]);
}

redirect('view.php?id=' . $archivoId);
