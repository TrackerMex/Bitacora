# MAPEO: Eventos Wialon → Columnas Tramos

## Resumen rápido

El webhook ahora hace **DUAS operaciones**:

1. **Auditoría**: Insertar evento en `NotificacionesLog` (siempre)
2. **Actualización**: Llenar columna correspondiente en tabla `Tramos` (si está vacía)

---

## Mapeo evento_type → Columna en Tramos

```
evento_type de Wialon    |  Columna en Tramos         |  Índice (0-based)
─────────────────────────┼────────────────────────────┼──────────────────
"salida"                 →  fecha_salida              →  8 (Columna I)
"entrada_carga"          →  fecha_entrada_carga      →  9 (Columna J)
"salida_carga"           →  fecha_salida_carga       →  10 (Columna K)
"descarga"               →  fecha_descarga           →  11 (Columna L)
```

---

## Estructura esperada de tabla Tramos

```
Col#  Nombre                  Tipo       Descripción
────────────────────────────────────────────────────
0     tramo_id                UUID       Llave primaria
1     fecha_inicio            DateTime   Inicio del tramo
2     fecha_fin               DateTime   Fin del tramo
3     unit_id                 String     UNIT_ID de Wialon
4     ruta                    String     Nombre de ruta
5     conductor               String     Nombre conductor
6     estado                  Enum       activo|completado|cancelado
7     timestamp_creacion      DateTime   Cuándo se creó
8  → fecha_salida             DateTime   ⬅️ ACTUALIZAR CON EVENTO "salida"
9  → fecha_entrada_carga      DateTime   ⬅️ ACTUALIZAR CON EVENTO "entrada_carga"
10 → fecha_salida_carga       DateTime   ⬅️ ACTUALIZAR CON EVENTO "salida_carga"
11 → fecha_descarga           DateTime   ⬅️ ACTUALIZAR CON EVENTO "descarga"
12   timestamp_actualizacion  DateTime   Se actualiza con cada cambio
```

---

## Flujo de ejemplo

### Escenario: Camión A1 (unit_id: 45839283)

**Tiempo 08:00**
```
Wialon envía: {
  "unit_id": "45839283",
  "event_type": "salida",
  "pos_time": "2026-04-24T08:00:00Z"
}

El webhook:
1. Busca tramo activo para unit_id=45839283 en 08:00 ✓ Encontrado (tramo_uuid_xyz)
2. Inserta en NotificacionesLog:
   - notificacion_id: uuid_123
   - tramo_id: tramo_uuid_xyz
   - tipo_evento: "salida"
   - timestamp_evento: "2026-04-24T08:00:00Z"

3. Actualiza Tramos (fila del tramo_uuid_xyz):
   - Columna: fecha_salida (índice 8)
   - Valor actual: (vacío)
   - → ACTUALIZA: fecha_salida = "2026-04-24T08:00:00Z" ✅
```

**Tiempo 10:30 - Llegada a carga**
```
Wialon envía: {
  "unit_id": "45839283",
  "event_type": "entrada_carga",
  "pos_time": "2026-04-24T10:30:00Z"
}

El webhook:
1. Busca tramo ✓ Encontrado (mismo tramo_uuid_xyz)
2. Inserta en NotificacionesLog:
   - tipo_evento: "entrada_carga"
   - timestamp_evento: "2026-04-24T10:30:00Z"

3. Actualiza Tramos:
   - Columna: fecha_entrada_carga (índice 9)
   - Valor actual: (vacío)
   - → ACTUALIZA: fecha_entrada_carga = "2026-04-24T10:30:00Z" ✅
```

**Tiempo 10:35 - Otro evento de entrada (duplicado)**
```
Wialon envía (por error): {
  "unit_id": "45839283",
  "event_type": "entrada_carga",
  "pos_time": "2026-04-24T10:35:00Z"
}

El webhook:
1. Busca tramo ✓ Encontrado
2. Inserta en NotificacionesLog ✅
   - (Se registra igual para auditoría)

3. Actualiza Tramos:
   - Columna: fecha_entrada_carga
   - Valor actual: "2026-04-24T10:30:00Z" (YA TIENE VALOR)
   - → NO ACTUALIZA ❌ (Primera llegada gana)
   - Registra en WebhookLogs: "Columna ya tiene valor"
```

**Tiempo 14:00 - Descarga**
```
Wialon envía: {
  "unit_id": "45839283",
  "event_type": "descarga",
  "pos_time": "2026-04-24T14:00:00Z"
}

El webhook:
1. Busca tramo ✓ Encontrado
2. Inserta en NotificacionesLog
3. Actualiza Tramos:
   - Columna: fecha_descarga (índice 11)
   - Valor actual: (vacío)
   - → ACTUALIZA: fecha_descarga = "2026-04-24T14:00:00Z" ✅
```

**Resultado final en Tramos**
```
tramo_id           | fecha_salida        | fecha_entrada_carga | fecha_salida_carga | fecha_descarga
──────────────────┼────────────────────┼────────────────────┼───────────────────┼─────────────────
tramo_uuid_xyz    | 08:00:00           | 10:30:00           | (vacío)            | 14:00:00
```

---

## Comportamiento especial

### ¿Qué pasa si falta fecha_salida_carga?
Es normal. Si el camión no hace parada en carga, ese campo permanece vacío. Los eventos solo llenan lo que ocurrió.

### ¿Qué si llegan fuera de orden?
No importa. El webhook verifica que el timestamp del evento cae dentro del rango [fecha_inicio, fecha_fin] del tramo. Si no, rechaza.

### ¿Qué si llega un evento que no está en el mapeo?
Retorna error 400: "Tipo de evento no soportado: {event_type}"
Registra en WebhookLogs para debugging.

### ¿Qué si no hay tramo activo?
Rechaza con error 404. Registra en WebhookLogs: "TRAMO_NO_ENCONTRADO"
**Nota**: El evento SÍ se registra en NotificacionesLog para auditoría (así ves qué llegó).

---

## Validaciones críticas

1. **Token válido** ✓ (Script Properties)
2. **event_type soportado** ✓ (existe en EVENT_TO_COLUMN)
3. **unit_id existe** ✓ (buscarUnidad)
4. **Tramo activo en ese horario** ✓ (buscarTramoActivo)
5. **Columna existe en tabla** ✓ (columnMap)
6. **Columna está vacía** ✓ (primera llegada gana)

---

## Cómo configurar en Wialon

En la sección Webhooks de Wialon:

```
URL:            https://script.google.com/macros/d/[TU_DEPLOYMENT_ID]/usercallable
Método:         POST
Content-Type:   application/json
Eventos:        ✓ Salida (mapea a "salida")
                ✓ Entrada de carga (mapea a "entrada_carga")
                ✓ Salida de carga (mapea a "salida_carga")
                ✓ Descarga (mapea a "descarga")
```

**Payload JSON desde Wialon:**
```json
{
  "token": "%TOKEN%",
  "unit_id": "%UNIT_ID%",
  "event_type": "salida",
  "pos_time": "%POS_TIME%",
  "position": {
    "latitude": %LAT%,
    "longitude": %LON%
  },
  "speed": %SPEED%,
  "odometer": %ODOMETER%,
  "timestamp_received": "%POS_TIME%"
}
```

---

## Testing con curl

```bash
curl -X POST https://script.google.com/macros/d/[ID]/usercallable \
  -H "Content-Type: application/json" \
  -d '{
    "token": "orh5537498",
    "unit_id": "45839283",
    "event_type": "salida",
    "pos_time": "2026-04-24T08:00:00Z",
    "position": {
      "latitude": 40.7128,
      "longitude": -74.0060
    },
    "speed": 0,
    "odometer": 50000
  }'

# Respuesta esperada:
# {
#   "success": true,
#   "data": "notificacion_uuid_123",
#   "timestamp": "2026-04-24T22:10:00.000Z",
#   "columna_actualizada": "fecha_salida",
#   "fue_actualizado": true,
#   "razon": "Actualizado exitosamente"
# }
```

---

## Índices exactos en AppScript

**IMPORTANTE**: Los índices en el código JS son 0-based, pero Sheets usa 1-based.

```javascript
const columnMap = {
  'fecha_salida': 8,              // Columna I (índice 8, rango (fila, 9))
  'fecha_entrada_carga': 9,       // Columna J (índice 9, rango (fila, 10))
  'fecha_salida_carga': 10,       // Columna K (índice 10, rango (fila, 11))
  'fecha_descarga': 11            // Columna L (índice 11, rango (fila, 12))
};

// Cuando haces getRange(), sumas +1:
cellRef = tramoSheet.getRange(filaActualizar, colIndex + 1);
```

---

## ¿Qué ves en NotificacionesLog?

Cada evento genera UNA fila en NotificacionesLog, independientemente de si actualizó o no el Tramo:

```
notificacion_id         | tramo_id           | unit_id  | tipo_evento    | timestamp_evento    | webhook_status
────────────────────────┼────────────────────┼──────────┼────────────────┼────────────────────┼──────────────
uuid_123                | tramo_uuid_xyz     | 45839283 | salida         | 2026-04-24T08:00Z  | éxito
uuid_124                | tramo_uuid_xyz     | 45839283 | entrada_carga  | 2026-04-24T10:30Z  | éxito
uuid_125                | tramo_uuid_xyz     | 45839283 | entrada_carga  | 2026-04-24T10:35Z  | éxito (dup)
```

Notas:
- **uuid_123**: Actualiza fecha_salida
- **uuid_124**: Actualiza fecha_entrada_carga
- **uuid_125**: NO actualiza (columna ya tiene valor), pero se registra igual para auditoría

---

## Troubleshooting

| Síntoma | Causa | Solución |
|---------|-------|----------|
| `"success": false, "data": "Token inválido"` | Token no coincide | Verificar Script Properties |
| `"success": false, "data": "No hay tramo activo"` | Tramo no existe o fuera de horario | Crear tramo con fecha_inicio/fin correctas |
| `"fue_actualizado": false, "razon": "Columna ya tiene valor"` | Duplicado de evento | Es normal, auditoría sigue funcionando |
| Columna no se actualiza en Sheets | Índice de columna mal mapeado | Verificar que columna está en índice correcto (contar desde 0) |
| Llega a WebhookSecurityLog pero no a NotificacionesLog | Error en registrarNotificacion() | Verificar que hoja existe y estructura es correcta |

---

## Checklist antes de ir a producción

- [ ] Crear 4 hojas en Sheets: Tramos, NotificacionesLog, UnidadesConfig, WebhookSecurityLog
- [ ] Copiar webhook_actualizado.js a Apps Script
- [ ] Ejecutar setupScriptProperties() para guardar IDs y token
- [ ] Crear UUID de tramo en Planificador
- [ ] Test POST con curl (ver ejemplo arriba)
- [ ] Verificar que NotificacionesLog se llenó
- [ ] Verificar que columna en Tramo se actualizó
- [ ] Configurar webhook en Wialon con JSON payload
- [ ] Test con evento real de Wialon
- [ ] Monitorear WebhookSecurityLog por errores

---

¡Listo! Las notificaciones ahora actualizan tu tabla de Tramos en tiempo real.
