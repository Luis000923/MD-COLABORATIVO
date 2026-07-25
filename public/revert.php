<?php

require __DIR__ . '/bootstrap.php';

use App\Version;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verificarCsrf();

$versionId = (int) ($_POST['version_id'] ?? 0);
$autorNombre = trim($_POST['autor_nombre'] ?? '');

$versionOriginal = Version::obtener($versionId);
if ($versionOriginal === null) {
    http_response_code(404);
    echo 'Versión no encontrada.';
    exit;
}

if (nivelAcceso((int) $versionOriginal['archivo_id']) !== 'edicion') {
    http_response_code(403);
    echo 'No tienes permiso para revertir este documento.';
    exit;
}

if ($autorNombre === '') {
    redirect('history.php?id=' . $versionOriginal['archivo_id'] . '&mensaje=' . urlencode('Debes indicar tu nombre para revertir.'));
}

Version::revertirA($versionId, $autorNombre);

redirect('history.php?id=' . $versionOriginal['archivo_id'] . '&mensaje=' . urlencode('Documento revertido a v' . $versionOriginal['numero_version'] . '.'));
