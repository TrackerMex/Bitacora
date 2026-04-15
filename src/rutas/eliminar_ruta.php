<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/_helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        throw new Exception('Metodo no permitido. Use POST o DELETE.');
    }

    $data = rutas_get_json_body();
    $ruta_id = intval($data['ruta_id'] ?? ($_GET['ruta_id'] ?? 0));
    if ($ruta_id <= 0) {
        throw new Exception('Campo requerido: ruta_id.');
    }

    $stmt = $conn->prepare('DELETE FROM rutas_fijas WHERE id = ?');
    if (!$stmt) {
        throw new Exception('Error preparando delete: ' . $conn->error);
    }
    $stmt->bind_param('i', $ruta_id);
    if (!$stmt->execute()) {
        throw new Exception('Error eliminando ruta: ' . $stmt->error);
    }

    $affected = intval($stmt->affected_rows);
    $stmt->close();

    if ($affected <= 0) {
        rutas_json_response(false, 'No existe la ruta indicada.', ['id' => $ruta_id], 404);
    }

    rutas_json_response(true, 'Ruta eliminada correctamente.', [
        'id' => $ruta_id,
        'deleted' => true,
    ]);
} catch (Exception $e) {
    rutas_json_response(false, 'Error: ' . $e->getMessage(), [], 400);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
