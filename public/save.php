<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;
use App\Version;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$archivoId = (int) ($_POST['archivo_id'] ?? 0);
$autorNombre = trim($_POST['autor_nombre'] ?? '');
$contenido = $_POST['contenido'] ?? '';

if ($archivoId <= 0 || Archivo::obtener($archivoId) === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Documento no encontrado']);
    exit;
}

if (nivelAcceso($archivoId) !== 'edicion') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'No tienes permiso para editar este documento']);
    exit;
}

if ($autorNombre === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Falta el nombre del autor']);
    exit;
}

if (!mb_check_encoding($contenido, 'UTF-8')) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Contenido con codificación inválida']);
    exit;
}

$versionId = Version::crear($archivoId, $contenido, $autorNombre);
$version = Version::obtener($versionId);

echo json_encode([
    'ok' => true,
    'version_id' => $versionId,
    'numero_version' => $version['numero_version'],
]);
