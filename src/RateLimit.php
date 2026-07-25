<?php

namespace App;

/**
 * Rate-limit simple basado en tabla (S3, S8). Cuenta intentos fallidos por
 * clave (ej. "login:usuario" o "doc:5:IP") dentro de una ventana de tiempo.
 * No frena un atacante distribuido, pero corta la fuerza bruta trivial contra
 * códigos de 6 dígitos y contraseñas de vista desde una sola IP.
 */
class RateLimit
{
    /**
     * ¿La clave está bloqueada por exceso de intentos?
     */
    public static function bloqueado(string $clave, int $maxIntentos = 5, int $ventanaSegundos = 900): bool
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM intentos_acceso
             WHERE clave = ? AND creado_en > (NOW() - INTERVAL ? SECOND)'
        );
        $stmt->execute([$clave, $ventanaSegundos]);
        return ((int) $stmt->fetchColumn()) >= $maxIntentos;
    }

    /**
     * Registra un intento fallido.
     */
    public static function registrarFallo(string $clave): void
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('INSERT INTO intentos_acceso (clave) VALUES (?)');
        $stmt->execute([$clave]);
    }

    /**
     * Limpia los intentos de una clave tras un acceso exitoso.
     */
    public static function limpiar(string $clave): void
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('DELETE FROM intentos_acceso WHERE clave = ?');
        $stmt->execute([$clave]);
    }

    /**
     * IP del cliente, normalizada para usar como parte de la clave.
     */
    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
    }
}
