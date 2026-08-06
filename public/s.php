<?php

require __DIR__ . '/bootstrap.php';

use App\Enlace;

$token = trim($_GET['t'] ?? '');
$enlace = $token !== '' ? Enlace::obtenerPorToken($token) : null;

if ($enlace === null) {
    http_response_code(404);
    echo 'Enlace no válido.';
    exit;
}

marcarAcceso((int) $enlace['archivo_id'], $enlace['nivel']);
redirect('view.php?id=' . (int) $enlace['archivo_id']);
