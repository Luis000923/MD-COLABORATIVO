<?php

namespace App;

class Archivo
{
    /**
     * Lista documentos no borrados. Si $usuarioId se pasa, se usa solo para
     * saber el contexto; el filtrado de privados ajenos se hace en la vista.
     */
    public static function listar(): array
    {
        $pdo = getConnection();
        $stmt = $pdo->query(
            'SELECT a.id, a.nombre, a.usuario_id, a.es_privado, a.creado_en, a.actualizado_en,
                    (SELECT COUNT(*) FROM versiones v WHERE v.archivo_id = a.id) AS total_versiones
             FROM archivos a
             WHERE a.borrado_en IS NULL
             ORDER BY a.actualizado_en DESC'
        );
        return $stmt->fetchAll();
    }

    /**
     * Búsqueda full-text por nombre y contenido de la versión actual (F4).
     * Devuelve documentos no borrados que coincidan, con un snippet.
     */
    public static function buscar(string $consulta): array
    {
        $consulta = trim($consulta);
        if ($consulta === '') {
            return [];
        }

        $pdo = getConnection();
        // Coincidencia por nombre O por contenido de cualquier versión.
        $stmt = $pdo->prepare(
            'SELECT DISTINCT a.id, a.nombre, a.usuario_id, a.es_privado, a.creado_en, a.actualizado_en,
                    (SELECT COUNT(*) FROM versiones v2 WHERE v2.archivo_id = a.id) AS total_versiones
             FROM archivos a
             LEFT JOIN versiones v ON v.archivo_id = a.id
             WHERE a.borrado_en IS NULL
               AND (
                    MATCH(a.nombre) AGAINST (:q IN NATURAL LANGUAGE MODE)
                 OR MATCH(v.contenido) AGAINST (:q IN NATURAL LANGUAGE MODE)
                 OR a.nombre LIKE :like
               )
             ORDER BY a.actualizado_en DESC
             LIMIT 50'
        );
        $stmt->execute([':q' => $consulta, ':like' => '%' . $consulta . '%']);
        return $stmt->fetchAll();
    }

    public static function obtener(int $id): ?array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, nombre, usuario_id, es_privado, creado_en, actualizado_en, borrado_en
             FROM archivos WHERE id = ? AND borrado_en IS NULL'
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
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM archivos WHERE nombre = ? AND borrado_en IS NULL');
        $stmt->execute([$nombre]);
        return ((int) $stmt->fetchColumn()) === 0;
    }

    /**
     * Renombra un documento evitando colisiones de nombre (F3).
     */
    public static function renombrar(int $id, string $nuevoNombre): void
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('UPDATE archivos SET nombre = ? WHERE id = ?');
        $stmt->execute([$nuevoNombre, $id]);
    }

    /**
     * Cambia la visibilidad y credenciales de un documento (F3).
     * Al pasar a público se limpian los hashes. Al pasar a privado (o rotar)
     * se guardan los nuevos hashes; si un valor viene null no se toca ese hash.
     */
    public static function actualizarVisibilidad(
        int $id,
        bool $esPrivado,
        ?string $passwordVista,
        ?string $codigoEdicion
    ): void {
        $pdo = getConnection();

        if (!$esPrivado) {
            $stmt = $pdo->prepare(
                'UPDATE archivos SET es_privado = 0, password_hash = NULL, codigo_edicion_hash = NULL WHERE id = ?'
            );
            $stmt->execute([$id]);
            return;
        }

        // Privado: construir SET dinámico para no borrar credenciales existentes
        // cuando el usuario deja un campo vacío (rotación parcial).
        $sets = ['es_privado = 1'];
        $params = [];
        if ($passwordVista !== null && $passwordVista !== '') {
            $sets[] = 'password_hash = ?';
            $params[] = password_hash($passwordVista, PASSWORD_DEFAULT);
        }
        if ($codigoEdicion !== null && $codigoEdicion !== '') {
            $sets[] = 'codigo_edicion_hash = ?';
            $params[] = password_hash($codigoEdicion, PASSWORD_DEFAULT);
        }
        $params[] = $id;

        $stmt = $pdo->prepare('UPDATE archivos SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    /**
     * Soft-delete: marca el documento como borrado sin perder datos (F2).
     */
    public static function borrar(int $id): void
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('UPDATE archivos SET borrado_en = NOW() WHERE id = ?');
        $stmt->execute([$id]);
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

    // ---- Colaboradores granulares (F8) ----

    /**
     * Devuelve el rol del usuario como colaborador ('lectura'|'edicion') o
     * null si no es colaborador.
     */
    public static function rolColaborador(int $archivoId, int $usuarioId): ?string
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT rol FROM archivo_colaborador WHERE archivo_id = ? AND usuario_id = ?'
        );
        $stmt->execute([$archivoId, $usuarioId]);
        $rol = $stmt->fetchColumn();
        return $rol !== false ? $rol : null;
    }

    public static function listarColaboradores(int $archivoId): array
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'SELECT c.usuario_id, u.username, c.rol
             FROM archivo_colaborador c
             JOIN usuarios u ON u.id = c.usuario_id
             WHERE c.archivo_id = ?
             ORDER BY u.username'
        );
        $stmt->execute([$archivoId]);
        return $stmt->fetchAll();
    }

    public static function agregarColaborador(int $archivoId, int $usuarioId, string $rol): void
    {
        $rol = $rol === 'lectura' ? 'lectura' : 'edicion';
        $pdo = getConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO archivo_colaborador (archivo_id, usuario_id, rol) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE rol = VALUES(rol)'
        );
        $stmt->execute([$archivoId, $usuarioId, $rol]);
    }

    public static function quitarColaborador(int $archivoId, int $usuarioId): void
    {
        $pdo = getConnection();
        $stmt = $pdo->prepare('DELETE FROM archivo_colaborador WHERE archivo_id = ? AND usuario_id = ?');
        $stmt->execute([$archivoId, $usuarioId]);
    }
}
