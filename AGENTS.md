# AGENTS.md - Development Guidelines for Bitácora Tracker

## Project Overview
**Bitácora Electrónica para Monitoreo de Logística** - A logistics tracking and monitoring system for TRACKER MEXICO GPS. This is a full-stack web application with a Google Sheets integration frontend and PHP backend with MySQL database support.

## Tech Stack
- **Frontend**: HTML5, Tailwind CSS, JavaScript (vanilla ES6+), Chart.js, dayjs
- **Backend**: PHP 7.4+
- **Database**: MySQL/MariaDB with mysqli
- **External APIs**: Google Sheets API v4, Make.com webhooks

---

## Build/Test/Run Commands

### Local Development
```bash
# Serve using XAMPP (already configured)
# Access via: http://localhost/bitacora_/

# No build step required - project runs directly
# Frontend: pure HTML/JS (no bundling)
# Backend: PHP interpreted at runtime
```

### Database Operations
```bash
# Apply schema updates
mysql -h [host] -u [user] -p[password] [database] < actualizar_bd_direccion.sql

# Test database connection
curl -X POST http://localhost/bitacora_/src/informe/test_conexion.php
```

### Backend Testing
```bash
# Test PHP configuration
php -l src/db/db.php
php -l src/informe/guardar_informe.php
php -l src/seguimiento/guardar_seguimiento.php

# Test endpoints (POST examples)
curl -X POST http://localhost/bitacora_/src/seguimiento/guardar_seguimiento.php \
  -H "Content-Type: application/json" \
  -d '{"folio":"123","unidad":"UNIT-001","fechaProgramada":"2025-01-12"}'

curl -X POST http://localhost/bitacora_/src/informe/guardar_informe.php \
  -H "Content-Type: application/json" \
  -d '{"titulo":"Test","datos_informe":{}}'
```

### Frontend Validation
```bash
# Check HTML validity (no build tool - files are static/inline)
# Manually validate critical files before commit
php -S localhost:8000 src/  # Serve and test locally
```

---

## Code Style Guidelines

### General Principles
- **Readability over cleverness**: Code should be understandable at first glance
- **Explicit error handling**: Never silently fail; always provide meaningful error messages
- **DRY (Don't Repeat Yourself)**: Extract reusable functions/logic
- **Type safety where possible**: Validate and cast input types explicitly

### PHP Code Style

#### Imports & Structure
```php
<?php
// Always include error reporting setup at top of endpoint files
error_reporting(0);  // For production endpoints (don't leak stack traces)
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Require database connection
require_once __DIR__ . '/../db/db.php';

// All endpoint files should be wrapped in try-catch-finally
```

#### Naming Conventions
- **Variables**: `snake_case` (e.g., `$fecha_programada`, `$real_salida_unidad`)
- **Functions**: `snake_case` (e.g., `to_mysql_date()`, `to_mysql_datetime_or_null()`)
- **Database columns**: `snake_case` (e.g., `fecha_programada`, `gps_validacion_timestamp`)
- **Constants**: `UPPER_CASE` (e.g., `MYSQLI_REPORT_OFF`)

#### Type Handling & Casting
```php
// Always explicitly cast input with type prefix
$folio = isset($data['folio']) ? (string)$data['folio'] : '';
$total = intval($data['total_despachos'] ?? 0);
$fecha = to_mysql_date($raw_fecha);  // Use helper functions

// Date/time conversion (standardize to YYYY-MM-DD and YYYY-MM-DD HH:mm:ss)
function to_mysql_date($value) {
  $s = trim((string)$value);
  if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
    return substr($s, 0, 10);
  }
  try {
    $dt = new DateTime($s);
    return $dt->format('Y-m-d');
  } catch (Exception $e) {
    return '';
  }
}
```

#### Error Handling & Validation
```php
// Always validate required fields early
if (!$titulo || !$datos_informe) {
  throw new Exception("Faltan datos requeridos: título y datos del informe");
}

// Prepared statements for ALL database queries (prevent SQL injection)
$stmt = $conn->prepare($sql);
if (!$stmt) {
  throw new Exception('Error preparando query: ' . $conn->error);
}
$stmt->bind_param('sss', $param1, $param2, $param3);

// Always handle errors and rollback transactions
try {
  $conn->begin_transaction();
  // ... operations ...
  $conn->commit();
} catch (Exception $e) {
  if (isset($conn) && $conn) {
    try { $conn->rollback(); } catch (Exception $ignored) {}
  }
  http_response_code(500);
  throw $e;
}

// Always close connections in finally block
finally {
  if (isset($conn) && $conn) {
    $conn->close();
  }
}
```

#### Response Format
```php
// All endpoints return consistent JSON responses
$response = [
  'success' => false,
  'message' => '',
  'data' => null,  // Optional: add payload here
  'id' => null     // Optional: for create/update operations
];

// Always use JSON_UNESCAPED_UNICODE for Spanish characters
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
```

### JavaScript/Frontend Code Style

#### Naming Conventions
- **Variables/functions**: `camelCase` (e.g., `filteredDespachosData`, `renderDatosGenerales()`)
- **CSS classes**: `kebab-case` (e.g., `tab-active`, `estatus-verde`)
- **IDs**: `kebab-case` (e.g., `date-filter`, `login-modal`)
- **Data attributes**: `kebab-case` (e.g., `data-label`)

#### Type Handling
```javascript
// Explicit type checking and coercion
const folio = String(row?.folio || '').trim();
const total = parseInt(data?.count ?? 0, 10);
const date = dayjs(value).isValid() ? dayjs(value).format('YYYY-MM-DD') : '';

// Use optional chaining and nullish coalescing
const cliente = data?.cliente ?? 'N/A';
const clientes = filteredData?.filter(d => d.cliente);
```

#### Formatting & Structure
```javascript
// Use arrow functions for callbacks
data.map(d => ({ ...d, processed: true }))
filteredData.forEach(item => renderItem(item))

// Template literals for complex strings
const html = `<div class="item">
  <span>${escapeHtml(item.name)}</span>
</div>`;

// Always escape HTML to prevent XSS
function escapeHtml(v) {
  return String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
```

#### Error Handling
```javascript
// Always handle async operations with try-catch or .catch()
async function loadData() {
  try {
    const response = await fetch(url);
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Failed to load data:', error);
    showErrorMessage('No se pudo cargar los datos. Intenta de nuevo.');
  }
}

// Use console for debugging, not alert() for errors in production
console.error('Error detail:', error);
alert('Usuario o contraseña incorrectos.');  // OK for login
```

#### Styling Approach
- **Use Tailwind CSS utility classes**: `class="bg-blue-600 text-white px-4 py-2 rounded-lg"`
- **Avoid inline styles**: Use Tailwind or <style> blocks instead
- **Responsive design**: Always include mobile breakpoints (md:, lg:)
- **Color scheme**: Use defined Tailwind colors (blue-600, red-600, green-600, gray-*)

#### Date/Time Handling
```javascript
// Always use dayjs for date operations
dayjs.extend(dayjs_plugin_customParseFormat);

// Parse flexible date formats
const parsed = dayjs(value, ['YYYY-MM-DD', 'DD-MM-YYYY'], true);
const formatted = parsed.isValid() ? parsed.format('DD/MM/YYYY HH:mm') : '-';

// Convert for database
function formatForInput(iso) {
  const d = dayjs(iso);
  return d.isValid() ? d.format('YYYY-MM-DDTHH:mm') : '';
}
```

---

## File Organization

```
bitacora_/
├── index.php                    # Redirect to main HTML
├── src/
│   ├── index.html              # Main application file (all-in-one)
│   ├── js/
│   │   ├── downloadKpisTabAsHTML.js
│   │   └── downloadInformeTabsHTML.js
│   ├── db/
│   │   └── db.php              # Database connection logic
│   ├── informe/
│   │   ├── guardar_informe.php
│   │   ├── obtener_informe_detalle.php
│   │   ├── obtener_informes.php
│   │   └── eliminar_informe.php
│   ├── seguimiento/
│   │   ├── guardar_seguimiento.php
│   │   └── obtener_seguimiento.php
│   └── contactos/
│       └── obtener_numeros_emergencia.php
└── actualizar_bd_direccion.sql  # Schema updates
```

---

## Common Tasks

### Adding a New Endpoint
1. Create file in appropriate subdirectory (`src/[module]/`)
2. Start with error handling and CORS headers
3. Validate all inputs with explicit type casting
4. Use prepared statements for queries
5. Return JSON with consistent `{ success, message, data }` structure
6. Wrap in try-catch-finally with proper cleanup

### Adding a New Tab/Feature
1. Add HTML structure in `src/index.html`
2. Add tab button with unique ID (`tab-btn-N`)
3. Add content div with ID (`tab-N`)
4. Add JavaScript rendering function (e.g., `renderTabN()`)
5. Update `renderAllTabs()` to call new function
6. Include in `changeTab()` permission logic if needed

### Updating Database Schema
1. Write SQL migration in `actualizar_bd_direccion.sql`
2. Update PHP endpoint to use new columns
3. Test with both INSERT and UPDATE scenarios
4. Verify JSON response structure hasn't changed

---

## Environment & Configuration

### Database Credentials (db.php)
- Uses multiple fallback configurations for Hostinger
- Supports both standard and alternative host configurations
- Never commit actual credentials; use environment variables in production

### Google Sheets API
- **Key**: Located in HTML (AIzaSyDtA5bl8XuclA92cz2f-eCswuWQul87f0I)
- **Spreadsheet ID**: 1XwjnIxq98oStetgaD5XDWpfgUhMCR1dgCzY8eVa3tiE
- **Ranges**: BITACORA!A1:O944, Contactos!A1:H
- Note: API key should be restricted in production

---

## Testing Checklist Before Commit
- [ ] PHP syntax valid: `php -l src/[file].php`
- [ ] All required fields validated in endpoints
- [ ] Database queries use prepared statements
- [ ] Error responses include meaningful messages
- [ ] JSON responses use `JSON_UNESCAPED_UNICODE`
- [ ] Frontend uses `escapeHtml()` for user input
- [ ] Responsive design tested on mobile (md: breakpoint)
- [ ] Modal closes properly (`closeModal()`)
- [ ] Date parsing handles both formats (YYYY-MM-DD, DD-MM-YYYY)

