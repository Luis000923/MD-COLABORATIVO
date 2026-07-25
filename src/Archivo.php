<?php

namespace App;

class Archivo
{
    public static function listar(): array
    {
        $pdo = getConnection();
        $stmt = $pdo->query(
            'SELECT a.id, a.nombre, a.usuario_id, a.es_privado, a.creado_en, a.actualizado_en,
                    (SELECT COUNT(*) FROM versiones v WHERE v.archivo_id = a.id) AS total_versiones
             FROM archivos a
             ORDER BY a.actualizado_en DESC'
        );
        return $stmt->fetchAll();
    }

    public static function obtener(int $id): ?array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, nombre, usuario_id, es_privado, creado_en, actualizado_en
             FROM archivos WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function crear(
        string $nombre,
        string $contenidoInicial,
        string $autorNombre,
        ?int $usuarioId = null,
        bool $esPrivado = false,
        ?string $passwordVista = null,
        ?string $codigoEdicion = null
    ): int {
        $pdo = getConnection();
        $pdo->beginTransaction();

        try {
            $passwordHash = $esPrivado && $passwordVista !== null
                ? password_hash($passwordVista, PASSWORD_DEFAULT)
                : null;
            $codigoHash = $esPrivado && $codigoEdicion !== null
                ? password_hash($codigoEdicion, PASSWORD_DEFAULT)
                : null;

            $stmt = $pdo->prepare(
                'INSERT INTO archivos (nombre, usuario_id, es_privado, password_hash, codigo_edicion_hash)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$nombre, $usuarioId, $esPrivado ? 1 : 0, $passwordHash, $codigoHash]);
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

    public static function verificarPasswordVista(int $id, string $passwordPlano): bool
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT password_hash FROM archivos WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        return $hash !== false && $hash !== null && password_verify($passwordPlano, $hash);
    }

    public static function verificarCodigoEdicion(int $id, string $codigoPlano): bool
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT codigo_edicion_hash FROM archivos WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        return $hash !== false && $hash !== null && password_verify($codigoPlano, $hash);
    }

    public static function esDueno(int $archivoId, ?int $usuarioId): bool
    {
        if ($usuarioId === null) {
            return false;
        }

        $archivo = self::obtener($archivoId);
        return $archivo !== null && $archivo['usuario_id'] !== null && (int) $archivo['usuario_id'] === $usuarioId;
    }
}
