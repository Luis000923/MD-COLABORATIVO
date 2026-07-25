<?php

namespace App;

class Archivo
{
    public static function listar(): array
    {
        $pdo = getConnection();
        $stmt = $pdo->query(
            'SELECT a.id, a.nombre, a.creado_en, a.actualizado_en,
                    (SELECT COUNT(*) FROM versiones v WHERE v.archivo_id = a.id) AS total_versiones
             FROM archivos a
             ORDER BY a.actualizado_en DESC'
        );
        return $stmt->fetchAll();
    }

    public static function obtener(int $id): ?array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT id, nombre, creado_en, actualizado_en FROM archivos WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function crear(string $nombre, string $contenidoInicial, string $autorNombre): int
    {
        $pdo = getConnection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('INSERT INTO archivos (nombre) VALUES (?)');
            $stmt->execute([$nombre]);
            $archivoId = (int) $pdo->lastInsertId();

            Version::crear($archivoId, $contenidoInicial, $autorNombre);

            $pdo->commit();
            return $archivoId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function tocarActualizado(int $id): void
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('UPDATE archivos SET actualizado_en = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function nombreDisponible(string $nombre): bool
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM archivos WHERE nombre = ?');
        $stmt->execute([$nombre]);
        return ((int) $stmt->fetchColumn()) === 0;
    }
}
