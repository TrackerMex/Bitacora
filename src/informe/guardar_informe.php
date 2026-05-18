<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once __DIR__ . '/../db/db.php';
    require_once __DIR__ . '/informe_auth.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Metodo no permitido. Use POST.', 405);
    }

    $auth_user = informes_get_auth_user($conn, true);
    $has_created_by = informes_column_exists($conn, 'created_by_usuario_id');

    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    if (!$data) {
        $data = $_POST;
    }

    $titulo = $data['titulo'] ?? null;
    $datos_informe = $data['datos_informe'] ?? null;
    if (!$titulo || !$datos_informe) {
        throw new Exception('Faltan datos requeridos: titulo y datos del informe son obligatorios', 400);
    }

    $raw_fecha_despacho = $data['fecha_despacho'] ?? date('Y-m-d');
    $fecha_despacho = (function ($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return date('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{2})$/', $value, $m)) {
            return '20' . $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return $value;
    })($raw_fecha_despacho);

    $total_despachos = intval($data['total_despachos'] ?? 0);
    $a_tiempo = intval($data['a_tiempo'] ?? 0);
    $con_retraso = intval($data['con_retraso'] ?? 0);
    $en_ruta = intval($data['en_ruta'] ?? 0);
    $programados = intval($data['programados'] ?? 0);
    $total_incidencias = intval($data['total_incidencias'] ?? 0);
    $operador_monitoreo = $data['operador_monitoreo'] ?? 'Desconocido';
    $cliente_id = isset($data['cliente_id']) && intval($data['cliente_id']) > 0
        ? intval($data['cliente_id'])
        : null;

    if (is_array($datos_informe)) {
        $datos_informe = json_encode($datos_informe, JSON_UNESCAPED_UNICODE);
    }

    $conn->begin_transaction();

    if ($has_created_by) {
        $selectSql = $cliente_id === null
            ? 'SELECT id FROM informes_guardados WHERE fecha_despacho = ? AND cliente_id IS NULL AND created_by_usuario_id = ? ORDER BY id DESC LIMIT 1'
            : 'SELECT id FROM informes_guardados WHERE fecha_despacho = ? AND cliente_id = ? AND created_by_usuario_id = ? ORDER BY id DESC LIMIT 1';
    } else {
        $selectSql = $cliente_id === null
            ? 'SELECT id FROM informes_guardados WHERE fecha_despacho = ? AND cliente_id IS NULL ORDER BY id DESC LIMIT 1'
            : 'SELECT id FROM informes_guardados WHERE fecha_despacho = ? AND cliente_id = ? ORDER BY id DESC LIMIT 1';
    }

    $selectStmt = $conn->prepare($selectSql);
    if (!$selectStmt) {
        throw new Exception('Error preparando select: ' . $conn->error, 500);
    }
    if ($has_created_by && $cliente_id === null) {
        $selectStmt->bind_param('si', $fecha_despacho, $auth_user['id']);
    } elseif ($has_created_by) {
        $selectStmt->bind_param('sii', $fecha_despacho, $cliente_id, $auth_user['id']);
    } elseif ($cliente_id === null) {
        $selectStmt->bind_param('s', $fecha_despacho);
    } else {
        $selectStmt->bind_param('si', $fecha_despacho, $cliente_id);
    }
    if (!$selectStmt->execute()) {
        throw new Exception('Error ejecutando select: ' . $selectStmt->error, 500);
    }
    $existingId = null;
    $selectResult = $selectStmt->get_result();
    if ($selectResult && ($row = $selectResult->fetch_assoc())) {
        $existingId = intval($row['id']);
    }
    $selectStmt->close();

    if ($existingId) {
        if ($has_created_by) {
            $updateSql = 'UPDATE informes_guardados
                SET cliente_id = ?, created_by_usuario_id = ?, titulo = ?, total_despachos = ?, a_tiempo = ?, con_retraso = ?, en_ruta = ?, programados = ?, total_incidencias = ?, datos_informe = ?, operador_monitoreo = ?
                WHERE id = ?';
        } else {
            $updateSql = 'UPDATE informes_guardados
                SET cliente_id = ?, titulo = ?, total_despachos = ?, a_tiempo = ?, con_retraso = ?, en_ruta = ?, programados = ?, total_incidencias = ?, datos_informe = ?, operador_monitoreo = ?
                WHERE id = ?';
        }
        $updateStmt = $conn->prepare($updateSql);
        if (!$updateStmt) {
            throw new Exception('Error preparando update: ' . $conn->error, 500);
        }

        if ($has_created_by) {
            $updateStmt->bind_param(
                'iisiiiiiissi',
                $cliente_id,
                $auth_user['id'],
                $titulo,
                $total_despachos,
                $a_tiempo,
                $con_retraso,
                $en_ruta,
                $programados,
                $total_incidencias,
                $datos_informe,
                $operador_monitoreo,
                $existingId
            );
        } else {
            $updateStmt->bind_param(
                'isiiiiiissi',
                $cliente_id,
                $titulo,
                $total_despachos,
                $a_tiempo,
                $con_retraso,
                $en_ruta,
                $programados,
                $total_incidencias,
                $datos_informe,
                $operador_monitoreo,
                $existingId
            );
        }
        if (!$updateStmt->execute()) {
            throw new Exception('Error ejecutando update: ' . $updateStmt->error, 500);
        }
        $updateStmt->close();
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Informe actualizado correctamente',
            'action' => 'updated',
            'id' => $existingId,
            'fecha_despacho' => $fecha_despacho
        ], JSON_UNESCAPED_UNICODE);
    } else {
        if ($has_created_by) {
            $insertSql = 'INSERT INTO informes_guardados
                (cliente_id, created_by_usuario_id, titulo, fecha_despacho, total_despachos, a_tiempo, con_retraso, en_ruta, programados, total_incidencias, datos_informe, operador_monitoreo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        } else {
            $insertSql = 'INSERT INTO informes_guardados
                (cliente_id, titulo, fecha_despacho, total_despachos, a_tiempo, con_retraso, en_ruta, programados, total_incidencias, datos_informe, operador_monitoreo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        }
        $insertStmt = $conn->prepare($insertSql);
        if (!$insertStmt) {
            throw new Exception('Error preparando insert: ' . $conn->error, 500);
        }

        if ($has_created_by) {
            $insertStmt->bind_param(
                'iissiiiiiiss',
                $cliente_id,
                $auth_user['id'],
                $titulo,
                $fecha_despacho,
                $total_despachos,
                $a_tiempo,
                $con_retraso,
                $en_ruta,
                $programados,
                $total_incidencias,
                $datos_informe,
                $operador_monitoreo
            );
        } else {
            $insertStmt->bind_param(
                'issiiiiiiss',
                $cliente_id,
                $titulo,
                $fecha_despacho,
                $total_despachos,
                $a_tiempo,
                $con_retraso,
                $en_ruta,
                $programados,
                $total_incidencias,
                $datos_informe,
                $operador_monitoreo
            );
        }
        if (!$insertStmt->execute()) {
            throw new Exception('Error ejecutando insert: ' . $insertStmt->error, 500);
        }
        $newId = $insertStmt->insert_id;
        $insertStmt->close();
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Informe guardado correctamente',
            'action' => 'inserted',
            'id' => $newId,
            'fecha_despacho' => $fecha_despacho
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    if (isset($conn) && $conn && method_exists($conn, 'rollback')) {
        @$conn->rollback();
    }
    http_response_code(function_exists('informes_exception_code') ? informes_exception_code($e, 500) : 500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>
