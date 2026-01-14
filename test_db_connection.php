<?php
/**
 * test_db_connection.php
 * Script de prueba para verificar conexión a BD y variables de entorno
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

echo "<h2>Test de Conexión a Base de Datos</h2>";
echo "<hr>";

// 1. Verificar que el archivo .env existe
echo "<h3>1. Verificar archivo .env</h3>";
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    echo "✓ Archivo .env encontrado<br>";
} else {
    echo "✗ Archivo .env NO encontrado en: " . $env_file . "<br>";
}

// 2. Cargar variables de entorno
echo "<h3>2. Cargar variables de entorno</h3>";
require_once __DIR__ . '/config/environment.php';

// 3. Mostrar variables cargadas (sin mostrar contraseña completa)
echo "<h3>3. Variables de entorno cargadas</h3>";
echo "<pre>";
echo "DB_HOST: " . getEnvVar('DB_HOST', 'NO CONFIGURADO') . "\n";
echo "DB_PORT: " . getEnvVar('DB_PORT', 'NO CONFIGURADO') . "\n";
echo "DB_USER: " . getEnvVar('DB_USER', 'NO CONFIGURADO') . "\n";
echo "DB_PASS: " . (getEnvVar('DB_PASS') ? '***CONFIGURADO***' : 'NO CONFIGURADO') . "\n";
echo "DB_NAME: " . getEnvVar('DB_NAME', 'NO CONFIGURADO') . "\n";
echo "ENVIRONMENT: " . ENVIRONMENT . "\n";
echo "DEBUG: " . (DEBUG ? 'true' : 'false') . "\n";
echo "</pre>";

// 4. Intentar conectar directamente (sin usar db.php que oculta errores)
echo "<h3>4. Intentar conexión a Base de Datos</h3>";

$db_host = getEnvVar('DB_HOST');
$db_port = getEnvVar('DB_PORT', 3306);
$db_user = getEnvVar('DB_USER');
$db_pass = getEnvVar('DB_PASS');
$db_name = getEnvVar('DB_NAME');

echo "Intentando conectar a: " . $db_host . ":" . $db_port . " con usuario: " . $db_user . "<br><br>";

// Habilitar reporte de errores para ver el error real
mysqli_report(MYSQLI_REPORT_ALL);

try {
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, intval($db_port));
    
    if ($conn->connect_error) {
        echo "✗ <strong>ERROR DE CONEXIÓN</strong><br>";
        echo "Código de error: " . $conn->connect_errno . "<br>";
        echo "Mensaje: " . htmlspecialchars($conn->connect_error) . "<br><br>";
    } else {
        echo "✓ <strong>CONEXIÓN EXITOSA</strong><br>";
        $conn->set_charset("utf8mb4");
        echo "Host: " . $conn->get_server_info() . "<br>";
        
        $db_result = $conn->query("SELECT DATABASE()");
        if ($db_result) {
            $row = $db_result->fetch_row();
            echo "Database: " . $row[0] . "<br>";
        }
        
        // Probar una query simple
        $result = $conn->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = DATABASE()");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "Tablas en BD: " . $row['table_count'] . "<br>";
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "✗ <strong>EXCEPCIÓN</strong><br>";
    echo "Error: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "<hr>";
echo "<p>Si ves 'CONEXIÓN EXITOSA', todo está configurado correctamente.</p>";
?>
