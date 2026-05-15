# 📋 RESUMEN: Sistema de Notificaciones Wialon → Bitácora

## 🎯 Objetivo
Capturar eventos GPS de Wialon (salida, entrada_carga, salida_carga, descarga) y actualizar automáticamente las columnas correspondientes en Google Sheets, vinculando todo con un ID único de tramo.

---

## 🏗️ Arquitectura

```
Wialon (evento GPS)
    ↓ POST query string
AppScript webhook (doPost)
    ├→ Busca unidad en Sheets
    ├→ Valida que tenga tramo_id
    ├→ Registra en NotificacionesLog (auditoría)
    └→ Actualiza columna en tabla Datos
```

---

## 📊 Estructura Sheets (tabla "Datos")

| Columna | Índice | Uso |
|---------|--------|-----|
| C | 2 | **unidad** (búsqueda) |
| Q | 16 | **fecha_salida** (actualizar) |
| R | 17 | **fecha_entrada_carga** (actualizar) |
| S | 18 | **fecha_salida_carga** (actualizar) |
| T | 19 | **fecha_descarga** (actualizar) |
| U | 20 | **tramo_id** (validación) |

---

## 🔗 Mapeo: Evento → Columna

```
evento_type          → columna
─────────────────────────────
"salida"             → fecha_salida (Q)
"entrada_carga"      → fecha_entrada_carga (R)
"salida_carga"       → fecha_salida_carga (S)
"descarga"           → fecha_descarga (T)
```

---

## 📡 Payload desde Wialon (Query String)

**Formato:** NO es JSON, es query string plano

```
token=orh5537498&unidad=%UNIT%&event_type=salida&pos_time=%POS_TIME%&latitude=%LATD%&longitude=%LOND%&speed=%SPEED%&odometer=%MILEAGE%
```

**Parámetros clave:**
- `%UNIT%` → Nombre unidad (ej: EQUIPO DE PRUEBA)
- `%LATD%`, `%LOND%` → Coordenadas sin formato (números decimales)
- `%SPEED%` → Velocidad (puede incluir "km/h", se extrae número)
- `%MILEAGE%` → Odómetro (puede incluir "km", se extrae número)
- `%POS_TIME%` → Fecha/hora evento

---

## ✅ Reglas Implementadas

### 1. **Primera Llegada Gana**
- Si columna ya tiene valor, NO se sobrescribe
- Evento duplicado se registra en log pero NO actualiza

### 2. **Rate Limiting**
- Máximo 100 eventos/minuto por unidad
- Evita spam

### 3. **Validaciones**
- ✓ Token debe ser correcto
- ✓ event_type debe estar en mapeo
- ✓ unidad debe existir en tabla
- ✓ tramo_id no puede estar vacío

### 4. **Auditoría Completa**
- Cada evento se registra en **NotificacionesLog** (incluso si no actualiza)
- Errores se registran en **WebhookSecurityLog**

---

## 🔑 Archivos Clave

| Archivo | Propósito |
|---------|-----------|
| `webhook_QUERY_STRING.js` | Código AppScript (copia a tu proyecto) |
| `NotificacionesLog` | Hoja: registro de todos los eventos |
| `WebhookSecurityLog` | Hoja: registro de errores |
| `Datos` | Hoja: tabla principal con tramos |

---

## 🚀 Implementación Rápida

```
1. Copiar webhook_QUERY_STRING.js a Apps Script
2. Configurar Script Properties:
   - WIALON_TOKEN = orh5537498
   - BITACORA_SHEET_ID = TU_ID
3. Deploy como Web App (obtener URL)
4. Crear 4 webhooks en Wialon (uno por evento)
5. Probar con Postman
6. Activar en Wialon
```

---

## 🐛 Debugging

### Apps Script Logs
- Muestra parámetros recibidos
- Tramo encontrado/no encontrado
- Actualización realizada o rechazada

### Query de búsqueda
```javascript
// Busca en tabla "Datos"
// Columna 2 (unidad) = parámetro
// Columna 20 (tramo_id) != vacío
```

### Si falla:
1. Revisar Script Properties (¿ID correcto?)
2. Revisar formato query string (¿sin comillas?)
3. Revisar parámetros en Wialon (¿%LATD% no %LAT%?)
4. Ver logs en Apps Script

---

## 📈 Resultado Final

**Cuando evento se genera en Wialon:**
```
Wialon: "EQUIPO DE PRUEBA salió a las 13:00"
    ↓
AppScript: POST http://webhook/url?token=...&unidad=EQUIPO DE PRUEBA&event_type=salida
    ↓
Sheets - NotificacionesLog: ✅ Nueva fila registrada
Sheets - Datos: ✅ Columna Q (fecha_salida) = 13:00
```

---

## 🎯 Ventajas

✅ Actualización automática en tiempo real  
✅ Sin integraciones complejas  
✅ Auditoría completa de eventos  
✅ Protección contra duplicados  
✅ Fácil de escalar  

---

## 📞 Script Properties (Referencias)

```
WIALON_TOKEN        = "orh5537498"
BITACORA_SHEET_ID   = "1u9L81Jp5vRJ0YSaBnDPDJkvdqoamMOfM6OyXiOwHEGY"
APP_SCRIPT_URL       = "https://script.google.com/macros/s/AKfycbyPyEPZuG7H6AAqe3Ryrm260pB7uLuBMGBJ8TsRsD6Vb4lriuSFNrSnwaQyMNWgjOjCzA/exec"
```

---

**Versión:** 1.0  
**Fecha:** 27 de Abril de 2026  
**Autor:** Alex (FullStack Developer)  
**Estado:** ✅ Producción
