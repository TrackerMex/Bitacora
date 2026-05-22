-- Agrega auditoria de usuario creador para informes guardados.
-- Ejecutar una sola vez en la BD multi-tenant de produccion.

ALTER TABLE informes_guardados
  ADD COLUMN created_by_usuario_id INT NULL AFTER cliente_id,
  ADD INDEX idx_informes_created_by_usuario_id (created_by_usuario_id),
  ADD CONSTRAINT fk_informes_created_by_usuario
    FOREIGN KEY (created_by_usuario_id) REFERENCES usuarios(id)
    ON DELETE SET NULL;