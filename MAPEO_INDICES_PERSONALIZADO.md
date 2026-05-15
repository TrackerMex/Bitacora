# ⚙️ MAPEO DE ÍNDICES - ESTRUCTURA PERSONALIZADA DE ALEX

## Estructura actual de tu tabla Tramos

```
Índice 0-based | Columna Letter | Nombre columna
───────────────┼────────────────┼─────────────────────────────
0              | A              | fecha
1              | B              | folio
2              | C              | unidad              ← Identificador de vehículo
3              | D              | placas
4              | E              | id_equipos
5              | F              | operador
6              | G              | telefono
7              | H              | ruta
8              | I              | origen
9              | J              | destino
10             | K              | salida_prog
11             | L              | carga_prog
12             | M              | salida_carga_prog
13             | N              | descarga_prog
14             | O              | cliente
15             | P              | sub_cliente
16             | Q              | (vacío?)
17             | R              | fecha_salida        ← ACTUALIZAR (evento "salida")
18             | S              | fecha_entrada_carga ← ACTUALIZAR (evento "entrada_carga")
19             | T              | fecha_salida_carga  ← ACTUALIZAR (evento "salida_carga")
20             | U              | fecha_descarga      ← ACTUALIZAR (evento "descarga")
21             | V              | tramo_id            ← LLAVE PRIMARIA
```

---

## 🔑 OBSERVACIONES IMPORTANTES

⚠️ **Diferencias con el plan original:**

1. **Identificador de vehículo**: 
   - Plan original: `unit_id` (Wialon)
   - Tu estructura: `unidad` (columna índice 2) o `placas` (índice 3)
   - **Decision**: Usaremos `unidad` como identificador en el webhook

2. **Rango de fechas**: 
   - Plan original: `fecha_inicio` y `fecha_fin` para validar que evento caiga en rango
   - Tu estructura: No tienes estas columnas
   - **Decision**: Validaremos directamente contra `tramo_id` (buscar si existe ese tramo para esa unidad)

3. **Ubicación de tramo_id**: 
   - Plan original: Primero (índice 0)
   - Tu estructura: Último (índice 21)
   - **Impacto**: El código debe buscar por `tramo_id` en columna 21

4. **Fechas programadas vs. reales**:
   - Tienes `salida_prog`, `carga_prog`, `salida_carga_prog`, `descarga_prog` (planeadas)
   - Y `fecha_salida`, `fecha_entrada_carga`, `fecha_salida_carga`, `fecha_descarga` (reales)
   - **Correcto**: El webhook actualiza las REALES ✅

---

## 📍 MAPEO FINAL: Evento → Columna de Actualización

```
Evento Wialon      | Columna a actualizar   | Índice | Letter | Contenido esperado
───────────────────┼────────────────────────┼────────┼────────┼─────────────────────
"salida"           | fecha_salida           | 17     | R      | DateTime del evento
"entrada_carga"    | fecha_entrada_carga    | 18     | S      | DateTime del evento
"salida_carga"     | fecha_salida_carga     | 19     | T      | DateTime del evento
"descarga"         | fecha_descarga         | 20     | U      | DateTime del evento
```

---

## 🔍 BÚSQUEDA DE TRAMO: Lógica ajustada

**Antes (plan original)**:
```
WHERE unit_id = X 
  AND fecha_inicio <= pos_time <= fecha_fin 
  AND estado = 'activo'
```

**Ahora (tu estructura)**:
```
WHERE unidad = X (o placas = X)
  AND tramo_id IS NOT EMPTY
  AND la fila existe con datos
```

**En AppScript:**
```javascript
// Buscar por unidad (columna 2)
for (let i = 1; i < data.length; i++) {
  if (data[i][2] === unidad &&      // columna "unidad"
      data[i][21] !== '' &&          // columna "tramo_id" (21) no está vacía
      data[i][0] !== '') {           // columna "fecha" (0) no está vacía
    return {
      tramo_id: data[i][21],
      fila: i + 1
    };
  }
}
```

---

## ⚡ SCRIPT ACTUALIZADO PARA TU ESTRUCTURA

Aquí está el `actualizarTramo()` adaptado a tus índices:

```javascript
// ===== FUNCIÓN ACTUALIZADA: Actualizar Tramo (TU ESTRUCTURA) =====
function actualizarTramo(unidad, columna, timestamp_evento) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.PLANIFICADOR);
    const tramoSheet = ss.getSheetByName('Tramos');
    const data = tramoSheet.getDataRange().getValues();

    // MAPEO DE COLUMNAS PARA TU ESTRUCTURA
    const columnMap = {
      'fecha_salida': 17,              // Índice 17 (Columna R)
      'fecha_entrada_carga': 18,       // Índice 18 (Columna S)
      'fecha_salida_carga': 19,        // Índice 19 (Columna T)
      'fecha_descarga': 20             // Índice 20 (Columna U)
    };

    const colIndex = columnMap[columna];
    if (colIndex === undefined) {
      return {
        fue_actualizado: false,
        razon: 'Columna no mapeada: ' + columna
      };
    }

    // Buscar fila del tramo por UNIDAD
    // Headers: [fecha(0), folio(1), unidad(2), placas(3), ..., tramo_id(21)]
    let filaActualizar = -1;
    let tramoIdEncontrado = '';

    for (let i = 1; i < data.length; i++) {
      const rowUnidad = data[i][2];    // columna "unidad" (índice 2)
      const rowTramoId = data[i][21];  // columna "tramo_id" (índice 21)

      if (rowUnidad === unidad && rowTramoId !== '' && rowTramoId !== null) {
        filaActualizar = i + 1;  // +1 porque Sheets usa 1-indexing
        tramoIdEncontrado = rowTramoId;
        break;
      }
    }

    if (filaActualizar === -1) {
      return {
        fue_actualizado: false,
        razon: 'No se encontró tramo para unidad: ' + unidad
      };
    }

    // Obtener valor actual de la columna
    const cellRef = tramoSheet.getRange(filaActualizar, colIndex + 1); // +1 para Sheets
    const valorActual = cellRef.getValue();

    // PRIMERA LLEGADA GANA
    if (valorActual === '' || valorActual === null) {
      cellRef.setValue(timestamp_evento);
      Logger.log(`✅ Actualizado: tramo_id=${tramoIdEncontrado}, columna=${columna}, valor=${timestamp_evento}`);
      return {
        fue_actualizado: true,
        razon: 'Actualizado exitosamente',
        tramo_id: tramoIdEncontrado
      };
    } else {
      Logger.log(`⚠️ No actualizado: columna ya tiene valor ${valorActual}`);
      return {
        fue_actualizado: false,
        razon: 'Columna ya tiene valor: ' + valorActual + ' (primera llegada gana)',
        tramo_id: tramoIdEncontrado
      };
    }

  } catch (error) {
    registrarErrorLog('ERROR_UPDATE_TRAMO', error.message);
    return {
      fue_actualizado: false,
      razon: 'Excepción: ' + error.message
    };
  }
}
```

---

## 🔄 FUNCIÓN BUSCAR TRAMO - ADAPTADA

```javascript
// ===== FUNCIÓN ADAPTADA: Buscar tramo activo (TU ESTRUCTURA) =====
function buscarTramoActivo(unidad) {
  try {
    const ss = SpreadsheetApp.openById(SHEETS_IDS.PLANIFICADOR);
    const tramoSheet = ss.getSheetByName('Tramos');
    const data = tramoSheet.getDataRange().getValues();

    // Headers: [fecha(0), folio(1), unidad(2), placas(3), ..., tramo_id(21)]
    for (let i = 1; i < data.length; i++) {
      const rowUnidad = data[i][2];    // columna "unidad" (índice 2)
      const rowTramoId = data[i][21];  // columna "tramo_id" (índice 21)
      const rowFolio = data[i][1];     // columna "folio" (índice 1) - identificador visual

      // Criterios: unidad coincide, tramo_id no está vacío
      if (rowUnidad === unidad && rowTramoId !== '' && rowTramoId !== null) {
        return {
          tramo_id: rowTramoId,
          folio: rowFolio,
          unidad: rowUnidad,
          fila: i + 1
        };
      }
    }

    return null; // No encontrado

  } catch (error) {
    Logger.log('Error en buscarTramoActivo: ' + error.message);
    return null;
  }
}
```

---

## 🧪 AJUSTE EN doPost() - USAR "unidad" EN LUGAR DE "unit_id"

```javascript
function doPost(e) {
  try {
    const payload = JSON.parse(e.postData.contents);

    // Validar token
    const token = PropertiesService.getScriptProperties().getProperty('WIALON_TOKEN');
    if (payload.token !== token) {
      return crearRespuesta(false, 'Token inválido', 401);
    }

    // Extraer datos - CAMBIO: usar "unidad" en lugar de "unit_id"
    const { unidad, event_type, pos_time, position, speed, odometer } = payload;

    // Validar event_type
    if (!EVENT_TO_COLUMN[event_type]) {
      return crearRespuesta(false, 'Tipo de evento no soportado: ' + event_type, 400);
    }

    // CAMBIO: buscar por unidad (no necesita fecha_inicio/fecha_fin)
    const tramo = buscarTramoActivo(unidad);
    if (!tramo) {
      registrarErrorLog('TRAMO_NO_ENCONTRADO', unidad, event_type, pos_time);
      return crearRespuesta(false, 'No hay tramo para unidad: ' + unidad, 404);
    }

    // Buscar nombre de unidad (aquí es igual a unidad)
    const nombre_unidad = tramo.unidad || unidad;

    // Generar IDs
    const notificacion_id = Utilities.getUuid();
    const timestamp_recibida = new Date().toISOString();

    // OPERACIÓN A: Registrar en NotificacionesLog
    registrarNotificacion({
      notificacion_id,
      tramo_id: tramo.tramo_id,
      unidad,
      nombre_unidad,
      tipo_evento: event_type,
      timestamp_evento: pos_time,
      timestamp_recibida: timestamp_recibida,
      latitud: position.latitude,
      longitud: position.longitude,
      velocidad: speed,
      odometro: odometer,
      webhook_status: 'éxito'
    });

    // OPERACIÓN B: Actualizar columna en Tramo
    const columna = EVENT_TO_COLUMN[event_type];
    const resultado_update = actualizarTramo(unidad, columna, pos_time);

    // Retornar éxito
    return crearRespuesta(true, notificacion_id, tramo.tramo_id, {
      folio: tramo.folio,
      columna_actualizada: columna,
      fue_actualizado: resultado_update.fue_actualizado,
      razon: resultado_update.razon
    });

  } catch (error) {
    registrarErrorLog('EXCEPCIÓN', error.message, error.stack);
    return crearRespuesta(false, 'Error interno: ' + error.message, 500);
  }
}
```

---

## 📋 PAYLOAD DESDE WIALON - AJUSTE

En lugar de `unit_id`, usa `unidad`:

```json
{
  "token": "orh5537498",
  "unidad": "16BH8N",        ← CAMBIO: "unidad" en lugar de "unit_id"
  "event_type": "salida",
  "pos_time": "2026-04-24T08:00:00Z",
  "position": {
    "latitude": 40.7128,
    "longitude": -74.0060
  },
  "speed": 0,
  "odometer": 50000
}
```

O si prefieres usar `placas`:
```json
{
  "token": "orh5537498",
  "placas": "52UH2E",         ← Usar placas en lugar
  "event_type": "salida",
  ...
}
```

---

## 🧐 NOTAS IMPORTANTES PARA TU ESTRUCTURA

1. **Sin rango de fechas**: No validamos `fecha_inicio` y `fecha_fin`. Solo buscamos que exista un tramo para esa unidad. ✅
   
2. **Sin "estado"**: No validamos si el tramo está "activo" o "completado". Asumir que si tiene `tramo_id`, es válido. ✅

3. **Duplicados de unidad**: Si tienes múltiples tramos para la misma unidad en el Sheets, el código toma el **PRIMERO que encuentre**. Si necesitas más precisión, podríamos agregar validación de fechas programadas.

4. **El `folio` es único**: Si cada `folio` es único por tramo (ej: "CR-06" es de una ruta específica), podrías usar eso como identificador en lugar de `unidad`.

---

## ✅ CHECKLIST DE VALIDACIÓN

- [ ] Verificar que `unidad` en columna 2 tiene valores como "16BH8N"
- [ ] Verificar que `tramo_id` en columna 21 tiene UUIDs o identificadores únicos
- [ ] Verificar que `fecha_salida`, `fecha_entrada_carga`, etc. están en columnas 17, 18, 19, 20
- [ ] Test: Enviar evento con `unidad: "16BH8N"`
- [ ] Verificar que se actualiza la columna R (fecha_salida)
- [ ] Verificar que se registra en NotificacionesLog
- [ ] Si envías el mismo evento 2 veces, que NO sobrescribe (primera llegada gana)

---

## 🎯 PRÓXIMOS PASOS

1. **Confirma** que los índices son correctos (especialmente columnas 17-20 y 21)
2. **Actualiza** el código `webhook_actualizado.js` con estas funciones adaptadas
3. **Test** con curl/Postman usando `"unidad"` como parámetro
4. **Monitorea** WebhookLogs para cualquier error

¿Te parece correcto este mapeo? ¿Necesitas ajustar algo?
