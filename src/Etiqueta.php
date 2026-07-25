<?php

namespace App;

/**
 * Etiquetas / carpetas ligeras para clasificar documentos (F5).
 */
class Etiqueta
{
    public static function todas(): array
    {
        $pdo = getConnection();
        $stmt = $pdo->query(
            'SELECT e.id, e.nombre,
                    (SELECT COUNT(*) FROM archivo_etiqueta ae
                     JOIN archivos a ON a.id = ae.archivo_id
                     WHERE ae.etiqueta_id = e.id AND a.borrado_en IS NULL) AS total
             FROM etiquetas e
             ORDER BY e.nombre'
        );
        return $stmt->fetchAll();
    }

    /**
     * Devuelve el id de la etiqueta, creándola si no existe.
     */
    public static function obtenerOCrear(string $nombre): int
    {
        $nombre = trim($nombre);
        $pdo = getConnection();
        $stmt = $pdo->prepare('SELECT id FROM etiquetas WHERE nombre = ?');
        $stmt->execute([$nombre]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $stmt = $pdo->prepare('INSERT INTO etiquetas (nombre) VALUES (?)');
        $stmt->execute([$nombre]);
        return (int) $pdo->lastInsertId();
    }

    public static function deArchivo(int $archivoId): array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT e.id, e.nombre
             FROM etiquetas e
             JOIN archivo_etiqueta ae ON ae.etiqueta_id = e.id
             WHERE ae.archivo_id = ?
             ORDER BY e.nombre'
        );
        $stmt->execute([$archivoId]);
        return $stmt->fetchAll();
    }

    /**
     * Reemplaza el conjunto de etiquetas de un documento por la lista dada
     * (nombres de etiqueta). Crea las que falten.
     */
    public static function asignar(int $archivoId, array $nombres): void
    {
        $pdo = getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('DELETE FROM archivo_etiqueta WHERE archivo_id = ?');
            $stmt->execute([$archivoId]);

            $ins = $pdo->prepare(
                'INSERT IGNORE INTO archivo_etiqueta (archivo_id, etiqueta_id) VALUES (?, ?)'
            );
            foreach ($nombres as $nombre) {
                $nombre = trim($nombre);
                if ($nombre === '') {
                    continue;
                }
                $etiquetaId = self::obtenerOCrear($nombre);
                $ins->execute([$archivoId, $etiquetaId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * IDs de documentos (no borrados) que tienen una etiqueta dada.
     */
    public static function archivosConEtiqueta(int $etiquetaId): array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT a.id, a.nombre, a.usuario_id, a.es_privado, a.creado_en, a.actualizado_en,
                    (SELECT COUNT(*) FROM versiones v WHERE v.archivo_id = a.id) AS total_versiones
             FROM archivos a
             JOIN archivo_etiqueta ae ON ae.archivo_id = a.id
             WHERE ae.etiqueta_id = ? AND a.borrado_en IS NULL
             ORDER BY a.actualizado_en DESC'
        );
        $stmt->execute([$etiquetaId]);
        return $stmt->fetchAll();
    }
}
