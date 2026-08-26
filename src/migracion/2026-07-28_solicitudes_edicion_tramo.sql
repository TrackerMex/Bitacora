CREATE TABLE IF NOT EXISTS solicitudes_edicion_tramo (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  despacho_id INT(10) UNSIGNED NOT NULL,
  cliente_id INT(10) UNSIGNED NOT NULL,
  solicitado_por_usuario_id INT(10) UNSIGNED NULL DEFAULT NULL,
  estado ENUM('pendiente', 'en_revision', 'aplicada', 'rechazada', 'cancelada')
    NOT NULL DEFAULT 'pendiente',
  motivo VARCHAR(500) NOT NULL,
  campos_solicitados LONGTEXT NOT NULL,
  valores_actuales LONGTEXT NOT NULL,
  valores_aplicados LONGTEXT NULL DEFAULT NULL,
  comentario_admin VARCHAR(500) NULL DEFAULT NULL,
  revisado_por_usuario_id INT(10) UNSIGNED NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL DEFAULT NULL,
  applied_at DATETIME NULL DEFAULT NULL,
  cancelled_at DATETIME NULL DEFAULT NULL,

  PRIMARY KEY (id),
  INDEX idx_solicitud_edicion_estado_fecha (estado, created_at),
  INDEX idx_solicitud_edicion_cliente (cliente_id),
  INDEX idx_solicitud_edicion_despacho (despacho_id),
  INDEX idx_solicitud_edicion_usuario (solicitado_por_usuario_id),

  CONSTRAINT fk_solicitud_edicion_despacho
    FOREIGN KEY (despacho_id)
    REFERENCES despachos (id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT fk_solicitud_edicion_cliente
    FOREIGN KEY (cliente_id)
    REFERENCES clientes (id)
    ON DELETE CASCADE ON UPDATE CASCADE,

  CONSTRAINT fk_solicitud_edicion_solicitante
    FOREIGN KEY (solicitado_por_usuario_id)
    REFERENCES usuarios (id)
    ON DELETE SET NULL ON UPDATE CASCADE,

  CONSTRAINT fk_solicitud_edicion_revisor
    FOREIGN KEY (revisado_por_usuario_id)
    REFERENCES usuarios (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
