<?php

namespace App;

class Version
{
    public static function crear(int $archivoId, string $contenido, string $autorNombre, bool $esReversion = false): int
    {
        $pdo = getConnection();

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(numero_version), 0) FROM versiones WHERE archivo_id = ?');
        $stmt->execute([$archivoId]);
        $siguienteNumero = ((int) $stmt->fetchColumn()) + 1;

        $stmt = $pdo->prepare(
            'INSERT INTO versiones (archivo_id, numero_version, autor_nombre, contenido, es_reversion)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$archivoId, $siguienteNumero, $autorNombre, $contenido, $esReversion ? 1 : 0]);
        $versionId = (int) $pdo->lastInsertId();

        Archivo::tocarActualizado($archivoId);

        return $versionId;
    }

    public static function actual(int $archivoId): ?array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, archivo_id, numero_version, autor_nombre, contenido, creado_en, es_reversion
             FROM versiones WHERE archivo_id = ? ORDER BY numero_version DESC LIMIT 1'
        );
        $stmt->execute([$archivoId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listarPorArchivo(int $archivoId): array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, archivo_id, numero_version, autor_nombre, creado_en, es_reversion
             FROM versiones WHERE archivo_id = ? ORDER BY numero_version DESC'
        );
        $stmt->execute([$archivoId]);
        return $stmt->fetchAll();
    }

    public static function obtener(int $versionId): ?array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, archivo_id, numero_version, autor_nombre, contenido, creado_en, es_reversion
             FROM versiones WHERE id = ?'
        );
        $stmt->execute([$versionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Revertir = crear una nueva versión cuyo contenido es una copia de una
     * versión anterior. No borra historial, solo agrega un snapshot nuevo.
     */
    public static function revertirA(int $versionId, string $autorNombre): int
    {
        $version = self::obtener($versionId);
        if ($version === null) {
            throw new \RuntimeException('Versión no encontrada');
        }

        return self::crear($version['archivo_id'], $version['contenido'], $autorNombre, true);
    }
}
