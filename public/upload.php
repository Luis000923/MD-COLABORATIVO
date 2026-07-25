<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

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

$archivoId = Archivo::crear($nombre, $contenido, $autorNombre);

redirect('view.php?id=' . $archivoId);
