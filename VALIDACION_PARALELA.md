# Validación paralela Sheets vs MySQL

La validación compara la hoja histórica de Google Sheets contra los despachos migrados en MySQL.

## Ejecución

```bash
php -r "$_SERVER['REQUEST_METHOD']='GET'; include 'src/migracion/validar_paralelo.php';"
```

También puede abrirse en navegador:

```text
http://localhost/bitacora_/src/migracion/validar_paralelo.php
```

## Qué valida

- Reconstruye las llaves históricas (`legacy_key`) desde Google Sheets.
- Cruza esas llaves contra `despachos.legacy_key`.
- Reporta despachos faltantes en MySQL.
- Reporta despachos extra en MySQL.
- Reporta diferencias en campos clave: `cliente`, `folio`, `unidad`, `fecha`, `tramo_numero`.
- Agrupa conteos por cliente.

## Resultado actual

- Filas leídas de Sheets: 188
- Filas válidas: 174
- Filas omitidas: 13
- Despachos históricos en MySQL: 174
- Faltantes en MySQL: 0
- Extras en MySQL: 0
- Diferencias en campos clave: 0

Distribución validada:

- ALCASALI: 1
- KAVAK: 2
- TEST: 1
- TRACKER ALEXIS: 2
- Tracker: 168

Las filas omitidas no tienen alguno de estos campos requeridos: `cliente`, `folio`, `unidad` o `fecha`.
