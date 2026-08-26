-- Agrega el evento real final del flujo de descarga en seguimiento legacy.
-- real_descarga = Arribo a Descarga, real_vacio = Vacío.

ALTER TABLE seguimiento_despacho
  ADD COLUMN IF NOT EXISTS real_vacio DATETIME NULL DEFAULT NULL AFTER real_descarga;
