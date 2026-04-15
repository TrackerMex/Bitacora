<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/_helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo no permitido. Use GET o POST.');
    }

    $input = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : rutas_get_json_body();
    $pais_origen = isset($input['pais_origen']) ? (string) $input['pais_origen'] : '';
    $ciudad_origen = isset($input['ciudad_origen']) ? (string) $input['ciudad_origen'] : '';

    if (trim($pais_origen) === '' || trim($ciudad_origen) === '') {
        throw new Exception('Faltan campos requeridos: pais_origen y ciudad_origen.');
    }

    $codigo = rutas_generate_codigo($conn, $pais_origen, $ciudad_origen);

    rutas_json_response(true, 'Codigo generado correctamente.', [
        'codigo_ruta' => $codigo,
    ]);
} catch (Exception $e) {
    rutas_json_response(false, 'Error: ' . $e->getMessage(), [], 400);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
