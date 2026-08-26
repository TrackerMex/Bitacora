CREATE TABLE IF NOT EXISTS viaje_incidencias (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  viaje_id INT(10) UNSIGNED NOT NULL,
  tramo_id INT(10) UNSIGNED NOT NULL,
  tipo VARCHAR(120) NOT NULL,
  severidad ENUM('alta', 'media', 'baja') NOT NULL DEFAULT 'media',
  fecha DATETIME NOT NULL,
  direccion VARCHAR(255) NULL DEFAULT NULL,
  notas TEXT NULL DEFAULT NULL,
  creado_por INT(10) UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  INDEX idx_viaje_incidencias_viaje (viaje_id),
  INDEX idx_viaje_incidencias_tramo (tramo_id),
  INDEX idx_viaje_incidencias_fecha (fecha),

  CONSTRAINT fk_viaje_incidencias_viaje
    FOREIGN KEY (viaje_id)
    REFERENCES viajes (id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT fk_viaje_incidencias_tramo
    FOREIGN KEY (tramo_id)
    REFERENCES viaje_tramos (id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT fk_viaje_incidencias_creado_por
    FOREIGN KEY (creado_por)
    REFERENCES usuarios (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
