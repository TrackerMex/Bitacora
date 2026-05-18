<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception("ID no especificado");
    }

    $id = intval($_GET['id']);

    if ($id <= 0) {
        throw new Exception("ID inválido");
    }

    require_once __DIR__ . '/../db/db.php';
    require_once __DIR__ . '/informe_auth.php';

    $auth_user = informes_get_auth_user($conn, false);
    $has_created_by = informes_column_exists($conn, 'created_by_usuario_id');
    $sql = "SELECT * FROM informes_guardados WHERE id = ?";
    $types = "i";
    $params = [$id];

    if ($has_created_by && $auth_user && $auth_user['role'] !== 'admin') {
        $sql .= " AND created_by_usuario_id = ?";
        $types .= "i";
        $params[] = $auth_user['id'];
    } elseif ($auth_user && $auth_user['role'] !== 'admin') {
        $cliente_ids = informes_get_user_cliente_ids($conn, $auth_user['id'], $auth_user['role']);
        if (count($cliente_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($cliente_ids), '?'));
            $sql .= " AND cliente_id IN ($placeholders)";
            $types .= str_repeat('i', count($cliente_ids));
            foreach ($cliente_ids as $cliente_id) {
                $params[] = $cliente_id;
            }
        } else {
            $sql .= " AND 1 = 0";
        }
    }
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }

    $refs = [$types];
    foreach ($params as $key => $value) {
        $params[$key] = $value;
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);

    if (!$stmt->execute()) {
        throw new Exception("Error ejecutando consulta: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (!empty($row['datos_informe'])) {
            $decoded = json_decode($row['datos_informe'], true);
            if ($decoded !== null) {
                $row['datos_informe_decoded'] = $decoded;
            }
        }

        $response['success'] = true;
        $response['data'] = $row;
        $response['message'] = 'Informe encontrado';
    } else {
        throw new Exception("Informe no encontrado");
    }

    $stmt->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(404);
}

if (isset($conn)) {
    $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
