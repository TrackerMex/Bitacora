-- Importacion admin de programacion enviada por cliente.
-- Crea plantillas de mapeo, historial de lotes y errores por fila.

CREATE TABLE IF NOT EXISTS import_plantillas_excel (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id INT(10) UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL DEFAULT 'Plantilla principal',
  mapeo_json JSON NOT NULL,
  encabezados_json JSON NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_by_usuario_id INT(10) UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY unique_import_plantilla_cliente_nombre (cliente_id, nombre),
  KEY idx_import_plantillas_cliente_activo (cliente_id, activo),
  CONSTRAINT fk_import_plantillas_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_lotes (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id INT(10) UNSIGNED NOT NULL,
  plantilla_id INT(10) UNSIGNED NULL,
  usuario_id INT(10) UNSIGNED NULL,
  archivo_nombre VARCHAR(255) NULL,
  hoja_nombre VARCHAR(120) NULL,
  total_filas INT(10) UNSIGNED NOT NULL DEFAULT 0,
  filas_validas INT(10) UNSIGNED NOT NULL DEFAULT 0,
  filas_error INT(10) UNSIGNED NOT NULL DEFAULT 0,
  filas_duplicadas INT(10) UNSIGNED NOT NULL DEFAULT 0,
  viajes_creados INT(10) UNSIGNED NOT NULL DEFAULT 0,
  tramos_creados INT(10) UNSIGNED NOT NULL DEFAULT 0,
  estado VARCHAR(30) NOT NULL DEFAULT 'preview',
  resumen_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_import_lotes_cliente_fecha (cliente_id, created_at),
  KEY idx_import_lotes_usuario (usuario_id),
  CONSTRAINT fk_import_lotes_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_lote_errores (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  lote_id INT(10) UNSIGNED NOT NULL,
  fila INT(10) UNSIGNED NOT NULL DEFAULT 0,
  campo VARCHAR(80) NULL,
  mensaje VARCHAR(255) NOT NULL,
  valor_original TEXT NULL,
  severidad VARCHAR(20) NOT NULL DEFAULT 'error',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_import_lote_errores_lote (lote_id),
  CONSTRAINT fk_import_lote_errores_lote
    FOREIGN KEY (lote_id) REFERENCES import_lotes(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tramos_catalogo
  ADD COLUMN IF NOT EXISTS firma_ruta VARCHAR(700) NULL AFTER destino,
  ADD COLUMN IF NOT EXISTS veces_usada INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER duracion_estimada_horas,
  ADD COLUMN IF NOT EXISTS es_favorito TINYINT(1) NOT NULL DEFAULT 0 AFTER veces_usada,
  ADD UNIQUE KEY IF NOT EXISTS unique_tramos_catalogo_firma (cliente_id, firma_ruta);
