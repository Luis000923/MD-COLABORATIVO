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
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  INDEX idx_archivos_usuario (usuario_id)
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
  INDEX idx_archivo_version (archivo_id, numero_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Migración para bases ya desplegadas (ejecutar una sola vez) ----
-- CREATE TABLE IF NOT EXISTS usuarios (
--   id INT AUTO_INCREMENT PRIMARY KEY,
--   username VARCHAR(50) NOT NULL,
--   password_hash VARCHAR(255) NOT NULL,
--   creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--   UNIQUE KEY uniq_username (username)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
--
-- ALTER TABLE archivos
--   ADD COLUMN usuario_id INT NULL AFTER nombre,
--   ADD COLUMN es_privado TINYINT(1) NOT NULL DEFAULT 0 AFTER usuario_id,
--   ADD COLUMN password_hash VARCHAR(255) NULL AFTER es_privado,
--   ADD COLUMN codigo_edicion_hash VARCHAR(255) NULL AFTER password_hash,
--   ADD CONSTRAINT fk_archivos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
--   ADD INDEX idx_archivos_usuario (usuario_id);
