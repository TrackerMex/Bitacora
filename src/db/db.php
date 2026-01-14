<?php
/**
 * src/db/db.php
 * 
 * Conexión a base de datos usando variables de entorno
 * Manejo seguro de credenciales
 */

// Cargar configuración de entorno
require_once __DIR__ . '/../config/environment.php';

// Configuración de errores
error_reporting(0);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

/**
 * Obtener configuración de base de datos desde variables de entorno
 * @return array[] Array con configuraciones de base de datos
 */
function getDbConfigs() {
    $configs = [];
    
    // Configuración principal
    $configs[] = [
        'host' => getEnv('DB_HOST', 'localhost'),
        'port' => getEnvInt('DB_PORT', 3306),
        'user' => getEnv('DB_USER', ''),
        'pass' => getEnv('DB_PASS', ''),
        'db'   => getEnv('DB_NAME', '')
    ];
    
    // Configuración alternativa (fallback)
    $alt_host = getEnv('DB_HOST_ALT');
    if ($alt_host) {
        $configs[] = [
            'host' => $alt_host,
            'port' => getEnvInt('DB_PORT_ALT', 3306),
            'user' => getEnv('DB_USER_ALT', ''),
            'pass' => getEnv('DB_PASS_ALT', ''),
            'db'   => getEnv('DB_NAME_ALT', '')
        ];
    }
    
    return $configs;
}

/**
 * Conectar a la base de datos con reintentos
 * @throws Exception Si no se puede conectar
 * @return mysqli Conexión a la base de datos
 */
function connectDatabase() {
    $configs = getDbConfigs();
    $conn = null;
    $last_error = '';
    
    foreach ($configs as $config) {
        // Validar que tenga credenciales
        if (empty($config['user']) || empty($config['db'])) {
            $last_error = 'Credenciales de base de datos no configuradas';
            continue;
        }
        
        // Intentar conexión
        $conn = @new mysqli(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['db'],
            intval($config['port'] ?? 3306)
        );
        
        if (!$conn->connect_error) {
            // Conexión exitosa
            $conn->set_charset("utf8mb4");
            return $conn;
        }
        
        $last_error = $conn->connect_error;
        $conn = null;
    }
    
    // Si no se pudo conectar, mostrar error genérico en producción
    if (DEBUG) {
        throw new Exception('Error de conexión a BD: ' . $last_error);
    } else {
        throw new Exception('No se pudo conectar a la base de datos. Intente más tarde.');
    }
}

// Establecer conexión global
try {
    $conn = connectDatabase();
} catch (Exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    throw $e;
}

?>
