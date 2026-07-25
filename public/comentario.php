<?php

require __DIR__ . '/bootstrap.php';

use App\Archivo;
use App\Comentario;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verificarCsrf();

$archivoId = (int) ($_POST['archivo_id'] ?? 0);
$accion = $_POST['accion'] ?? '';
$archivo = Archivo::obtener($archivoId);

if ($archivo === null) {
    http_response_code(404);
    echo 'Documento no encontrado.';
    exit;
}

$nivel = nivelAcceso($archivoId);
if ($nivel === 'ninguno') {
    http_response_code(403);
    echo 'No tienes acceso a este documento.';
    exit;
}

$usuario = usuarioActual();

if ($accion === 'crear') {
    $autorNombre = trim($_POST['autor_nombre'] ?? '');
    $cuerpo = trim($_POST['cuerpo'] ?? '');

    if ($autorNombre === '' || $cuerpo === '') {
        redirect('view.php?id=' . $archivoId);
    }
    if (mb_strlen($cuerpo) > 5000) {
        $cuerpo = mb_substr($cuerpo, 0, 5000);
    }

    Comentario::crear($archivoId, $usuario['id'] ?? null, $autorNombre, $cuerpo);
    redirect('view.php?id=' . $archivoId . '#comentarios');
}

if ($accion === 'borrar') {
    // Borrar comentarios requiere nivel de edición sobre el documento.
    if ($nivel !== 'edicion') {
        http_response_code(403);
        echo 'No tienes permiso para borrar comentarios.';
        exit;
    }
    $comentarioId = (int) ($_POST['comentario_id'] ?? 0);
    $comentario = Comentario::obtener($comentarioId);
    if ($comentario !== null && (int) $comentario['archivo_id'] === $archivoId) {
        Comentario::borrar($comentarioId);
    }
    redirect('view.php?id=' . $archivoId . '#comentarios');
}

redirect('view.php?id=' . $archivoId);
