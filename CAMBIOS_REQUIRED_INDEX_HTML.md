# MODIFICACIÓN: src/index.html - Usar Google Sheets Proxy

## ¿Por qué cambiar?

Actualmente, `src/index.html` expone tu API key de Google Sheets en el cliente (JavaScript):

```javascript
const API_KEY = "AIzaSyDtA5bl8XuclA92cz2f-eCswuWQul87f0I";  // ❌ EXPUESTO
```

Esto es un **riesgo de seguridad crítico** porque:
- La API key es visible en el inspector del navegador
- Se puede reutilizar en otros contextos
- Google puede revocar el acceso si se detecta abuso

## Solución: Usar proxy PHP seguro

Hemos creado `src/api/google-sheets-proxy.php` que:
- Mantiene la API key en el servidor (segura)
- Expone un endpoint seguro para obtener datos
- Cachea datos por 5 minutos
- Valida requests

---

## Cambios necesarios en src/index.html

### 1. REMOVER líneas 967-970 (API key y spreadsheet ID)

**BUSCAR:**
```javascript
      const API_KEY = "AIzaSyDtA5bl8XuclA92cz2f-eCswuWQul87f0I";
      const SPREADSHEET_ID = "1XwjnIxq98oStetgaD5XDWpfgUhMCR1dgCzY8eVa3tiE";
      const RANGE = "BITACORA!A1:O944";
      const CONTACTOS = "Contactos!A1:H";
```

**CAMBIAR A:**
```javascript
      // API key está protegida en el backend (src/api/google-sheets-proxy.php)
      // Las constantes ahora se cargan desde el servidor mediante proxy
      const SPREADSHEET_ID = "1XwjnIxq98oStetgaD5XDWpfgUhMCR1dgCzY8eVa3tiE";  // Solo para referencia interna
      const RANGE = "BITACORA!A1:O944";
      const CONTACTOS = "Contactos!A1:H";
```

### 2. REEMPLAZAR función loadDataFromGoogleSheets (líneas 1211-1237)

**BUSCAR:**
```javascript
      async function loadDataFromGoogleSheets(isInitialLoad = true) {
        const container = document.getElementById("datos-generales-container");
        if (isInitialLoad) {
          container.innerHTML = `<div class="col-span-full text-center p-10 bg-white rounded-xl shadow"><h3 class="text-lg font-medium text-gray-700">Cargando datos...</h3><p class="text-gray-500 mt-2">Conectando con Google Sheets.</p></div>`;
        }
        try {
          const url = `https://sheets.googleapis.com/v4/spreadsheets/${SPREADSHEET_ID}/values/${RANGE}?key=${API_KEY}`;
          const response = await fetch(url);
          const apiResponse = await response.json();
          if (!response.ok)
            throw new Error(
              apiResponse.error
                ? apiResponse.error.message
                : "Error de conexión."
            );
          if (!apiResponse.values || apiResponse.values.length === 0)
            throw new Error(
              `El RANGO ('${RANGE}') está vacío o es incorrecto.`
            );
          const jsonData = convertSheetDataToObjects(apiResponse.values);
          processData(jsonData);
        } catch (error) {
          container.innerHTML = `<div class="col-span-full text-center p-10 bg-white rounded-xl shadow"><h3 class="text-lg font-medium text-red-700">Error Crítico</h3><p class="mt-4 bg-red-50 p-3 rounded-md text-left"><strong>Detalle:</strong> ${escapeHtml(
            error.message
          )}</p></div>`;
        }
      }
```

**CAMBIAR A:**
```javascript
      async function loadDataFromGoogleSheets(isInitialLoad = true) {
        const container = document.getElementById("datos-generales-container");
        if (isInitialLoad) {
          container.innerHTML = `<div class="col-span-full text-center p-10 bg-white rounded-xl shadow"><h3 class="text-lg font-medium text-gray-700">Cargando datos...</h3><p class="text-gray-500 mt-2">Conectando con servidor.</p></div>`;
        }
        try {
          // Usar proxy seguro en lugar de Google Sheets API directamente
          const url = '/bitacora_/src/api/google-sheets-proxy.php?action=bitacora';
          const response = await fetch(url);
          
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
          }
          
          const apiResponse = await response.json();
          
          // Validar respuesta del proxy
          if (!apiResponse.success) {
            throw new Error(apiResponse.message || 'Error al obtener datos');
          }
          
          // Verificar que hay datos
          if (!apiResponse.data || apiResponse.data.length === 0) {
            throw new Error('No hay datos disponibles en Google Sheets');
          }
          
          // Procesar datos del proxy
          const jsonData = convertSheetDataToObjects(apiResponse.data);
          processData(jsonData);
          
        } catch (error) {
          console.error('Error cargando datos:', error);
          container.innerHTML = `<div class="col-span-full text-center p-10 bg-white rounded-xl shadow"><h3 class="text-lg font-medium text-red-700">Error Crítico</h3><p class="mt-4 bg-red-50 p-3 rounded-md text-left"><strong>Detalle:</strong> ${escapeHtml(
            error.message
          )}</p></div>`;
        }
      }
```

### 3. REEMPLAZAR función loadEmergencyContactsFromSheet (líneas 1240-1334)

**BUSCAR:**
```javascript
      async function loadEmergencyContactsFromSheet() {
        const url = `https://sheets.googleapis.com/v4/spreadsheets/${SPREADSHEET_ID}/values/${encodeURIComponent(
          CONTACTOS
        )}?key=${API_KEY}`;

        try {
          const response = await fetch(url);
          const data = await response.json();
          // ... resto del código ...
```

**CAMBIAR A:**
```javascript
      async function loadEmergencyContactsFromSheet() {
        // Usar proxy seguro para obtener contactos
        const url = '/bitacora_/src/api/google-sheets-proxy.php?action=contactos';

        try {
          const response = await fetch(url);
          
          if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
          }
          
          const apiResponse = await response.json();
          
          if (!apiResponse.success) {
            console.error('Error al obtener contactos:', apiResponse.message);
            return [];
          }

          const rows = apiResponse.data || [];
          
          if (!rows || rows.length < 2) {
            return [];
          }

          const headers = rows[0].map((h) =>
            (h || "")
              .toString()
              .trim()
              .toLowerCase()
              .replace(/[^a-záéíóúñü\s]/g, "")
          );

          // Mapear tus encabezados exactos:
          const headerMap = {
            "nombre contacto": "label",
            nombre: "label",
            "cargo/posicion": "cargo",
            cargo: "cargo",
            "area/departamento": "departamento",
            departamento: "departamento",
            "prioridad contacto": "prioridad",
            prioridad: "prioridad",
            telefonos: "telefonos",
            correos: "correos",
            accionesatomar: "acciones",
            acciones: "acciones",
            observaciones: "observaciones",
          };

          const records = rows.slice(1).map((row) => {
            const obj = {};

            headers.forEach((header, i) => {
              const normalizedHeader =
                Object.keys(headerMap).find(
                  (key) => header.includes(key) || key.includes(header)
                ) || header;

              obj[headerMap[normalizedHeader] || normalizedHeader] =
                row[i] || "";
            });

            return {
              label: obj.label || "Contacto",
              nombre: obj.nombre || obj.label || "",
              cargo: obj.cargo || "",
              departamento: obj.departamento || "",
              prioridad: obj.prioridad || "Normal",
              telefonos: (obj.telefonos || "")
                .split(/[\n,;|]/)
                .map((t) => t.trim())
                .filter(Boolean),
              correos: (obj.correos || "")
                .split(/[\n,;|]/)
                .map((c) => c.trim())
                .filter(Boolean),
              acciones: obj.acciones || "",
              observaciones: obj.observaciones || "",
            };
          });

          // Filtrar filas vacías
          return records.filter((r) => r.nombre && r.nombre.trim());
          
        } catch (error) {
          console.error("Error cargando contactos de emergencia:", error);
          return [];
        }
      }
```

---

## Verificación post-cambios

### 1. Verificar sintaxis
```bash
# Validar HTML
# Abrir en navegador y revisar consola (F12)
```

### 2. Testear en navegador
```javascript
// En consola del navegador (F12 > Console)
// Verificar que los datos se cargan correctamente
```

### 3. Testear en servidor
```bash
# En Hostinger, probar directamente
curl http://tudominio.com/bitacora_/src/api/google-sheets-proxy.php?action=bitacora
```

---

## Cambios que NO se necesitan

❌ **No cambiar:**
- Resto de funciones en src/index.html
- Estructura de convertSheetDataToObjects()
- Procesamiento de datos en processData()
- Funciones de UI (renderDatosGenerales, etc.)
- Estilos CSS
- Configuración de Chart.js

✅ **Solo cambiar:**
- Remover API key (líneas 967)
- Función loadDataFromGoogleSheets()
- Función loadEmergencyContactsFromSheet()

---

## FAQ

**P: ¿Qué pasa si no hago estos cambios?**
A: La aplicación seguirá funcionando, pero tu API key de Google seguirá expuesta en el cliente.

**P: ¿El proxy funcionará sin cambios en index.html?**
A: No, el proxy está disponible pero index.html seguirá usando la API key directa.

**P: ¿Se pierden datos al cambiar?**
A: No, el proxy devuelve exactamente los mismos datos que Google Sheets API.

**P: ¿Hay que hacer cambios en la BD?**
A: No, este cambio es solo en frontend.

**P: ¿Cuándo hago estos cambios?**
A: Después de crear .env y antes de deployar a producción.

