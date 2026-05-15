# ⚡ QUICK START - Implementar Notificaciones en 15 minutos

## 📋 Resumen

Tu estructura Sheets es diferente a la del plan original, así que te preparé código personalizado. Aquí está el proceso exacto.

---

## 🎯 PASO 1: Preparar hojas en Sheets (2 min)

En tu **Sheets de Bitácora** (`TU_ID_SHEETS_BITACORA`), crea estas 2 hojas nuevas:

### Hoja 1: NotificacionesLog
Headers (fila 1):
```
notificacion_id | tramo_id | folio | unidad | tipo_evento | timestamp_evento | timestamp_recibida | latitud | longitud | velocidad | odometro | webhook_status
```

Estructura:
- **notificacion_id**: UUID del evento
- **tramo_id**: Referencia a tabla Tramos
- **folio**: Número de operación (ej: CR-06)
- **unidad**: Unidad que generó evento (ej: 16BH8N)
- **tipo_evento**: salida, entrada_carga, salida_carga, descarga
- **timestamp_evento**: Cuándo ocurrió en Wialon
- **timestamp_recibida**: Cuándo llegó al webhook
- **latitud/longitud**: Posición GPS
- **velocidad**: km/h
- **odometro**: Km recorridos
- **webhook_status**: éxito, error, etc

### Hoja 2: WebhookSecurityLog
Headers (fila 1):
```
log_id | timestamp | tipo_error | mensaje | contexto
```

Estructura:
- **log_id**: UUID del log
- **timestamp**: Cuándo ocurrió el error
- **tipo_error**: TOKEN_INVALIDO, TRAMO_NO_ENCONTRADO, RATE_LIMIT_EXCEEDED, etc
- **mensaje**: Descripción del error
- **contexto**: Información adicional (unidad, event_type, etc)

---

## 🎯 PASO 2: Crear proyecto en Apps Script (1 min)

1. Ve a **Google Apps Script**: https://script.google.com
2. Crea un **proyecto nuevo**
3. Copia TODO el contenido de `webhook_alex_final.js` (en tu carpeta bitacora_)
4. Pega en el editor (elimina el código por defecto)
5. **Guarda** (Ctrl+S)

---

## 🎯 PASO 3: Configurar Script Properties (1 min)

En el mismo Apps Script:

1. Click en **Proyecto > Configuración del proyecto**
2. Abre **Script Properties** (a la derecha)
3. Agrega 3 propiedades:

| Propiedad | Valor |
|-----------|-------|
| `WIALON_TOKEN` | `orh5537498` |
| `PLANIFICADOR_SHEET_ID` | Tu ID de Sheets (https://docs.google.com/spreadsheets/d/**ESTE_ID**) |
| `BITACORA_SHEET_ID` | Tu ID de Sheets de Bitácora |

4. **Guarda** las propiedades

---

## 🎯 PASO 4: Ejecutar setupScriptProperties() (30 seg)

En el Apps Script:

1. En el dropdown arriba que dice "Selecciona una función", elige **`setupScriptProperties`**
2. Click en el ▶️ (ejecutar)
3. Permitir acceso cuando pida permisos
4. Verifica en la consola: ✅ "Script Properties configuradas exitosamente"

---

## 🎯 PASO 5: Deploy como Aplicación Web (2 min)

1. Click en **Deploy > New Deployment**
2. En el dropdown de "Select type", elige **"Web app"**
3. En "Execute as", selecciona **tu cuenta de Gmail**
4. En "Who has access", selecciona **"Anyone"**
5. Click en **Deploy**
6. Se abrirá un popup. Copia la **URL de deployment** (se ve así):
   ```
   https://script.google.com/macros/d/AKfycbx...../usercallable
   ```
7. **GUARDA ESTA URL** - la necesitarás en Wialon

---

## 🧪 PASO 6: Test con Postman o curl (3 min)

### Opción A: Con curl (terminal)

```bash
curl -X POST "https://script.google.com/macros/d/TU_URL_DEPLOYMENT/usercallable" \
  -H "Content-Type: application/json" \
  -d '{
    "token": "orh5537498",
    "unidad": "16BH8N",
    "event_type": "salida",
    "pos_time": "2025-08-15T08:00:00Z",
    "position": {
      "latitude": 40.7128,
      "longitude": -74.0060
    },
    "speed": 0,
    "odometer": 50000
  }'
```

⚠️ Cambiar:
- `TU_URL_DEPLOYMENT` por la URL del step anterior
- `"16BH8N"` por una unidad REAL de tu tabla Tramos
- Asegúrate de que esa unidad tiene un `tramo_id` en columna V

### Opción B: Con Postman

1. Abre Postman
2. Nuevo request: POST
3. URL: Pega la URL del deployment
4. Body: raw, JSON, copia el payload de arriba
5. Click Send

### Respuesta esperada:
```json
{
  "success": true,
  "data": "uuid-notificacion-123",
  "timestamp": "2025-04-24T22:10:00.000Z",
  "tramo_id": "uuid-tramo-xyz",
  "folio": "CR-06",
  "columna_actualizada": "fecha_salida",
  "fue_actualizado": true,
  "razon": "Actualizado exitosamente"
}
```

### Si algo falló:

- ❌ `"success": false, "data": "Token inválido"` → Verifica Script Properties
- ❌ `"success": false, "data": "No hay tramo para unidad..."` → La unidad no existe o no tiene tramo_id
- ❌ `"fue_actualizado": false, "razon": "Columna ya tiene valor"` → Es normal, significa que ya fue actualizado antes (primera llegada gana)

---

## 🎯 PASO 7: Verificar datos en Sheets (1 min)

Después de hacer el test:

1. Abre tu **Sheets de Bitácora**
2. Ve a hoja **NotificacionesLog**
3. Verifica que se insertó una fila con el evento

Ahora ve a tu **Sheets del Planificador**:
4. Ve a hoja **Tramos**
5. Busca la fila con unidad "16BH8N"
6. Verifica que la columna **R** (fecha_salida) se actualizó con el timestamp

---

## 🎯 PASO 8: Configurar webhook en Wialon (5 min)

En tu cuenta de Wialon:

1. Ve a **Configuración > Webhooks** (o similar, depende versión Wialon)
2. **Crear nuevo webhook**
3. Configura:

| Campo | Valor |
|-------|-------|
| **URL** | Pega la URL del deployment (Step 5) |
| **Método** | POST |
| **Content-Type** | application/json |
| **Eventos** | Salida, Entrada carga, Salida carga, Descarga |

4. **Payload personalizado** (si Wialon lo permite):

```json
{
  "token": "orh5537498",
  "unidad": "%UNIT_ID%",
  "event_type": "%EVENT_TYPE%",
  "pos_time": "%POS_TIME%",
  "position": {
    "latitude": %LAT%,
    "longitude": %LON%
  },
  "speed": %SPEED%,
  "odometer": %ODOMETER%
}
```

⚠️ **Mapeo de eventos de Wialon**:
- Evento "Salida" → `"event_type": "salida"`
- Evento "Entrada a punto de carga" → `"event_type": "entrada_carga"`
- Evento "Salida de punto de carga" → `"event_type": "salida_carga"`
- Evento "Descarga completada" → `"event_type": "descarga"`

5. **Guardar webhook**

---

## 🔍 VALIDACIÓN FINAL

Después de configurar en Wialon:

✅ **Test 1**: Cuando el camión 16BH8N sale
- Ve a NotificacionesLog → debe haber nueva fila con tipo_evento="salida"
- Ve a tabla Tramos → columna R (fecha_salida) debe tener timestamp

✅ **Test 2**: Cuando entra a carga
- Ve a NotificacionesLog → debe haber nueva fila con tipo_evento="entrada_carga"
- Ve a tabla Tramos → columna S (fecha_entrada_carga) debe tener timestamp

✅ **Test 3**: Si el mismo evento se repite (duplicado)
- NotificacionesLog registra el evento (auditoría)
- Pero tabla Tramos NO se actualiza (primera llegada gana)
- WebhookSecurityLog registra: "Columna ya tiene valor"

---

## 🐛 Debugging si algo no funciona

**Revisa la consola de Apps Script**:
1. En tu proyecto → Click en **Logs** (abajo)
2. Verás mensajes:
   - ✅ "Tramo encontrado..."
   - ❌ "No se encontró tramo para unidad..."
   - ⚠️ "Columna ya tiene valor..."

**Revisa WebhookSecurityLog en Sheets**:
- Si hay errores, aparecerán ahí con tipo_error y mensaje

**Test manualmente**:
- En Apps Script, ejecuta la función **testWebhook()**
- Simula un evento sin Wialon real
- Verifica que todo funciona

---

## 📍 ÍNDICES CLAVE (por si necesitas debuggear)

Tu estructura Tramos:
```
Índice 0-based | Columna | Nombre
───────────────┼─────────┼─────────────────────
0              | A       | fecha
1              | B       | folio
2              | C       | unidad             ← Se busca por esto
17             | R       | fecha_salida       ← Se actualiza
18             | S       | fecha_entrada_carga
19             | T       | fecha_salida_carga
20             | U       | fecha_descarga
21             | V       | tramo_id           ← Se valida que no está vacío
```

---

## ✅ CHECKLIST FINAL

- [ ] Creé hojas NotificacionesLog y WebhookSecurityLog
- [ ] Copié `webhook_alex_final.js` a Apps Script
- [ ] Configuré Script Properties (token, IDs)
- [ ] Ejecuté setupScriptProperties()
- [ ] Hice Deploy como Web App
- [ ] Test con curl/Postman ✅
- [ ] Verificar datos en NotificacionesLog ✅
- [ ] Verificar actualización en tabla Tramos ✅
- [ ] Configuré webhook en Wialon
- [ ] Primera salida real de camión → actualizó Sheets ✅

---

¡Listo! Si algo no funciona, revisa:
1. Script Properties tiene los IDs correctos
2. Las hojas existen y tienen los headers exactos
3. El webhook está activado en Wialon
4. La unidad en el test existe en tu tabla Tramos con un tramo_id

¿Necesitas ayuda en algún step?
