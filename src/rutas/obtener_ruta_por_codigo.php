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

    $codigo = isset($_GET['codigo']) ? trim((string) $_GET['codigo']) : '';
    if ($codigo === '') {
        throw new Exception('Parametro requerido: codigo.');
    }

    $sql_ruta = "SELECT
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
                 WHERE codigo_ruta = ?
                 LIMIT 1";

    $stmt_ruta = $conn->prepare($sql_ruta);
    if (!$stmt_ruta) {
        throw new Exception('Error preparando consulta de ruta: ' . $conn->error);
    }
    $stmt_ruta->bind_param('s', $codigo);
    if (!$stmt_ruta->execute()) {
        throw new Exception('Error ejecutando consulta de ruta: ' . $stmt_ruta->error);
    }
    $res_ruta = $stmt_ruta->get_result();
    $ruta = $res_ruta ? $res_ruta->fetch_assoc() : null;
    $stmt_ruta->close();

    if (!$ruta) {
        rutas_json_response(false, 'No existe una ruta con ese codigo.', [
            'data' => null,
            'secuencias' => [],
        ], 404);
    }

    $ruta_id = intval($ruta['id']);

    $sql_sec = "SELECT
                  id,
                  ruta_id,
                  numero_secuencia,
                  origen_municipio,
                  destino_municipio,
                  distancia_km,
                  tiempo_estimado_minutos,
                  notas,
                  fecha_creacion
                FROM secuencias_ruta
                WHERE ruta_id = ?
                ORDER BY numero_secuencia ASC";
    $stmt_sec = $conn->prepare($sql_sec);
    if (!$stmt_sec) {
        throw new Exception('Error preparando consulta de secuencias: ' . $conn->error);
    }
    $stmt_sec->bind_param('i', $ruta_id);
    if (!$stmt_sec->execute()) {
        throw new Exception('Error ejecutando consulta de secuencias: ' . $stmt_sec->error);
    }

    $res_sec = $stmt_sec->get_result();
    $secuencias = [];
    while ($row = $res_sec->fetch_assoc()) {
        $secuencias[] = $row;
    }
    $stmt_sec->close();

    rutas_json_response(true, 'Ruta obtenida correctamente.', [
        'data' => $ruta,
        'secuencias' => $secuencias,
        'count' => count($secuencias),
    ]);
} catch (Exception $e) {
    rutas_json_response(false, 'Error: ' . $e->getMessage(), [
        'data' => null,
        'secuencias' => [],
        'count' => 0,
    ], 400);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
