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
    $ruta_id = intval($data['ruta_id'] ?? 0);
    if ($ruta_id <= 0) {
        throw new Exception('Campo requerido: ruta_id.');
    }

    $pais_origen = isset($data['pais_origen']) ? trim((string) $data['pais_origen']) : '';
    $estado_origen = isset($data['estado_origen']) ? trim((string) $data['estado_origen']) : '';
    $ciudad_origen = isset($data['ciudad_origen']) ? trim((string) $data['ciudad_origen']) : '';
    $ciudad_destino = isset($data['ciudad_destino']) ? trim((string) $data['ciudad_destino']) : '';
    $nombre_ruta = isset($data['nombre_ruta']) ? trim((string) $data['nombre_ruta']) : '';
    $descripcion = isset($data['descripcion']) ? trim((string) $data['descripcion']) : null;
    $transportista_id = isset($data['transportista_id']) && $data['transportista_id'] !== '' ? intval($data['transportista_id']) : null;
    $estado = rutas_validar_estado($data['estado'] ?? 'activa');
    $secuencias = isset($data['secuencias']) ? $data['secuencias'] : null;

    if ($pais_origen === '' || $estado_origen === '' || $ciudad_origen === '' || $ciudad_destino === '' || $nombre_ruta === '') {
        throw new Exception('Faltan campos requeridos: pais_origen, estado_origen, ciudad_origen, ciudad_destino, nombre_ruta.');
    }

    $conn->begin_transaction();

    $stmt_exists = $conn->prepare('SELECT id FROM rutas_fijas WHERE id = ? LIMIT 1');
    if (!$stmt_exists) {
        throw new Exception('Error preparando validacion de ruta: ' . $conn->error);
    }
    $stmt_exists->bind_param('i', $ruta_id);
    if (!$stmt_exists->execute()) {
        throw new Exception('Error validando ruta: ' . $stmt_exists->error);
    }
    $res_exists = $stmt_exists->get_result();
    $exists = $res_exists ? $res_exists->fetch_assoc() : null;
    $stmt_exists->close();

    if (!$exists) {
        throw new Exception('No existe la ruta indicada.');
    }

    $parsed = null;
    if (is_array($secuencias)) {
        $parsed = rutas_parse_secuencias($secuencias);
    }

    if ($parsed) {
        $sql_update = "UPDATE rutas_fijas
                       SET pais_origen = ?, estado_origen = ?, ciudad_origen = ?, ciudad_destino = ?,
                           nombre_ruta = ?, descripcion = ?, transportista_id = ?, estado = ?,
                           distancia_total_km = ?, tiempo_total_minutos = ?, numero_paradas = ?
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) {
            throw new Exception('Error preparando update de ruta: ' . $conn->error);
        }
        $stmt_update->bind_param(
            'ssssssisdiii',
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
            $parsed['numero_paradas'],
            $ruta_id
        );
        if (!$stmt_update->execute()) {
            throw new Exception('Error actualizando ruta: ' . $stmt_update->error);
        }
        $stmt_update->close();

        $stmt_del = $conn->prepare('DELETE FROM secuencias_ruta WHERE ruta_id = ?');
        if (!$stmt_del) {
            throw new Exception('Error preparando borrado de secuencias: ' . $conn->error);
        }
        $stmt_del->bind_param('i', $ruta_id);
        if (!$stmt_del->execute()) {
            throw new Exception('Error borrando secuencias: ' . $stmt_del->error);
        }
        $stmt_del->close();

        $stmt_ins = $conn->prepare(
            'INSERT INTO secuencias_ruta (ruta_id, numero_secuencia, origen_municipio, destino_municipio, distancia_km, tiempo_estimado_minutos, notas) VALUES (?,?,?,?,?,?,?)'
        );
        if (!$stmt_ins) {
            throw new Exception('Error preparando insercion de secuencias: ' . $conn->error);
        }

        foreach ($parsed['items'] as $item) {
            $stmt_ins->bind_param(
                'iissdis',
                $ruta_id,
                $item['numero_secuencia'],
                $item['origen_municipio'],
                $item['destino_municipio'],
                $item['distancia_km'],
                $item['tiempo_estimado_minutos'],
                $item['notas']
            );
            if (!$stmt_ins->execute()) {
                throw new Exception('Error insertando secuencia: ' . $stmt_ins->error);
            }
        }
        $stmt_ins->close();
    } else {
        $sql_update = "UPDATE rutas_fijas
                       SET pais_origen = ?, estado_origen = ?, ciudad_origen = ?, ciudad_destino = ?,
                           nombre_ruta = ?, descripcion = ?, transportista_id = ?, estado = ?
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        if (!$stmt_update) {
            throw new Exception('Error preparando update de ruta: ' . $conn->error);
        }
        $stmt_update->bind_param(
            'ssssssisi',
            $pais_origen,
            $estado_origen,
            $ciudad_origen,
            $ciudad_destino,
            $nombre_ruta,
            $descripcion,
            $transportista_id,
            $estado,
            $ruta_id
        );
        if (!$stmt_update->execute()) {
            throw new Exception('Error actualizando ruta: ' . $stmt_update->error);
        }
        $stmt_update->close();
    }

    $conn->commit();

    rutas_json_response(true, 'Ruta actualizada correctamente.', [
        'id' => $ruta_id,
        'secuencias_actualizadas' => $parsed ? true : false,
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
