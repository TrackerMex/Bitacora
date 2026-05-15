# Migración histórica Google Sheets -> MySQL

Este proceso migra la hoja histórica de despachos hacia la base multi-tenant.

## Qué migra

- Clientes desde la columna `cliente`.
- Unidades por `cliente + unidad`.
- Despachos por `cliente + folio + unidad + fecha + tramo`.
- Seguimiento histórico solo si la hoja trae horas reales de salida/carga/descarga.
- Mapeo idempotente en `migration_map`.

## Dry run

No escribe en la base. Solo calcula conteos y filas omitidas.

```bash
php -r "$_SERVER['REQUEST_METHOD']='GET'; $_GET['dry_run']='1'; include 'src/migracion/migrar_historico.php';"
```

También puede abrirse en navegador:

```text
http://localhost/bitacora_/src/migracion/migrar_historico.php?dry_run=1
```

## Ejecución real

Antes de ejecutar, definir `MIGRATION_TOKEN` en `.env`.

```text
MIGRATION_TOKEN=un_token_largo_y_privado
```

Después ejecutar:

```text
http://localhost/bitacora_/src/migracion/migrar_historico.php?execute=1&token=un_token_largo_y_privado
```

El script usa `ON DUPLICATE KEY UPDATE`, `legacy_key` y `migration_map`, por lo que se puede repetir sin duplicar despachos.

## Resultado del dry run inicial

- Filas leídas: 188
- Filas válidas: 174
- Filas omitidas: 13
- Clientes detectados: 5
- Unidades detectadas: 19
- Despachos a migrar: 174

Las filas omitidas no tienen alguno de estos campos requeridos: `cliente`, `folio`, `unidad` o `fecha`.

## Resultado de ejecución

- Migración aplicada: 174 despachos históricos.
- Mapeos creados: 198 registros en `migration_map`.
- Seguimientos históricos creados: 0, porque la hoja no trajo horas reales suficientes para crear seguimiento.

Si una corrida debe repetirse desde cero, existe una limpieza controlada para datos generados por `google_sheets`:

```bash
php src/migracion/limpiar_historico_google_sheets.php limpiar_google_sheets
```

Para limpiar clientes huérfanos sin referencias:

```bash
php src/migracion/limpiar_clientes_orfanos.php limpiar_clientes_orfanos
```
