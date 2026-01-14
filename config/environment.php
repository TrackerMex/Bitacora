<?php
/**
 * config/environment.php
 * 
 * Carga variables de entorno desde .env
 * En producción, puede usar variables de entorno del servidor (recomendado)
 */

// Ruta del archivo .env (buscar en el directorio padre)
$env_file = __DIR__ . '/../.env';

// Si existe .env, cargarlo
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parsear KEY=VALUE
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remover comillas si existen
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
            // Solo establecer si no existe en $_ENV (prioriza variables del servidor)
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
}

/**
 * Obtener valor de variable de entorno con fallback
 * @param string $key Nombre de la variable
 * @param mixed $default Valor por defecto
 * @return mixed Valor de la variable o default
 */
function getEnvVar($key, $default = null) {
    // Chequear $_ENV primero
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    
    // Luego $_SERVER
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    
    // Finalmente getenv()
    $env_value = getenv($key);
    if ($env_value !== false) {
        return $env_value;
    }
    
    // Default
    return $default;
}

/**
 * Obtener variable como booleano
 */
function getEnvBool($key, $default = false) {
    $value = getEnvVar($key, $default);
    return in_array($value, [true, 1, '1', 'true', 'True', 'TRUE', 'yes', 'Yes', 'YES'], true);
}

/**
 * Obtener variable como número
 */
function getEnvInt($key, $default = 0) {
    return (int)(getEnvVar($key, $default));
}

// Configuración global de seguridad
define('ENVIRONMENT', getEnvVar('ENVIRONMENT', 'development'));
define('DEBUG', getEnvBool('DEBUG', false));
define('FORCE_HTTPS', getEnvBool('FORCE_HTTPS', false));
define('SESSION_SECURE_ONLY', getEnvBool('SESSION_SECURE_ONLY', true));
define('SESSION_TIMEOUT', getEnvInt('SESSION_TIMEOUT', 120));

// Forzar HTTPS en producción (solo en contexto web)
if (FORCE_HTTPS && !empty($_SERVER['HTTP_HOST']) && empty($_SERVER['HTTPS'])) {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

?>
