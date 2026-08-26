-- Agrega el lugar de carga a los overrides manuales de origen/destino.
-- Permite editar y persistir la ruta completa: origen, carga y destino.

ALTER TABLE despacho_origen_destino
  ADD COLUMN IF NOT EXISTS lugar_carga VARCHAR(255) DEFAULT NULL AFTER origen;
