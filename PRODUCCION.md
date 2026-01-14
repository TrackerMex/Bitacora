# PRODUCCIÓN - Guía Completa de Configuración

## 📋 Índice
1. [Preparación pre-deployment](#preparación-pre-deployment)
2. [Protección de credenciales](#protección-de-credenciales)
3. [Estructura de directorios](#estructura-de-directorios)
4. [Variables de entorno](#variables-de-entorno)
5. [Seguridad](#seguridad)
6. [Deploy en Hostinger](#deploy-en-hostinger)
7. [Monitoreo y logging](#monitoreo-y-logging)
8. [Backup y recuperación](#backup-y-recuperación)

---

## Preparación pre-deployment

### 1. Crear archivo `.env` en la raíz
```bash
# Copia el template y reemplaza valores reales
cp .env.example .env
```

**NUNCA commits el archivo .env**. Ya está en `.gitignore`.

### 2. Estructura de directorios requerida
```
bitacora_/
├── config/              ← Nuevo: archivos de configuración
│   └── environment.php
├── logs/                ← Nuevo: directorio de logs
├── backups/             ← Nuevo: backups de BD
├── src/
│   ├── api/             ← Nuevo: endpoints proxy seguros
│   │   └── google-sheets-proxy.php
│   ├── db/
│   │   └── db.php       ← Modificado: usa variables de entorno
│   ├── index.html
│   ├── js/
│   ├── informe/
│   └── seguimiento/
├── .env                 ← Nuevo: variables de entorno (NO commitear)
├── .env.example         ← Nuevo: template
├── .gitignore           ← Nuevo: archivos a ignorar
└── index.php
```

### 3. Crear directorios necesarios
```bash
mkdir -p config logs backups src/api
chmod 755 logs backups
chmod 600 .env  # Solo lectura para el propietario
```

---

## Protección de credenciales

### ❌ QUÉ NO HACER (Actual)

```javascript
// ❌ EXPOSICIÓN CRÍTICA: API key en el cliente
const API_KEY = "AIzaSyDtA5bl8XuclA92cz2f-eCswuWQul87f0I";
const SPREADSHEET_ID = "1XwjnIxq98oStetgaD5XDWpfgUhMCR1dgCzY8eVa3tiE";

// ❌ CREDENCIALES HARDCODEADAS EN PHP
$configs = [
    [
        'user' => 'u558294948_test',
        'pass' => '=L~enk:7gH',  // ¡EXPUESTO!
    ]
];
```

### ✅ QUÉ HACER (Producción)

**A. Variables de entorno (.env)**
```env
DB_HOST=srv1145.hstgr.io
DB_USER=u558294948_test
DB_PASS=tu_contraseña_real_aquí
GOOGLE_SHEETS_API_KEY=AIzaSyDtA5bl8XuclA92cz2f-eCswuWQul87f0I
GOOGLE_SHEETS_SPREADSHEET_ID=1XwjnIxq98oStetgaD5XDWpfgUhMCR1dgCzY8eVa3tiE
```

**B. Cargar en PHP (src/db/db.php)**
```php
require_once __DIR__ . '/../../config/environment.php';

$db_host = getEnv('DB_HOST');
$db_user = getEnv('DB_USER');
$db_pass = getEnv('DB_PASS');
```

**C. Frontend usa proxy PHP (NO API key directa)**
```javascript
// ✅ SEGURO: proxy PHP en el backend
async function loadDataFromGoogleSheets() {
    const response = await fetch('/bitacora_/src/api/google-sheets-proxy.php?action=bitacora');
    const data = await response.json();
    return data.data;
}
```

---

## Variables de entorno

### Configuración de desarrollo (.env.local)
```env
ENVIRONMENT=development
DEBUG=true
FORCE_HTTPS=false
SESSION_TIMEOUT=120
```

### Configuración de producción (.env en servidor)
```env
ENVIRONMENT=production
DEBUG=false
FORCE_HTTPS=true
SESSION_SECURE_ONLY=true
SESSION_TIMEOUT=60

# Base de datos
DB_HOST=srv1145.hstgr.io
DB_USER=u558294948_test
DB_PASS=[contraseña_segura]
DB_NAME=u558294948_test

# Google Sheets
GOOGLE_SHEETS_API_KEY=[tu_api_key]
GOOGLE_SHEETS_SPREADSHEET_ID=[tu_spreadsheet_id]

# CORS
ALLOWED_ORIGINS=https://tudominio.com,https://www.tudominio.com

# Make.com webhook
MAKE_WEBHOOK_URL=https://hook.us2.make.com/...
MAKE_WEBHOOK_ENABLED=true
```

---

## Seguridad

### 1. Proteger archivo .env
```bash
# Asegurar que solo el usuario del servidor puede leerlo
chmod 600 /var/www/html/bitacora_/.env
chown www-data:www-data /var/www/html/bitacora_/.env
```

### 2. Validar variables de entorno
```php
// En config/environment.php o al iniciar la aplicación
function validateEnvironment() {
    $required = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
    foreach ($required as $key) {
        if (!getEnv($key)) {
            throw new Exception("Variable de entorno requerida no configurada: $key");
        }
    }
}
validateEnvironment();
```

### 3. HTTPS obligatorio
```php
// config/environment.php
if (FORCE_HTTPS && empty($_SERVER['HTTPS'])) {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}
```

### 4. Headers de seguridad
```php
// Agregar al principio de src/index.html o con .htaccess

// Prevenir XSS
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com;");
```

### 5. CORS restringido
```php
// src/db/db.php o config/security.php
function setCORSHeaders() {
    $allowed = explode(',', getEnv('ALLOWED_ORIGINS', 'http://localhost'));
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowed)) {
        header("Access-Control-Allow-Origin: $origin");
    }
    
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 3600');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    setCORSHeaders();
    exit();
}
```

---

## Deploy en Hostinger

### 1. Preparar archivos locales
```bash
# Asegurar que .env y directorios sensibles NO están en git
git status  # Verificar que .env no aparece

# Crear archivo .env en el servidor (NUNCA por git)
# Hacerlo manualmente en el cpanel/SSH
```

### 2. Via SSH (Hostinger)

```bash
# Conectar al servidor
ssh user@servidor

# Navegar al directorio
cd /home/u558294948/public_html/bitacora_

# Crear archivo .env (solo credenciales, NO en git)
nano .env
# Pegar contenido con contraseña real

# Asegurar permisos
chmod 600 .env
chmod 755 config logs backups src/api

# Crear directorios si no existen
mkdir -p config logs backups src/api

# Verificar PHP version
php -v

# Test de PHP
php -l src/db/db.php
php config/environment.php  # Verificar que carga sin errores
```

### 3. Via cPanel File Manager

1. **Subir archivos**
   - Subir todo EXCEPTO `.env`
   - Crear directorios: `config`, `logs`, `backups`, `src/api`

2. **Crear archivo .env**
   - cPanel > File Manager > New File > Nombre: `.env`
   - Editar y pegar variables de producción
   - Cambiar permisos a 600 (clic derecho > Change Permissions)

3. **Crear archivo .htaccess** (seguridad adicional)
```apache
# .htaccess en la raíz del proyecto
<Files .env>
    Deny from all
</Files>

<Files .git>
    Deny from all
</Files>

<Files .gitignore>
    Deny from all
</Files>

# Forzar HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## Monitoreo y logging

### 1. Crear logger
```php
// config/logger.php
function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $log_file = __DIR__ . '/../logs/error.log';
    
    $log_entry = "[$timestamp] $message";
    if (!empty($context)) {
        $log_entry .= "\n  Context: " . json_encode($context);
    }
    $log_entry .= "\n";
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

function logActivity($action, $user = 'system', $details = '') {
    $timestamp = date('Y-m-d H:i:s');
    $log_file = __DIR__ . '/../logs/activity.log';
    
    $log_entry = "[$timestamp] $action | User: $user | Details: $details\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}
```

### 2. Usar en endpoints
```php
// src/seguimiento/guardar_seguimiento.php
try {
    // ... código ...
    logActivity('seguimiento_saved', $operadorMonitoreo, "Folio: $folio");
} catch (Exception $e) {
    logError('Error guardando seguimiento', [
        'folio' => $folio,
        'error' => $e->getMessage()
    ]);
}
```

### 3. Rotación de logs
```bash
# Crear cron job en Hostinger
# Cada semana, comprimir logs antiguos
0 2 * * 0 gzip /home/u558294948/public_html/bitacora_/logs/error.log-*
```

---

## Backup y recuperación

### 1. Script de backup automático
```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/home/u558294948/backups/bitacora"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="u558294948_test"

mkdir -p $BACKUP_DIR

# Backup de BD
mysqldump -h srv1145.hstgr.io -u u558294948_test -p$DB_PASS $DB_NAME > \
    $BACKUP_DIR/db_backup_$DATE.sql

# Backup de archivos
tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz \
    /home/u558294948/public_html/bitacora_ \
    --exclude=.git --exclude=logs --exclude=node_modules

# Eliminar backups más antiguos de 30 días
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
```

### 2. Agendar backup (Hostinger cPanel)
```
Cron Jobs:
0 3 * * * /home/u558294948/backup.sh
# Se ejecuta a las 3 AM diariamente
```

### 3. Recuperar de backup
```bash
# Restaurar base de datos
mysql -h srv1145.hstgr.io -u u558294948_test -p$DB_PASS $DB_NAME < backup.sql

# Restaurar archivos
tar -xzf files_backup_*.tar.gz -C /
```

---

## Checklist de deployment

- [ ] Crear archivo `.env` con credenciales reales
- [ ] Verificar permiso 600 en `.env`
- [ ] Crear directorios: `config`, `logs`, `backups`, `src/api`
- [ ] Subir archivo `config/environment.php`
- [ ] Subir archivo `src/api/google-sheets-proxy.php`
- [ ] Modificar `src/db/db.php` para usar variables de entorno
- [ ] Crear archivo `.htaccess` para proteger archivos sensibles
- [ ] Verificar que `.env` NO está en git (en .gitignore)
- [ ] Test de conexión a BD: `php -l src/db/db.php`
- [ ] Test de Google Sheets proxy: `curl http://dominio/bitacora_/src/api/google-sheets-proxy.php?action=bitacora`
- [ ] Configurar backups automáticos
- [ ] Habilitar HTTPS/SSL
- [ ] Revisar logs regularmente
- [ ] Documentar credenciales en lugar seguro (1Password, LastPass, etc.)

---

## Referencias adicionales

- [Mejores prácticas de seguridad PHP](https://owasp.org/www-community/attacks/sql_injection)
- [Google Sheets API - Seguridad](https://developers.google.com/sheets/api/guides/authorizing)
- [HTTPS en PHP](https://www.php.net/manual/en/reserved.variables.server.php)
- [cPanel/Hostinger Docs](https://www.hostinger.com/help/article/cpanel-file-manager)

