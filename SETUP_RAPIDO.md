# GUÍA RÁPIDA: Proteger Keys y Deployar a Producción

## 🚀 TL;DR (5 minutos)

### Paso 1: Crear archivo `.env` en la raíz
```bash
# En la raíz del proyecto (C:\xampp\htdocs\bitacora_\)
# Crear archivo .env con tus credenciales reales

DB_HOST=srv1145.hstgr.io
DB_PORT=3306
DB_USER=u558294948_test
DB_PASS=tu_contraseña_real_aquí
DB_NAME=u558294948_test

GOOGLE_SHEETS_API_KEY=AIzaSyDtA5bl8XuclA92cz2f-eCswuWQul87f0I
GOOGLE_SHEETS_SPREADSHEET_ID=1XwjnIxq98oStetgaD5XDWpfgUhMCR1dgCzY8eVa3tiE

ENVIRONMENT=production
DEBUG=false
FORCE_HTTPS=true
```

### Paso 2: Crear directorios
```bash
mkdir -p config logs backups src/api
```

### Paso 3: Copiar/crear archivos de configuración
- `config/environment.php` ✅ (ya creado)
- `config/security.php` ✅ (ya creado)
- `src/api/google-sheets-proxy.php` ✅ (ya creado)
- `.htaccess` ✅ (ya creado)

### Paso 4: Actualizar `src/db/db.php`
✅ (ya actualizado para usar variables de entorno)

### Paso 5: Actualizar `src/index.html`
Cambiar la carga de datos de Google Sheets para usar el proxy:

```javascript
// ❌ ANTES (expone API key)
async function loadDataFromGoogleSheets(isInitialLoad = true) {
    const url = `https://sheets.googleapis.com/v4/spreadsheets/${SPREADSHEET_ID}/values/${RANGE}?key=${API_KEY}`;
    const response = await fetch(url);
    // ...
}

// ✅ DESPUÉS (usa proxy seguro)
async function loadDataFromGoogleSheets(isInitialLoad = true) {
    const url = '/bitacora_/src/api/google-sheets-proxy.php?action=bitacora';
    const response = await fetch(url);
    const apiResponse = await response.json();
    const values = apiResponse.data || [];  // ← Obtener datos del proxy
    const jsonData = convertSheetDataToObjects(values);
    processData(jsonData);
}
```

### Paso 6: Git - Verificar que .env no se comitea
```bash
git status
# El archivo .env NO debe aparecer aquí
# Si aparece, ejecutar:
git rm --cached .env
git commit -m "Remove .env from tracking"
```

---

## 📁 Estructura Final

```
bitacora_/
├── .env                 ← Credenciales (NO en git)
├── .env.example         ← Template (EN git)
├── .gitignore           ← Ignore rules (EN git)
├── .htaccess            ← Seguridad del servidor (EN git)
├── index.php
├── config/
│   ├── environment.php  ← Cargar variables de entorno
│   └── security.php     ← Utilidades de seguridad
├── logs/                ← Logs de aplicación (NO en git)
├── backups/             ← Backups de BD (NO en git)
├── src/
│   ├── api/
│   │   └── google-sheets-proxy.php  ← Proxy seguro para Google Sheets
│   ├── db/
│   │   └── db.php       ← Ahora usa variables de entorno
│   ├── index.html       ← Usa proxy en vez de API key directa
│   ├── js/
│   ├── informe/
│   └── seguimiento/
└── PRODUCCION.md        ← Guía completa
```

---

## 🔐 Qué cambia en producción

### ANTES (desarrollo - INSEGURO)
```javascript
// API key hardcodeada en JavaScript
const API_KEY = "AIzaSyDtA5bl8XuclA92cz2f-eCswuWQul87f0I";

// Credenciales en el código PHP
$configs = [[
    'user' => 'u558294948_test',
    'pass' => '=L~enk:7gH'  // EXPUESTO EN REPOSITORIO
]];
```

### DESPUÉS (producción - SEGURO)
```javascript
// Obtener datos a través de proxy PHP
fetch('/bitacora_/src/api/google-sheets-proxy.php?action=bitacora')
    .then(r => r.json())
    .then(data => processData(data.data));
```

```php
// Credenciales desde variables de entorno
$db_user = getEnv('DB_USER');  // Del archivo .env
$api_key = getEnv('GOOGLE_SHEETS_API_KEY');  // Del archivo .env
```

---

## ✅ Checklist de deployment

- [ ] Crear archivo `.env` con credenciales reales
- [ ] Crear directorios: `config`, `logs`, `backups`, `src/api`
- [ ] Copiar archivos: `config/environment.php`, `config/security.php`
- [ ] Crear `src/api/google-sheets-proxy.php` 
- [ ] Actualizar `src/db/db.php` (ya hecho)
- [ ] Actualizar `src/index.html` para usar proxy
- [ ] Crear `.htaccess` (ya hecho)
- [ ] Crear `.gitignore` (ya hecho)
- [ ] Verificar: `git status` no muestra `.env`
- [ ] En servidor: `chmod 600 .env`
- [ ] En servidor: `chmod 755 config logs backups src/api`
- [ ] Verificar: `php -l src/db/db.php` sin errores
- [ ] Test: `curl http://localhost/bitacora_/src/api/google-sheets-proxy.php?action=bitacora`
- [ ] Revisar PRODUCCION.md para detalles

---

## 🔑 Dónde guardar credenciales reales

**NUNCA** en git, código o documentos públicos. Usar:

- **1Password / LastPass / Bitwarden** - Gestor de contraseñas
- **Variables de entorno del servidor** - En Hostinger: cPanel env o SSH
- **Archivo .env en servidor** - Solo accesible al proceso PHP
- **Vault centralizado** - Para equipos grandes

Ejemplo Hostinger/cPanel:
```
Herramientas > Variables de entorno
O vía SSH:
    echo "export DB_PASS=tu_contraseña" >> ~/.bashrc
```

---

## 🛠️ Testear antes de deployar

```bash
# 1. Sintaxis PHP
php -l src/db/db.php
php -l src/api/google-sheets-proxy.php
php -l config/environment.php

# 2. Cargar .env correctamente
php -r "require 'config/environment.php'; echo getEnv('DB_HOST');"

# 3. Conectar a BD
php -r "require 'src/db/db.php'; echo 'BD OK';"

# 4. Testear proxy
curl http://localhost/bitacora_/src/api/google-sheets-proxy.php?action=bitacora
```

---

## 📞 Contacto con soporte

Si hay problemas en producción:

1. **Revisar logs**: `tail logs/error.log`
2. **Verificar `.env`**: `cat .env | head` (sin mostrar valores)
3. **Verificar permisos**: `ls -l .env` (debe ser `-rw-------`)
4. **Testear conexión**: `php src/db/db.php` (debe conectar)
5. **Contactar Hostinger** con detalles específicos del error

