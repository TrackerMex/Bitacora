# Guia operativa: Rutas plantilla en Seguimiento

Esta guia es para operadores/monitoreo y explica como usar la nueva integracion de rutas fijas dentro de la pestana **Seguimiento Detallado**.

## Flujo rapido (1 minuto)

1. Inicia sesion.
2. Entra a **Seguimiento Detallado**.
3. Selecciona **Unidad** y, si aplica, **Tramo**.
4. Carga la ruta:
   - desde el selector **Cargar ruta como plantilla**, o
   - por codigo en **Buscar por codigo**.
5. Verifica el mensaje: `Ruta <CODIGO> aplicada en tramo <N>`.
6. Revisa tiempos, GPS, observaciones e incidencias.
7. Presiona **Guardar**.

---

## Vista de la pantalla (mapa visual)

```
Seguimiento Detallado
-------------------------------------------------------------
[Unidad] [Tramo] [Datos]

Cargar ruta como plantilla: [ ME-TOLUCA-001 - Ruta X ] [Aplicar]
Buscar por codigo:          [ ME-TOLUCA-001             ] [Cargar codigo]

Mensaje: Ruta ME-TOLUCA-001 aplicada en tramo 2: A -> B

Formulario:
  Plantilla aplicada: ME-TOLUCA-001 - Ruta X
  - tiempos
  - estatus
  - observaciones
  - incidencias

                                      [Guardar]
-------------------------------------------------------------
```

---

## Paso a paso detallado

### 1) Seleccionar unidad/tramo

- En **Seleccionar unidad**, elige la unidad activa.
- Si la unidad tiene multiples tramos, elige el tramo correcto en **Seleccionar tramo**.

### 2) Aplicar ruta plantilla

- Opcion A (recomendada):
  - En **Cargar ruta como plantilla**, selecciona una ruta activa.
  - Clic en **Aplicar**.
- Opcion B (por codigo):
  - Escribe el codigo en **Buscar por codigo** (ejemplo: `ME-TOLUCA-001`).
  - Clic en **Cargar codigo**.

### 3) Confirmar autollenado

- Debe aparecer un mensaje de confirmacion.
- Debe mostrarse la banda: `Plantilla aplicada: <CODIGO> - <NOMBRE>`.
- El sistema autollenara origen/destino de ese tramo segun la secuencia de la ruta.

### 4) Completar seguimiento

- Ajusta tiempos reales/programados si corresponde.
- Registra validacion GPS.
- Agrega observaciones e incidencias si aplica.

### 5) Guardar

- Clic en **Guardar**.
- El seguimiento se guarda en BD y queda trazabilidad de la plantilla en observaciones.

---

## Checklist operativo

- [ ] Sesion iniciada correctamente.
- [ ] Unidad seleccionada.
- [ ] Tramo correcto seleccionado (si hay varios).
- [ ] Plantilla aplicada (mensaje visible).
- [ ] Banda "Plantilla aplicada" visible en formulario.
- [ ] Datos revisados (tiempos/GPS/observaciones/incidencias).
- [ ] Seguimiento guardado sin error.

---

## Errores comunes y solucion

### No aparecen rutas en el selector

- Verifica que existan rutas **activas** en `Rutas Fijas`.

### La ruta no se aplica

- Asegura que primero elegiste unidad y tramo.
- Revisa que el codigo exista y este activo.

### Se aplico pero no coincide con el tramo esperado

- Cambia el tramo y vuelve a aplicar la plantilla.

### Se guardo pero quiero confirmar que uso plantilla

- Revisa observaciones del seguimiento: se agrega marcador `[RUTA:CODIGO|NOMBRE]`.

---

## Recomendacion para el equipo

- Usar siempre la opcion de **plantilla** antes de capturar tiempos.
- En unidades con multiples tramos, validar tramo antes de aplicar.
- No cerrar la pantalla antes de presionar **Guardar**.
