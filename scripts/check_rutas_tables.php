<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/environment.php';

$host = getEnvVar('DB_HOST', 'localhost');
$port = getEnvInt('DB_PORT', 3306);
$user = getEnvVar('DB_USER', '');
$pass = getEnvVar('DB_PASS', '');
$db = getEnvVar('DB_NAME', '');

$conn = @new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    fwrite(STDERR, "Error de conexion: {$conn->connect_error}\n");
    exit(1);
}

$conn->set_charset('utf8mb4');

$query = "
  SELECT table_name
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name IN ('rutas_fijas', 'secuencias_ruta')
  ORDER BY table_name
";

$result = $conn->query($query);
if (!$result) {
    fwrite(STDERR, "Error consultando tablas: {$conn->error}\n");
    $conn->close();
    exit(1);
}

$found = [];
while ($row = $result->fetch_assoc()) {
    $found[] = $row['table_name'];
}
$result->free();

foreach ($found as $table_name) {
    fwrite(STDOUT, "Tabla creada: {$table_name}\n");
}

if (count($found) !== 2) {
    fwrite(STDERR, "Faltan tablas esperadas. Encontradas: " . count($found) . "\n");
    $conn->close();
    exit(1);
}

fwrite(STDOUT, "Validacion OK: rutas_fijas y secuencias_ruta existen.\n");
$conn->close();
