-- Migracion: modulo de rutas fijas y rutinarias
-- Proyecto: bitacora_

CREATE TABLE IF NOT EXISTS rutas_fijas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo_ruta VARCHAR(50) NOT NULL,
  pais_origen VARCHAR(100) NOT NULL,
  estado_origen VARCHAR(100) NOT NULL,
  ciudad_origen VARCHAR(100) NOT NULL,
  ciudad_destino VARCHAR(100) NOT NULL,
  nombre_ruta VARCHAR(255) NOT NULL,
  descripcion TEXT NULL,
  transportista_id INT NULL,
  estado ENUM('activa', 'inactiva', 'pausada') NOT NULL DEFAULT 'activa',
  distancia_total_km DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tiempo_total_minutos INT NOT NULL DEFAULT 0,
  numero_paradas INT NOT NULL DEFAULT 0,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_codigo_ruta (codigo_ruta),
  KEY idx_pais_estado (pais_origen, estado_origen),
  KEY idx_ciudad_destino (ciudad_destino),
  KEY idx_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS secuencias_ruta (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ruta_id INT UNSIGNED NOT NULL,
  numero_secuencia INT NOT NULL,
  origen_municipio VARCHAR(255) NOT NULL,
  destino_municipio VARCHAR(255) NOT NULL,
  distancia_km DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tiempo_estimado_minutos INT NOT NULL DEFAULT 0,
  notas TEXT NULL,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ruta_secuencia (ruta_id, numero_secuencia),
  KEY idx_ruta_id (ruta_id),
  CONSTRAINT fk_secuencias_ruta_ruta
    FOREIGN KEY (ruta_id) REFERENCES rutas_fijas(id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
