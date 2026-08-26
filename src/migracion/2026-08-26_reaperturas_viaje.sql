CREATE TABLE IF NOT EXISTS viaje_reaperturas (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  viaje_id INT(10) UNSIGNED NOT NULL,
  usuario_id INT(10) UNSIGNED NULL DEFAULT NULL,
  estado_previo VARCHAR(30) NOT NULL,
  motivo VARCHAR(500) NOT NULL,
  tramo_ids LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_viaje_reaperturas_viaje (viaje_id),
  KEY idx_viaje_reaperturas_usuario (usuario_id),
  KEY idx_viaje_reaperturas_created (created_at),
  CONSTRAINT fk_viaje_reaperturas_viaje
    FOREIGN KEY (viaje_id) REFERENCES viajes(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_viaje_reaperturas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
