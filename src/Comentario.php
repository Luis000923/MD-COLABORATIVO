<?php

namespace App;

/**
 * Comentarios por documento (F6). Autor por nombre para invitados con acceso,
 * o vinculado a un usuario si está logueado.
 */
class Comentario
{
    public static function crear(int $archivoId, ?int $usuarioId, string $autorNombre, string $cuerpo): int
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO comentarios (archivo_id, usuario_id, autor_nombre, cuerpo)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$archivoId, $usuarioId, $autorNombre, $cuerpo]);
        return (int) $pdo->lastInsertId();
    }

    public static function listarPorArchivo(int $archivoId): array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, archivo_id, usuario_id, autor_nombre, cuerpo, creado_en
             FROM comentarios WHERE archivo_id = ? ORDER BY creado_en ASC'
        );
        $stmt->execute([$archivoId]);
        return $stmt->fetchAll();
    }

    public static function obtener(int $id): ?array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, archivo_id, usuario_id, autor_nombre, cuerpo, creado_en
             FROM comentarios WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function borrar(int $id): void
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('DELETE FROM comentarios WHERE id = ?');
        $stmt->execute([$id]);
    }
}
