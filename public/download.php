<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;
use App\Version;

$id = (int) ($_GET['id'] ?? 0);
$archivo = Archivo::obtener($id);

if ($archivo === null) {
    http_response_code(404);
    echo 'Documento no encontrado.';
    exit;
}

if (nivelAcceso($id) !== 'edicion') {
    redirect('view.php?id=' . $id);
}

$version = Version::actual($id);

$nombreDescarga = $archivo['nombre'];
if (!preg_match('/\.(md|markdown)$/i', $nombreDescarga)) {
    $nombreDescarga .= '.md';
}

header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="' . rawurlencode($nombreDescarga) . '"');
header('Content-Length: ' . strlen($version['contenido']));
echo $version['contenido'];
