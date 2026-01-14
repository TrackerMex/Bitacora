<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

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

    $sql = "SELECT * FROM informes_guardados WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $id);

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
