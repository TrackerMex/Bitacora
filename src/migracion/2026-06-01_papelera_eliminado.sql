ALTER TABLE viajes
  ADD COLUMN eliminado_at             DATETIME      NULL,
  ADD COLUMN eliminado_por_usuario_id INT UNSIGNED  NULL,
  ADD COLUMN eliminado_motivo         VARCHAR(255)  NULL;

ALTER TABLE viaje_tramos
  ADD COLUMN eliminado_at             DATETIME      NULL,
  ADD COLUMN eliminado_por_usuario_id INT UNSIGNED  NULL,
  ADD COLUMN eliminado_motivo         VARCHAR(255)  NULL;
