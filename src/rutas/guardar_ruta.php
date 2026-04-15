<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/_helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo no permitido. Use POST.');
    }

    $data = rutas_get_json_body();

    $pais_origen = isset($data['pais_origen']) ? trim((string) $data['pais_origen']) : '';
    $estado_origen = isset($data['estado_origen']) ? trim((string) $data['estado_origen']) : '';
    $ciudad_origen = isset($data['ciudad_origen']) ? trim((string) $data['ciudad_origen']) : '';
    $ciudad_destino = isset($data['ciudad_destino']) ? trim((string) $data['ciudad_destino']) : '';
    $nombre_ruta = isset($data['nombre_ruta']) ? trim((string) $data['nombre_ruta']) : '';
    $descripcion = isset($data['descripcion']) ? trim((string) $data['descripcion']) : null;
    $transportista_id = isset($data['transportista_id']) && $data['transportista_id'] !== '' ? intval($data['transportista_id']) : null;
    $estado = rutas_validar_estado($data['estado'] ?? 'activa');
    $codigo_ruta = isset($data['codigo_ruta']) ? trim((string) $data['codigo_ruta']) : '';
    $secuencias = isset($data['secuencias']) ? $data['secuencias'] : [];

    if ($pais_origen === '' || $estado_origen === '' || $ciudad_origen === '' || $ciudad_destino === '' || $nombre_ruta === '') {
        throw new Exception('Faltan campos requeridos: pais_origen, estado_origen, ciudad_origen, ciudad_destino, nombre_ruta.');
    }

    $parsed = rutas_parse_secuencias($secuencias);

    $conn->begin_transaction();

    if ($codigo_ruta === '') {
        $codigo_ruta = rutas_generate_codigo($conn, $pais_origen, $ciudad_origen);
    }

    $sql_ruta = "INSERT INTO rutas_fijas (
            codigo_ruta, pais_origen, estado_origen, ciudad_origen, ciudad_destino,
            nombre_ruta, descripcion, transportista_id, estado,
            distancia_total_km, tiempo_total_minutos, numero_paradas
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt_ruta = $conn->prepare($sql_ruta);
    if (!$stmt_ruta) {
        throw new Exception('Error preparando insert de ruta: ' . $conn->error);
    }

    $stmt_ruta->bind_param(
        'sssssssisdii',
        $codigo_ruta,
        $pais_origen,
        $estado_origen,
        $ciudad_origen,
        $ciudad_destino,
        $nombre_ruta,
        $descripcion,
        $transportista_id,
        $estado,
        $parsed['total_km'],
        $parsed['total_min'],
        $parsed['numero_paradas']
    );

    if (!$stmt_ruta->execute()) {
        throw new Exception('Error ejecutando insert de ruta: ' . $stmt_ruta->error);
    }

    $ruta_id = intval($stmt_ruta->insert_id);
    $stmt_ruta->close();

    $sql_sec = "INSERT INTO secuencias_ruta (
            ruta_id, numero_secuencia, origen_municipio, destino_municipio,
            distancia_km, tiempo_estimado_minutos, notas
        ) VALUES (?,?,?,?,?,?,?)";

    $stmt_sec = $conn->prepare($sql_sec);
    if (!$stmt_sec) {
        throw new Exception('Error preparando insert de secuencias: ' . $conn->error);
    }

    foreach ($parsed['items'] as $item) {
        $stmt_sec->bind_param(
            'iissdis',
            $ruta_id,
            $item['numero_secuencia'],
            $item['origen_municipio'],
            $item['destino_municipio'],
            $item['distancia_km'],
            $item['tiempo_estimado_minutos'],
            $item['notas']
        );

        if (!$stmt_sec->execute()) {
            throw new Exception('Error insertando secuencia: ' . $stmt_sec->error);
        }
    }

    $stmt_sec->close();
    $conn->commit();

    rutas_json_response(true, 'Ruta guardada correctamente.', [
        'id' => $ruta_id,
        'codigo_ruta' => $codigo_ruta,
        'totales' => [
            'distancia_total_km' => $parsed['total_km'],
            'tiempo_total_minutos' => $parsed['total_min'],
            'numero_paradas' => $parsed['numero_paradas'],
        ],
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn) {
        try {
            $conn->rollback();
        } catch (Exception $ignored) {
        }
    }
    rutas_json_response(false, 'Error: ' . $e->getMessage(), [], 400);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
