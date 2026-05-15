<?php
error_reporting(0);
ini_set('display_errors', 0);

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$response = [
    'success' => false,
    'message' => '',
    'data' => [],
    'count' => 0
];

function parse_id_list($value) {
    $ids = [];
    foreach (explode(',', (string)$value) as $part) {
        $id = intval(trim($part));
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function bind_statement_params($stmt, $types, $params) {
    if ($types === '' || count($params) === 0) {
        return;
    }

    $refs = [$types];
    foreach ($params as $key => $value) {
        $params[$key] = $value;
        $refs[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

try {
    require_once __DIR__ . '/../db/db.php';
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'informes_guardados'");
    
    if ($tableCheck->num_rows === 0) {
        $response['success'] = true;
        $response['message'] = 'No hay informes guardados';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $cliente_ids = parse_id_list($_GET['cliente_ids'] ?? '');
    $where = '';
    $types = '';
    $params = [];
    if (count($cliente_ids) > 0) {
        $placeholders = implode(',', array_fill(0, count($cliente_ids), '?'));
        $where = "WHERE cliente_id IN ($placeholders)";
        $types = str_repeat('i', count($cliente_ids));
        foreach ($cliente_ids as $id) {
            $params[] = $id;
        }
    } elseif (isset($_GET['cliente_ids'])) {
        $where = 'WHERE 1 = 0';
    }

    $sql = "SELECT id, cliente_id, titulo, fecha_creacion, fecha_despacho, total_despachos, a_tiempo, con_retraso, en_ruta, programados, total_incidencias, operador_monitoreo, datos_informe
            FROM informes_guardados
            $where
            ORDER BY fecha_creacion DESC
            LIMIT 100";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error preparando consulta: " . $conn->error);
    }
    bind_statement_params($stmt, $types, $params);

    if (!$stmt->execute()) {
        throw new Exception("Error en consulta: " . $stmt->error);
    }

    $result = $stmt->get_result();
    
    $informes = [];
    $count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $row['id'] = intval($row['id']);
        $row['cliente_id'] = $row['cliente_id'] !== null ? intval($row['cliente_id']) : null;
        $row['total_despachos'] = intval($row['total_despachos']);
        $row['a_tiempo'] = intval($row['a_tiempo']);
        $row['con_retraso'] = intval($row['con_retraso']);
        $row['en_ruta'] = intval($row['en_ruta']);
        $row['programados'] = intval($row['programados']);
        $row['total_incidencias'] = intval($row['total_incidencias']);
        
        // Decodificar datos_informe si existe
        if (!empty($row['datos_informe'])) {
            $decoded = json_decode($row['datos_informe'], true);
            if ($decoded !== null) {
                $row['datos_informe_decoded'] = $decoded;
            }
        }
        
        $informes[] = $row;
        $count++;
    }
    
    $response['success'] = true;
    $response['message'] = 'Informes obtenidos correctamente';
    $response['data'] = $informes;
    $response['count'] = $count;
    
    $stmt->close();
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    http_response_code(500);
}

if (isset($conn)) {
    $conn->close();
}

$json_output = json_encode($response, JSON_UNESCAPED_UNICODE);

if ($json_output === false) {
    $error_response = [
        'success' => false,
        'message' => 'Error generando JSON: ' . json_last_error_msg(),
        'data' => [],
        'count' => 0
    ];
    echo json_encode($error_response, JSON_UNESCAPED_UNICODE);
} else {
    echo $json_output;
}

exit();
?>
