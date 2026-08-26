-- Agrega el seguimiento opcional de regreso a origen por tramo/despacho.
-- Cuando requiere_regreso_origen = 0, el flujo actual no cambia.

ALTER TABLE viaje_tramos
  ADD COLUMN IF NOT EXISTS requiere_regreso_origen TINYINT(1) NOT NULL DEFAULT 0 AFTER vacio_real,
  ADD COLUMN IF NOT EXISTS regreso_origen_programado DATETIME NULL DEFAULT NULL AFTER requiere_regreso_origen,
  ADD COLUMN IF NOT EXISTS regreso_origen_real DATETIME NULL DEFAULT NULL AFTER regreso_origen_programado;

ALTER TABLE seguimiento_despacho
  ADD COLUMN IF NOT EXISTS requiere_regreso_origen TINYINT(1) NOT NULL DEFAULT 0 AFTER real_vacio,
  ADD COLUMN IF NOT EXISTS regreso_origen_programado DATETIME NULL DEFAULT NULL AFTER requiere_regreso_origen,
  ADD COLUMN IF NOT EXISTS regreso_origen_real DATETIME NULL DEFAULT NULL AFTER regreso_origen_programado;
