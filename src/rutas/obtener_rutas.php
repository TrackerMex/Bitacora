<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/_helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Metodo no permitido. Use GET.');
    }

    $estado = isset($_GET['estado']) ? trim((string) $_GET['estado']) : '';
    $search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

    $sql = "SELECT
              id,
              codigo_ruta,
              pais_origen,
              estado_origen,
              ciudad_origen,
              ciudad_destino,
              nombre_ruta,
              descripcion,
              transportista_id,
              estado,
              distancia_total_km,
              tiempo_total_minutos,
              numero_paradas,
              fecha_creacion,
              fecha_actualizacion
            FROM rutas_fijas
            WHERE 1=1";

    $types = '';
    $params = [];

    if ($estado !== '') {
        $sql .= ' AND estado = ?';
        $types .= 's';
        $params[] = $estado;
    }

    if ($search !== '') {
        $sql .= ' AND (codigo_ruta LIKE ? OR nombre_ruta LIKE ? OR ciudad_origen LIKE ? OR ciudad_destino LIKE ?)';
        $like = '%' . $search . '%';
        $types .= 'ssss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= ' ORDER BY fecha_actualizacion DESC, id DESC';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

    if ($types !== '') {
        $bind_args = [];
        $bind_args[] = $types;
        for ($i = 0; $i < count($params); $i++) {
            $bind_args[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_args);
    }

    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    rutas_json_response(true, 'Rutas obtenidas correctamente.', [
        'data' => $rows,
        'count' => count($rows),
    ]);
} catch (Exception $e) {
    rutas_json_response(false, 'Error: ' . $e->getMessage(), ['data' => [], 'count' => 0], 400);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
