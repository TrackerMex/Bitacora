-- Limpieza controlada para iniciar operacion real en Bitacora multi-tenant.
-- IMPORTANTE:
-- 1) Haz backup antes de ejecutar.
-- 2) Ejecuta primero los SELECT de conteo.
-- 3) Esta limpieza conserva usuarios role='admin' y el catalogo numeros_emergencia_estado.
-- 4) Borra clientes, unidades, despachos, seguimientos, informes y usuarios no admin.

-- Conteo previo
SELECT 'clientes' AS tabla, COUNT(*) AS total FROM clientes
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios
UNION ALL SELECT 'usuario_clientes', COUNT(*) FROM usuario_clientes
UNION ALL SELECT 'usuario_tabs', COUNT(*) FROM usuario_tabs
UNION ALL SELECT 'unidades', COUNT(*) FROM unidades
UNION ALL SELECT 'despachos', COUNT(*) FROM despachos
UNION ALL SELECT 'despacho_lectores', COUNT(*) FROM despacho_lectores
UNION ALL SELECT 'seguimiento_despacho', COUNT(*) FROM seguimiento_despacho
UNION ALL SELECT 'seguimiento_incidencias', COUNT(*) FROM seguimiento_incidencias
UNION ALL SELECT 'informes_guardados', COUNT(*) FROM informes_guardados
UNION ALL SELECT 'directorio_monitoreo', COUNT(*) FROM directorio_monitoreo
UNION ALL SELECT 'migration_map', COUNT(*) FROM migration_map;

START TRANSACTION;

-- Hijas de seguimiento/despachos
DELETE FROM seguimiento_incidencias;
DELETE FROM seguimiento_despacho;
DELETE FROM despacho_lectores;

-- Informacion operativa
DELETE FROM informes_guardados;
DELETE FROM directorio_monitoreo;
DELETE FROM despachos;
DELETE FROM unidades;
DELETE FROM migration_map;

-- Usuarios/clientes de pruebas, conservando admins
DELETE FROM usuario_clientes;
DELETE ut
  FROM usuario_tabs ut
  INNER JOIN usuarios u ON u.id = ut.usuario_id
 WHERE LOWER(u.role) <> 'admin';
DELETE FROM usuarios WHERE LOWER(role) <> 'admin';
DELETE FROM clientes;

COMMIT;

-- Reset de autoincrement para tablas vacias.
-- Si alguna tabla no existe en tu BD, comenta esa linea.
ALTER TABLE clientes AUTO_INCREMENT = 1;
ALTER TABLE unidades AUTO_INCREMENT = 1;
ALTER TABLE despachos AUTO_INCREMENT = 1;
ALTER TABLE despacho_lectores AUTO_INCREMENT = 1;
ALTER TABLE seguimiento_despacho AUTO_INCREMENT = 1;
ALTER TABLE seguimiento_incidencias AUTO_INCREMENT = 1;
ALTER TABLE informes_guardados AUTO_INCREMENT = 1;
ALTER TABLE directorio_monitoreo AUTO_INCREMENT = 1;
ALTER TABLE migration_map AUTO_INCREMENT = 1;

-- Conteo posterior esperado:
-- usuarios: solo admins
-- numeros_emergencia_estado: se conserva
SELECT 'clientes' AS tabla, COUNT(*) AS total FROM clientes
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios
UNION ALL SELECT 'usuario_clientes', COUNT(*) FROM usuario_clientes
UNION ALL SELECT 'usuario_tabs', COUNT(*) FROM usuario_tabs
UNION ALL SELECT 'unidades', COUNT(*) FROM unidades
UNION ALL SELECT 'despachos', COUNT(*) FROM despachos
UNION ALL SELECT 'despacho_lectores', COUNT(*) FROM despacho_lectores
UNION ALL SELECT 'seguimiento_despacho', COUNT(*) FROM seguimiento_despacho
UNION ALL SELECT 'seguimiento_incidencias', COUNT(*) FROM seguimiento_incidencias
UNION ALL SELECT 'informes_guardados', COUNT(*) FROM informes_guardados
UNION ALL SELECT 'directorio_monitoreo', COUNT(*) FROM directorio_monitoreo
UNION ALL SELECT 'migration_map', COUNT(*) FROM migration_map
UNION ALL SELECT 'numeros_emergencia_estado', COUNT(*) FROM numeros_emergencia_estado;

