CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS archivos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(255) NOT NULL,
  usuario_id INT NULL,
  es_privado TINYINT(1) NOT NULL DEFAULT 0,
  password_hash VARCHAR(255) NULL,
  codigo_edicion_hash VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  borrado_en DATETIME NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_archivos_usuario (usuario_id),
  INDEX idx_archivos_borrado (borrado_en),
  FULLTEXT INDEX ft_archivos_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS versiones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  archivo_id INT NOT NULL,
  numero_version INT NOT NULL,
  autor_nombre VARCHAR(100) NOT NULL,
  contenido LONGTEXT NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  es_reversion TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE,
  INDEX idx_archivo_version (archivo_id, numero_version),
  FULLTEXT INDEX ft_versiones_contenido (contenido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rate-limit de intentos de acceso (login y desbloqueo de documentos).
CREATE TABLE IF NOT EXISTS intentos_acceso (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(191) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_intentos_clave (clave, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Etiquetas / carpetas ligeras.
CREATE TABLE IF NOT EXISTS etiquetas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL,
  UNIQUE KEY uniq_etiqueta_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS archivo_etiqueta (
  archivo_id INT NOT NULL,
  etiqueta_id INT NOT NULL,
  PRIMARY KEY (archivo_id, etiqueta_id),
  FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE,
  FOREIGN KEY (etiqueta_id) REFERENCES etiquetas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comentarios por documento.
CREATE TABLE IF NOT EXISTS comentarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  archivo_id INT NOT NULL,
  usuario_id INT NULL,
  autor_nombre VARCHAR(100) NOT NULL,
  cuerpo TEXT NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_comentarios_archivo (archivo_id, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Colaboradores granulares por documento.
CREATE TABLE IF NOT EXISTS archivo_colaborador (
  archivo_id INT NOT NULL,
  usuario_id INT NOT NULL,
  rol ENUM('lectura','edicion') NOT NULL DEFAULT 'edicion',
  PRIMARY KEY (archivo_id, usuario_id),
  FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enlaces cortos de compartir (lectura o edición) via s.php?t=token.
CREATE TABLE IF NOT EXISTS enlaces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  archivo_id INT NOT NULL,
  token VARCHAR(16) NOT NULL,
  nivel ENUM('lectura','edicion') NOT NULL DEFAULT 'lectura',
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_enlace_token (token),
  FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE,
  INDEX idx_enlaces_archivo (archivo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  Migración para bases ya desplegadas (ejecutar una sola vez)
-- ============================================================
--
-- -- Cuentas de usuario (si aún no existen):
-- CREATE TABLE IF NOT EXISTS usuarios (
--   id INT AUTO_INCREMENT PRIMARY KEY,
--   username VARCHAR(50) NOT NULL,
--   password_hash VARCHAR(255) NOT NULL,
--   creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   UNIQUE KEY uniq_username (username)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- -- Columnas de cuentas/privacidad en archivos (si aún no existen):
-- ALTER TABLE archivos
--   ADD COLUMN usuario_id INT NULL AFTER nombre,
--   ADD COLUMN es_privado TINYINT(1) NOT NULL DEFAULT 0 AFTER usuario_id,
--   ADD COLUMN password_hash VARCHAR(255) NULL AFTER es_privado,
--   ADD COLUMN codigo_edicion_hash VARCHAR(255) NULL AFTER password_hash,
--   ADD CONSTRAINT fk_archivos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
--   ADD INDEX idx_archivos_usuario (usuario_id);
--
-- -- Soft-delete + índices + full-text (Fases 2 y 3):
-- ALTER TABLE archivos
--   ADD COLUMN borrado_en DATETIME NULL,
--   ADD INDEX idx_archivos_borrado (borrado_en),
--   ADD FULLTEXT INDEX ft_archivos_nombre (nombre);
--
-- ALTER TABLE versiones
--   ADD FULLTEXT INDEX ft_versiones_contenido (contenido);
--
-- -- Rate-limit, etiquetas, comentarios y colaboradores (crear tablas nuevas):
-- CREATE TABLE IF NOT EXISTS intentos_acceso (
--   id INT AUTO_INCREMENT PRIMARY KEY,
--   clave VARCHAR(191) NOT NULL,
--   creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   INDEX idx_intentos_clave (clave, creado_en)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- CREATE TABLE IF NOT EXISTS etiquetas (
--   id INT AUTO_INCREMENT PRIMARY KEY,
--   nombre VARCHAR(60) NOT NULL,
--   UNIQUE KEY uniq_etiqueta_nombre (nombre)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- CREATE TABLE IF NOT EXISTS archivo_etiqueta (
--   archivo_id INT NOT NULL,
--   etiqueta_id INT NOT NULL,
--   PRIMARY KEY (archivo_id, etiqueta_id),
--   FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE,
--   FOREIGN KEY (etiqueta_id) REFERENCES etiquetas(id) ON DELETE CASCADE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- CREATE TABLE IF NOT EXISTS comentarios (
--   id INT AUTO_INCREMENT PRIMARY KEY,
--   archivo_id INT NOT NULL,
--   usuario_id INT NULL,
--   autor_nombre VARCHAR(100) NOT NULL,
--   cuerpo TEXT NOT NULL,
--   creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE,
--   FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
--   INDEX idx_comentarios_archivo (archivo_id, creado_en)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- CREATE TABLE IF NOT EXISTS archivo_colaborador (
--   archivo_id INT NOT NULL,
--   usuario_id INT NOT NULL,
--   rol ENUM('lectura','edicion') NOT NULL DEFAULT 'edicion',
--   PRIMARY KEY (archivo_id, usuario_id),
--   FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE,
--   FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- -- Enlaces cortos de compartir:
-- CREATE TABLE IF NOT EXISTS enlaces (
--   id INT AUTO_INCREMENT PRIMARY KEY,
--   archivo_id INT NOT NULL,
--   token VARCHAR(16) NOT NULL,
--   nivel ENUM('lectura','edicion') NOT NULL DEFAULT 'lectura',
--   creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   UNIQUE KEY uniq_enlace_token (token),
--   FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE,
--   INDEX idx_enlaces_archivo (archivo_id)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
