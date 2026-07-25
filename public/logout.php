<?php

require __DIR__ . '/bootstrap.php';

// Cerrar sesión solo por POST con CSRF válido para evitar logout forzado por
// terceros (S2).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

verificarCsrf();

$_SESSION = [];
session_destroy();

redirect('index.php');
