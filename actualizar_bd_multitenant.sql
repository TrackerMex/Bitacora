-- Migracion multi-tenant para Bitacora + Planificador de Flotas
-- Objetivo:
--   1. Crear base comun por cliente_id.
--   2. Permitir que bitacora_tracker registre unidades/despachos.
--   3. Permitir que bitacora_ consulte solo los datos del cliente del usuario.
--   4. Mantener compatibilidad temporal con tablas actuales.
--
-- Esta migracion es idempotente para MariaDB/MySQL usando helpers sobre
-- information_schema. No elimina datos ni cambia columnas existentes.

SET @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

DELIMITER $$

DROP PROCEDURE IF EXISTS add_column_if_not_exists $$
CREATE PROCEDURE add_column_if_not_exists(
  IN p_table_name VARCHAR(64),
  IN p_column_name VARCHAR(64),
  IN p_column_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND COLUMN_NAME = p_column_name
  ) THEN
    SET @sql = CONCAT(
      'ALTER TABLE `', p_table_name, '` ADD COLUMN `',
      p_column_name, '` ', p_column_definition
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS add_index_if_not_exists $$
CREATE PROCEDURE add_index_if_not_exists(
  IN p_table_name VARCHAR(64),
  IN p_index_name VARCHAR(64),
  IN p_index_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND INDEX_NAME = p_index_name
  ) THEN
    SET @sql = CONCAT(
      'ALTER TABLE `', p_table_name, '` ADD ',
      p_index_definition
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS add_fk_if_not_exists $$
CREATE PROCEDURE add_fk_if_not_exists(
  IN p_table_name VARCHAR(64),
  IN p_fk_name VARCHAR(64),
  IN p_fk_definition TEXT
)
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
  ) AND NOT EXISTS (
    SELECT 1
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = p_table_name
      AND CONSTRAINT_NAME = p_fk_name
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
  ) THEN
    SET @sql = CONCAT(
      'ALTER TABLE `', p_table_name, '` ADD CONSTRAINT `',
      p_fk_name, '` ', p_fk_definition
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END $$

DELIMITER ;

CREATE TABLE IF NOT EXISTS clientes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(150) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_clientes_slug (slug),
  KEY idx_clientes_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nombre VARCHAR(150) NOT NULL DEFAULT '',
  role VARCHAR(40) NOT NULL DEFAULT 'lector',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_email (email),
  KEY idx_usuarios_role (role),
  KEY idx_usuarios_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_clientes (
  usuario_id INT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, cliente_id),
  KEY idx_usuario_clientes_cliente (cliente_id),
  CONSTRAINT fk_usuario_clientes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_usuario_clientes_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuario_tabs (
  usuario_id INT UNSIGNED NOT NULL,
  tab_index INT NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (usuario_id, tab_index),
  CONSTRAINT fk_usuario_tabs_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unidades (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NOT NULL,
  economico VARCHAR(100) NOT NULL,
  placas VARCHAR(100) NOT NULL DEFAULT '',
  operador VARCHAR(150) NOT NULL DEFAULT '',
  telefonos TEXT NULL,
  equipos TEXT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_unidades_cliente_economico (cliente_id, economico),
  KEY idx_unidades_cliente (cliente_id),
  KEY idx_unidades_activo (activo),
  CONSTRAINT fk_unidades_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS despachos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NOT NULL,
  unidad_id INT UNSIGNED NOT NULL,
  folio VARCHAR(100) NOT NULL DEFAULT '',
  fecha_programada DATE NOT NULL,
  tramo_numero INT NOT NULL DEFAULT 1,
  ruta VARCHAR(150) NOT NULL DEFAULT '',
  origen TEXT NULL,
  lugar_carga TEXT NULL,
  destino TEXT NULL,
  instrucciones TEXT NULL,
  salida_patio_programada DATETIME NULL,
  cita_carga DATETIME NULL,
  salida_carga_programada DATETIME NULL,
  descarga_programada DATETIME NULL,
  source_system VARCHAR(40) NOT NULL DEFAULT 'planificador',
  legacy_key VARCHAR(190) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_despachos_tramo (cliente_id, folio, unidad_id, fecha_programada, tramo_numero),
  KEY idx_despachos_cliente_fecha (cliente_id, fecha_programada),
  KEY idx_despachos_unidad_fecha (unidad_id, fecha_programada),
  KEY idx_despachos_created_by (created_by),
  KEY idx_despachos_legacy_key (legacy_key),
  CONSTRAINT fk_despachos_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_despachos_unidad
    FOREIGN KEY (unidad_id) REFERENCES unidades(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_despachos_created_by
    FOREIGN KEY (created_by) REFERENCES usuarios(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS despacho_lectores (
  despacho_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (despacho_id, usuario_id),
  KEY idx_despacho_lectores_usuario (usuario_id),
  CONSTRAINT fk_despacho_lectores_despacho
    FOREIGN KEY (despacho_id) REFERENCES despachos(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_despacho_lectores_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS migration_map (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_system VARCHAR(40) NOT NULL,
  source_cliente VARCHAR(150) NOT NULL DEFAULT '',
  source_entity VARCHAR(40) NOT NULL,
  source_key VARCHAR(190) NOT NULL,
  target_table VARCHAR(40) NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  migrated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_migration_map_source (source_system, source_entity, source_key, target_table),
  KEY idx_migration_map_target (target_table, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tablas operativas actuales. En bases nuevas se crean listas para
-- multi-tenant; en bases existentes no se modifican aqui.
CREATE TABLE IF NOT EXISTS seguimiento_despacho (
  id INT NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NULL,
  despacho_id INT UNSIGNED NULL,
  folio VARCHAR(50) NULL,
  unidad VARCHAR(50) NULL,
  fecha_programada DATE NULL,
  operador_monitoreo VARCHAR(50) NULL,
  gps_estado VARCHAR(50) NULL,
  gps_timestamp DATETIME NULL,
  real_salida_unidad DATETIME NULL,
  real_carga DATETIME NULL,
  real_salida DATETIME NULL,
  real_descarga DATETIME NULL,
  cita_salida_unidad DATETIME NULL,
  cita_carga DATETIME NULL,
  cita_salida DATETIME NULL,
  cita_descarga DATETIME NULL,
  confirmacion_entrega VARCHAR(5) NULL,
  estatus VARCHAR(50) NULL,
  estatus_especial VARCHAR(50) NULL,
  observaciones TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_despacho (folio, unidad, fecha_programada),
  UNIQUE KEY uk_seguimiento_despacho_id (despacho_id),
  KEY idx_estatus_especial (estatus_especial),
  KEY idx_seguimiento_cliente (cliente_id),
  KEY idx_seguimiento_despacho_id (despacho_id),
  CONSTRAINT fk_seguimiento_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_seguimiento_despacho
    FOREIGN KEY (despacho_id) REFERENCES despachos(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seguimiento_incidencias (
  id INT NOT NULL AUTO_INCREMENT,
  seguimiento_id INT NULL,
  tipo VARCHAR(255) NULL,
  severidad VARCHAR(20) NULL,
  fecha DATETIME NULL,
  direccion TEXT NULL,
  PRIMARY KEY (id),
  KEY idx_seguimiento_incidencias_seguimiento (seguimiento_id),
  CONSTRAINT fk_seguimiento_incidencias_seguimiento
    FOREIGN KEY (seguimiento_id) REFERENCES seguimiento_despacho(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS despacho_origen_destino (
  id INT NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NULL,
  despacho_id INT UNSIGNED NULL,
  folio VARCHAR(100) NOT NULL,
  unidad VARCHAR(100) NOT NULL,
  fecha_programada DATE NOT NULL,
  origen TEXT NOT NULL DEFAULT '',
  destino TEXT NOT NULL DEFAULT '',
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_folio_unidad_fecha (folio, unidad, fecha_programada),
  UNIQUE KEY uk_origen_destino_despacho_id (despacho_id),
  KEY idx_origen_destino_cliente (cliente_id),
  KEY idx_origen_destino_despacho_id (despacho_id),
  CONSTRAINT fk_origen_destino_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_origen_destino_despacho
    FOREIGN KEY (despacho_id) REFERENCES despachos(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS informes_guardados (
  id INT NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NULL,
  titulo VARCHAR(255) NOT NULL,
  fecha_creacion DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_despacho DATE NULL,
  total_despachos INT NULL,
  a_tiempo INT NULL,
  con_retraso INT NULL,
  en_ruta INT NULL,
  programados INT NULL,
  total_incidencias INT NULL,
  datos_informe LONGTEXT NOT NULL,
  operador_monitoreo VARCHAR(50) NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_informes_cliente (cliente_id),
  KEY idx_informes_cliente_fecha (cliente_id, fecha_despacho),
  CONSTRAINT fk_informes_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contactos (
  id INT NOT NULL AUTO_INCREMENT,
  cliente_id INT UNSIGNED NULL,
  nombre VARCHAR(150) NOT NULL,
  cargo VARCHAR(150) NULL,
  departamento VARCHAR(150) NULL,
  PRIMARY KEY (id),
  KEY idx_contactos_cliente (cliente_id),
  CONSTRAINT fk_contactos_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacto_telefonos (
  id INT NOT NULL AUTO_INCREMENT,
  contacto_id INT NOT NULL,
  telefono VARCHAR(20) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_contacto_telefonos_contacto (contacto_id),
  CONSTRAINT fk_contacto_telefonos_contacto
    FOREIGN KEY (contacto_id) REFERENCES contactos(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacto_correos (
  id INT NOT NULL AUTO_INCREMENT,
  contacto_id INT NOT NULL,
  correo VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_contacto_correos_contacto (contacto_id),
  CONSTRAINT fk_contacto_correos_contacto
    FOREIGN KEY (contacto_id) REFERENCES contactos(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS numeros_emergencia_estado (
  id INT NOT NULL AUTO_INCREMENT,
  estado VARCHAR(255) NULL,
  municipio VARCHAR(255) NULL,
  numero BIGINT NULL,
  PRIMARY KEY (id),
  KEY idx_numeros_emergencia_estado_municipio (estado, municipio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compatibilidad temporal: enlazar tablas existentes con el nuevo modelo.
CALL add_column_if_not_exists('seguimiento_despacho', 'cliente_id', 'INT UNSIGNED NULL AFTER id');
CALL add_column_if_not_exists('seguimiento_despacho', 'despacho_id', 'INT UNSIGNED NULL AFTER cliente_id');
CALL add_index_if_not_exists('seguimiento_despacho', 'idx_seguimiento_cliente', 'INDEX idx_seguimiento_cliente (cliente_id)');
CALL add_index_if_not_exists('seguimiento_despacho', 'idx_seguimiento_despacho_id', 'INDEX idx_seguimiento_despacho_id (despacho_id)');
CALL add_index_if_not_exists('seguimiento_despacho', 'uk_seguimiento_despacho_id', 'UNIQUE KEY uk_seguimiento_despacho_id (despacho_id)');
CALL add_fk_if_not_exists(
  'seguimiento_despacho',
  'fk_seguimiento_cliente',
  'FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT ON UPDATE CASCADE'
);
CALL add_fk_if_not_exists(
  'seguimiento_despacho',
  'fk_seguimiento_despacho',
  'FOREIGN KEY (despacho_id) REFERENCES despachos(id) ON DELETE SET NULL ON UPDATE CASCADE'
);

CALL add_column_if_not_exists('despacho_origen_destino', 'cliente_id', 'INT UNSIGNED NULL AFTER id');
CALL add_column_if_not_exists('despacho_origen_destino', 'despacho_id', 'INT UNSIGNED NULL AFTER cliente_id');
CALL add_index_if_not_exists('despacho_origen_destino', 'idx_origen_destino_cliente', 'INDEX idx_origen_destino_cliente (cliente_id)');
CALL add_index_if_not_exists('despacho_origen_destino', 'idx_origen_destino_despacho_id', 'INDEX idx_origen_destino_despacho_id (despacho_id)');
CALL add_index_if_not_exists('despacho_origen_destino', 'uk_origen_destino_despacho_id', 'UNIQUE KEY uk_origen_destino_despacho_id (despacho_id)');
CALL add_fk_if_not_exists(
  'despacho_origen_destino',
  'fk_origen_destino_cliente',
  'FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT ON UPDATE CASCADE'
);
CALL add_fk_if_not_exists(
  'despacho_origen_destino',
  'fk_origen_destino_despacho',
  'FOREIGN KEY (despacho_id) REFERENCES despachos(id) ON DELETE SET NULL ON UPDATE CASCADE'
);

CALL add_column_if_not_exists('informes_guardados', 'cliente_id', 'INT UNSIGNED NULL AFTER id');
CALL add_index_if_not_exists('informes_guardados', 'idx_informes_cliente', 'INDEX idx_informes_cliente (cliente_id)');
CALL add_index_if_not_exists('informes_guardados', 'idx_informes_cliente_fecha', 'INDEX idx_informes_cliente_fecha (cliente_id, fecha_despacho)');
CALL add_fk_if_not_exists(
  'informes_guardados',
  'fk_informes_cliente',
  'FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT ON UPDATE CASCADE'
);

CALL add_column_if_not_exists('contactos', 'cliente_id', 'INT UNSIGNED NULL AFTER id');
CALL add_index_if_not_exists('contactos', 'idx_contactos_cliente', 'INDEX idx_contactos_cliente (cliente_id)');
CALL add_fk_if_not_exists(
  'contactos',
  'fk_contactos_cliente',
  'FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE RESTRICT ON UPDATE CASCADE'
);

DROP PROCEDURE IF EXISTS add_fk_if_not_exists;
DROP PROCEDURE IF EXISTS add_index_if_not_exists;
DROP PROCEDURE IF EXISTS add_column_if_not_exists;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;
