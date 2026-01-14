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

try {
    require_once __DIR__ . '/../db/db.php';
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'informes_guardados'");
    
    if ($tableCheck->num_rows === 0) {
        $response['success'] = true;
        $response['message'] = 'No hay informes guardados';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    $sql = "SELECT id, titulo, fecha_creacion, fecha_despacho, total_despachos, a_tiempo, con_retraso, en_ruta, programados, total_incidencias, operador_monitoreo 
            FROM informes_guardados 
            ORDER BY fecha_creacion DESC 
            LIMIT 100";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Error en consulta: " . $conn->error);
    }
    
    $informes = [];
    $count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $row['id'] = intval($row['id']);
        $row['total_despachos'] = intval($row['total_despachos']);
        $row['a_tiempo'] = intval($row['a_tiempo']);
        $row['con_retraso'] = intval($row['con_retraso']);
        $row['en_ruta'] = intval($row['en_ruta']);
        $row['programados'] = intval($row['programados']);
        $row['total_incidencias'] = intval($row['total_incidencias']);
        
        $informes[] = $row;
        $count++;
    }
    
    $response['success'] = true;
    $response['message'] = 'Informes obtenidos correctamente';
    $response['data'] = $informes;
    $response['count'] = $count;
    
    $result->free();
    
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