<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($argc < 2) {
    fwrite(STDERR, "Uso: php scripts/run_sql_migration.php <archivo_sql>\n");
    exit(1);
}

$sql_file = $argv[1];
if (!preg_match('/^[A-Za-z]:\\\\|^\//', $sql_file)) {
    $sql_file = __DIR__ . '/../' . ltrim($sql_file, '/\\');
}

if (!file_exists($sql_file)) {
    fwrite(STDERR, "No existe el archivo SQL: {$sql_file}\n");
    exit(1);
}

require_once __DIR__ . '/../config/environment.php';

$host = getEnvVar('DB_HOST', 'localhost');
$port = getEnvInt('DB_PORT', 3306);
$user = getEnvVar('DB_USER', '');
$pass = getEnvVar('DB_PASS', '');
$db = getEnvVar('DB_NAME', '');

if ($user === '' || $db === '') {
    fwrite(STDERR, "Credenciales incompletas en .env (DB_USER/DB_NAME)\n");
    exit(1);
}

$conn = @new mysqli($host, $user, $pass, $db, $port);
if ($conn->connect_error) {
    fwrite(STDERR, "Error de conexion: {$conn->connect_error}\n");
    exit(1);
}

$conn->set_charset('utf8mb4');

$sql = file_get_contents($sql_file);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Archivo SQL vacio o no legible: {$sql_file}\n");
    $conn->close();
    exit(1);
}

if (!$conn->multi_query($sql)) {
    fwrite(STDERR, "Error al ejecutar migracion: {$conn->error}\n");
    $conn->close();
    exit(1);
}

do {
    if ($result = $conn->store_result()) {
        $result->free();
    }
} while ($conn->more_results() && $conn->next_result());

if ($conn->error) {
    fwrite(STDERR, "Migracion ejecutada con errores: {$conn->error}\n");
    $conn->close();
    exit(1);
}

fwrite(STDOUT, "Migracion aplicada correctamente: {$sql_file}\n");
$conn->close();
exit(0);
