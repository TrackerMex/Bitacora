-- ============================================================================
-- Migración de PRODUCCIÓN — Deploy features viajes/papelera/plantillas
-- BD: u558294948_main  (MariaDB 11.8.6)
-- Fecha: 2026-06-02
--
-- Qué hace (todo idempotente, seguro de re-ejecutar):
--   1. Agrega columnas de soft-delete (papelera) a `viajes` y `viaje_tramos`
--   2. Crea la tabla `tramos_catalogo` (plantillas de ruta) si no existe
--
-- Cómo correr: pegar TODO este archivo en phpMyAdmin (pestaña SQL) de la BD
-- u558294948_main y ejecutar. O por CLI:
--   mysql -h srv1145.hstgr.io -u u558294948_clientes -p u558294948_main < este_archivo.sql
--
-- RECOMENDADO: hacer backup antes:
--   mysqldump -h srv1145.hstgr.io -u u558294948_clientes -p u558294948_main \
--     > backup_pre_deploy_2026-06-02.sql
-- ============================================================================

-- ── 1. Papelera / soft-delete en viajes ────────────────────────────────────
ALTER TABLE viajes
  ADD COLUMN IF NOT EXISTS eliminado_at             DATETIME      NULL,
  ADD COLUMN IF NOT EXISTS eliminado_por_usuario_id INT UNSIGNED  NULL,
  ADD COLUMN IF NOT EXISTS eliminado_motivo         VARCHAR(255)  NULL;

-- ── 2. Papelera / soft-delete en viaje_tramos ──────────────────────────────
ALTER TABLE viaje_tramos
  ADD COLUMN IF NOT EXISTS eliminado_at             DATETIME      NULL,
  ADD COLUMN IF NOT EXISTS eliminado_por_usuario_id INT UNSIGNED  NULL,
  ADD COLUMN IF NOT EXISTS eliminado_motivo         VARCHAR(255)  NULL;

-- ── 3. Catálogo de plantillas de ruta (sin datos de ejemplo) ───────────────
CREATE TABLE IF NOT EXISTS tramos_catalogo (
    id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id INT(10) UNSIGNED NOT NULL COMMENT 'Cliente propietario (multi-tenant)',
    etiqueta VARCHAR(100) NOT NULL COMMENT 'Identificador visual de la plantilla',
    ruta VARCHAR(100) DEFAULT NULL,
    origen VARCHAR(255) DEFAULT NULL,
    lugar_carga VARCHAR(255) DEFAULT NULL,
    destino VARCHAR(255) DEFAULT NULL,
    instrucciones TEXT DEFAULT NULL,
    duracion_estimada_horas DECIMAL(5,2) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Soft delete: 1=activo, 0=eliminado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_etiqueta_cliente (cliente_id, etiqueta),
    INDEX idx_cliente_activo (cliente_id, activo),
    INDEX idx_etiqueta (etiqueta),
    CONSTRAINT fk_tramos_catalogo_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo de plantillas de ruta reutilizables por cliente';

-- ============================================================================
-- Verificación (opcional — ejecutar después para confirmar)
-- ============================================================================
-- SHOW COLUMNS FROM viajes        LIKE 'eliminado%';
-- SHOW COLUMNS FROM viaje_tramos  LIKE 'eliminado%';
-- SHOW TABLES LIKE 'tramos_catalogo';
