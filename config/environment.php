<?php
$env_file = __DIR__ . '/../.env';

if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            
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
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    
    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    
    $env_value = getenv($key);
    if ($env_value !== false) {
        return $env_value;
    }
    
    return $default;
}

function getEnvBool($key, $default = false) {
    $value = getEnvVar($key, $default);
    return in_array($value, [true, 1, '1', 'true', 'True', 'TRUE', 'yes', 'Yes', 'YES'], true);
}

function getEnvInt($key, $default = 0) {
    return (int)(getEnvVar($key, $default));
}

define('ENVIRONMENT', getEnvVar('ENVIRONMENT', 'development'));
define('DEBUG', getEnvBool('DEBUG', false));
define('FORCE_HTTPS', getEnvBool('FORCE_HTTPS', false));
define('SESSION_SECURE_ONLY', getEnvBool('SESSION_SECURE_ONLY', true));
define('SESSION_TIMEOUT', getEnvInt('SESSION_TIMEOUT', 120));

if (FORCE_HTTPS && !empty($_SERVER['HTTP_HOST']) && empty($_SERVER['HTTPS'])) {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

?>
