<?php

namespace App;

class Enlace
{
    public static function crear(int $archivoId, string $nivel): array
    {
        $nivel = $nivel === 'edicion' ? 'edicion' : 'lectura';
        $pdo = getConnection();

        do {
            $token = self::generarToken();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM enlaces WHERE token = ?');
            $stmt->execute([$token]);
        } while ((int) $stmt->fetchColumn() > 0);

        $stmt = $pdo->prepare('INSERT INTO enlaces (archivo_id, token, nivel) VALUES (?, ?, ?)');
        $stmt->execute([$archivoId, $token, $nivel]);

        return ['id' => (int) $pdo->lastInsertId(), 'token' => $token, 'nivel' => $nivel];
    }

    public static function listarPorArchivo(int $archivoId): array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, token, nivel, creado_en FROM enlaces WHERE archivo_id = ? ORDER BY creado_en DESC'
        );
        $stmt->execute([$archivoId]);
        return $stmt->fetchAll();
    }

    public static function obtenerPorToken(string $token): ?array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT id, archivo_id, nivel FROM enlaces WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function borrar(int $id, int $archivoId): void
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('DELETE FROM enlaces WHERE id = ? AND archivo_id = ?');
        $stmt->execute([$id, $archivoId]);
    }

    /**
     * Token base62 de 8 caracteres (~47 bits). No es un secreto tecleado por
     * un humano como el código de 6 dígitos, así que no necesita rate-limit:
     * el espacio de búsqueda hace inviable la fuerza bruta por URL.
     */
    private static function generarToken(): string
    {
        $alfabeto = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $token = '';
        for ($i = 0; $i < 8; $i++) {
            $token .= $alfabeto[random_int(0, 61)];
        }
        return $token;
    }
}
