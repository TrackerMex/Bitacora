-- Migracion para gestion de tramos en el planificador de flotas.
-- Permite ocultar tramos sin borrar historico ni seguimientos relacionados.

ALTER TABLE despachos
  ADD COLUMN IF NOT EXISTS eliminado_at DATETIME NULL AFTER instrucciones,
  ADD COLUMN IF NOT EXISTS eliminado_por_usuario_id INT NULL AFTER eliminado_at,
  ADD COLUMN IF NOT EXISTS eliminado_motivo VARCHAR(255) NULL AFTER eliminado_por_usuario_id;

CREATE INDEX IF NOT EXISTS idx_despachos_eliminado_at
  ON despachos (eliminado_at);
