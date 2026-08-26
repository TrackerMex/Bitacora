-- Consolida la unidad duplicada de CORP LOGISTICO.
-- Canonica: 23 / OJ 011 / LG78603
-- Duplicada: 73 / 0J 011 / LG78603
--
-- La migracion conserva un respaldo de las referencias originales y no elimina
-- la unidad duplicada; solo la desactiva cuando ya no tiene relaciones.

CREATE TABLE IF NOT EXISTS migracion_backup_corp_oj011_20260826 (
  tabla VARCHAR(32) NOT NULL,
  registro_id INT UNSIGNED NOT NULL,
  unidad_id_original INT UNSIGNED NOT NULL,
  respaldado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tabla, registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO migracion_backup_corp_oj011_20260826 (
  tabla,
  registro_id,
  unidad_id_original
)
SELECT 'viajes', v.id, v.unidad_id
FROM viajes v
INNER JOIN unidades u ON u.id = v.unidad_id
WHERE u.id = 73
  AND u.cliente_id = 2
  AND u.economico = '0J 011'
  AND u.placas = 'LG78603';

INSERT IGNORE INTO migracion_backup_corp_oj011_20260826 (
  tabla,
  registro_id,
  unidad_id_original
)
SELECT 'despachos', d.id, d.unidad_id
FROM despachos d
INNER JOIN unidades u ON u.id = d.unidad_id
WHERE u.id = 73
  AND u.cliente_id = 2
  AND u.economico = '0J 011'
  AND u.placas = 'LG78603';

START TRANSACTION;

UPDATE viajes v
INNER JOIN unidades duplicada
  ON duplicada.id = v.unidad_id
 AND duplicada.id = 73
 AND duplicada.cliente_id = 2
 AND duplicada.economico = '0J 011'
 AND duplicada.placas = 'LG78603'
INNER JOIN unidades canonica
  ON canonica.id = 23
 AND canonica.cliente_id = duplicada.cliente_id
 AND canonica.economico = 'OJ 011'
 AND canonica.placas = duplicada.placas
SET v.unidad_id = canonica.id;

UPDATE despachos d
INNER JOIN unidades duplicada
  ON duplicada.id = d.unidad_id
 AND duplicada.id = 73
 AND duplicada.cliente_id = 2
 AND duplicada.economico = '0J 011'
 AND duplicada.placas = 'LG78603'
INNER JOIN unidades canonica
  ON canonica.id = 23
 AND canonica.cliente_id = duplicada.cliente_id
 AND canonica.economico = 'OJ 011'
 AND canonica.placas = duplicada.placas
SET d.unidad_id = canonica.id;

UPDATE unidades duplicada
INNER JOIN unidades canonica
  ON canonica.id = 23
 AND canonica.cliente_id = duplicada.cliente_id
 AND canonica.economico = 'OJ 011'
 AND canonica.placas = duplicada.placas
SET duplicada.activo = 0
WHERE duplicada.id = 73
  AND duplicada.cliente_id = 2
  AND duplicada.economico = '0J 011'
  AND duplicada.placas = 'LG78603'
  AND NOT EXISTS (
    SELECT 1 FROM viajes v WHERE v.unidad_id = duplicada.id
  )
  AND NOT EXISTS (
    SELECT 1 FROM despachos d WHERE d.unidad_id = duplicada.id
  );

COMMIT;

-- Rollback manual, solo si fuera necesario:
-- START TRANSACTION;
-- UPDATE viajes v
-- JOIN migracion_backup_corp_oj011_20260826 b
--   ON b.tabla = 'viajes' AND b.registro_id = v.id
-- SET v.unidad_id = b.unidad_id_original;
-- UPDATE despachos d
-- JOIN migracion_backup_corp_oj011_20260826 b
--   ON b.tabla = 'despachos' AND b.registro_id = d.id
-- SET d.unidad_id = b.unidad_id_original;
-- UPDATE unidades SET activo = 1 WHERE id = 73;
-- COMMIT;
