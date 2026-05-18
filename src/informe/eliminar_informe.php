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

    $id = $data['id'] ?? null;
    if (!$id || !is_numeric($id)) {
        throw new Exception('ID no especificado o invalido', 400);
    }
    $id = intval($id);

    $sql = 'DELETE FROM informes_guardados WHERE id = ?';
    $types = 'i';
    $params = [$id];

    if ($has_created_by && $auth_user['role'] !== 'admin') {
        $sql .= ' AND created_by_usuario_id = ?';
        $types .= 'i';
        $params[] = $auth_user['id'];
    } elseif ($auth_user['role'] !== 'admin') {
        $cliente_ids = informes_get_user_cliente_ids($conn, $auth_user['id'], $auth_user['role']);
        if (count($cliente_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($cliente_ids), '?'));
            $sql .= " AND cliente_id IN ($placeholders)";
            $types .= str_repeat('i', count($cliente_ids));
            foreach ($cliente_ids as $cliente_id) {
                $params[] = $cliente_id;
            }
        } else {
            $sql .= ' AND 1 = 0';
        }
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error preparando eliminacion: ' . $conn->error, 500);
    }
    $refs = [$types];
    foreach ($params as $key => $value) {
        $params[$key] = $value;
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);

    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar: ' . $stmt->error, 500);
    }

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Informe eliminado correctamente'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontro el informe para eliminar'
        ], JSON_UNESCAPED_UNICODE);
    }
    $stmt->close();
} catch (Exception $e) {
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
