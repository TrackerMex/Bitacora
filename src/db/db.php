<?php
require_once __DIR__ . '/../../config/environment.php';

error_reporting(0);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

/**
 * Obtener configuración de base de datos desde variables de entorno
 * @return array[] Array con configuraciones de base de datos
 */
function getDbConfigs() {
    $configs = [];

    $configs[] = [
        'host' => getEnvVar('DB_HOST', 'localhost'),
        'port' => getEnvInt('DB_PORT', 3306),
        'user' => getEnvVar('DB_USER', ''),
        'pass' => getEnvVar('DB_PASS', ''),
        'db'   => getEnvVar('DB_NAME', '')
    ];
    
    $alt_host = getEnvVar('DB_HOST_ALT');
    if ($alt_host) {
        $configs[] = [
            'host' => $alt_host,
            'port' => getEnvInt('DB_PORT_ALT', 3306),
            'user' => getEnvVar('DB_USER_ALT', ''),
            'pass' => getEnvVar('DB_PASS_ALT', ''),
            'db'   => getEnvVar('DB_NAME_ALT', '')
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
        if (empty($config['user']) || empty($config['db'])) {
            $last_error = 'Credenciales de base de datos no configuradas';
            continue;
        }
        $conn = @new mysqli(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['db'],
            intval($config['port'] ?? 3306)
        );
        
        if (!$conn->connect_error) {
            $conn->set_charset("utf8mb4");
            return $conn;
        }
        
        $last_error = $conn->connect_error;
        $conn = null;
    }
    
    if (DEBUG) {
        throw new Exception('Error de conexión a BD: ' . $last_error);
    } else {
        throw new Exception('No se pudo conectar a la base de datos. Intente más tarde.');
    }
}

try {
    $conn = connectDatabase();
} catch (Exception $e) {
    error_log('Database connection error: ' . $e->getMessage());
    throw $e;
}

?>
