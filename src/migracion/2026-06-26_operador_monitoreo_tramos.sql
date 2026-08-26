ALTER TABLE viaje_tramos
  ADD COLUMN operador_monitoreo VARCHAR(20) NULL DEFAULT NULL
  AFTER instrucciones;
