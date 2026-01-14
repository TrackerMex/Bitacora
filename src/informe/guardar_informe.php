<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../db/db.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido. Use POST.");
    }
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    $titulo = $data['titulo'] ?? null;
    $datos_informe = $data['datos_informe'] ?? null;
    
    if (!$titulo || !$datos_informe) {
        throw new Exception("Faltan datos requeridos: título y datos del informe son obligatorios");
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
    
    if (is_array($datos_informe)) {
        $datos_informe = json_encode($datos_informe, JSON_UNESCAPED_UNICODE);
    }
    
    $conn->begin_transaction();

    $selectSql = "SELECT id FROM informes_guardados WHERE fecha_despacho = ? ORDER BY id DESC LIMIT 1";
    $selectStmt = $conn->prepare($selectSql);
    if (!$selectStmt) {
        throw new Exception("Error en la preparación (select): " . $conn->error);
    }
    $selectStmt->bind_param("s", $fecha_despacho);
    if (!$selectStmt->execute()) {
        throw new Exception("Error al ejecutar (select): " . $selectStmt->error);
    }
    $existingId = null;
    $selectResult = $selectStmt->get_result();
    if ($selectResult && ($row = $selectResult->fetch_assoc())) {
        $existingId = intval($row['id']);
    }
    $selectStmt->close();

    if ($existingId) {
        $updateSql = "UPDATE informes_guardados 
            SET titulo = ?, total_despachos = ?, a_tiempo = ?, con_retraso = ?, en_ruta = ?, programados = ?, total_incidencias = ?, datos_informe = ?, operador_monitoreo = ?
            WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        if (!$updateStmt) {
            throw new Exception("Error en la preparación (update): " . $conn->error);
        }

        $updateStmt->bind_param(
            "siiiiiissi",
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

        if (!$updateStmt->execute()) {
            throw new Exception("Error al ejecutar (update): " . $updateStmt->error);
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
        $insertSql = "INSERT INTO informes_guardados 
            (titulo, fecha_despacho, total_despachos, a_tiempo, con_retraso, en_ruta, programados, total_incidencias, datos_informe, operador_monitoreo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertSql);
        if (!$insertStmt) {
            throw new Exception("Error en la preparación (insert): " . $conn->error);
        }

        $insertStmt->bind_param(
            "ssiiiiisss",
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

        if (!$insertStmt->execute()) {
            throw new Exception("Error al ejecutar (insert): " . $insertStmt->error);
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
    http_response_code(500);
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