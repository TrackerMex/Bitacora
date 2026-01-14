<?php
// obtener_numeros_emergencia.php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$response = [
    'success' => false,
    'message' => '',
    'data' => [],
    'count' => 0
];

function pick_first_value($row, $keys) {
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== null && trim((string)$row[$k]) !== '') {
            return $row[$k];
        }
    }
    return '';
}

try {
    // Localizar db.php de forma robusta (en algunos despliegues está en rutas distintas)
    $dbCandidates = [
        __DIR__ . '/../db/db.php',
        __DIR__ . '/../db.php',
        __DIR__ . '/../informe/db.php',
        __DIR__ . '/db.php'
    ];

    $dbFile = null;
    foreach ($dbCandidates as $candidate) {
        if (file_exists($candidate)) {
            $dbFile = $candidate;
            break;
        }
    }

    if (!$dbFile) {
        throw new Exception('No se encontró db.php. Rutas intentadas: ' . implode(' | ', $dbCandidates));
    }

    require_once $dbFile;

    // Verificar si la tabla existe
    $tableCheck = $conn->query("SHOW TABLES LIKE 'numeros_emergencia_estado'");
    if (!$tableCheck || $tableCheck->num_rows === 0) {
        $response['success'] = true;
        $response['message'] = 'La tabla numeros_emergencia_estado no existe o no es accesible.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Esquema esperado:
    // numeros_emergencia_estado(id, estado, municipio, numero)
    // OJO: numero es BIGINT; lo tratamos como string para evitar problemas en JS.
    $sql = "SELECT estado, municipio, numero FROM numeros_emergencia_estado ORDER BY estado, municipio";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception('Error en consulta: ' . $conn->error);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $estado = (string)pick_first_value($row, ['estado']);
        $municipio = (string)pick_first_value($row, ['municipio']);
        $tel = isset($row['numero']) ? (string)$row['numero'] : '';

        $estado = strtoupper(trim($estado));
        $municipio = strtoupper(trim($municipio));
        $tel = preg_replace('/\s+/', '', trim($tel));

        // Saltar filas vacías
        if ($estado === '' && $municipio === '' && $tel === '') {
            continue;
        }

        $rows[] = [
            'estado' => $estado,
            'municipio' => $municipio,
            'tel' => $tel
        ];
    }

    $result->free();

    $response['success'] = true;
    $response['message'] = 'Números de emergencia obtenidos correctamente';
    $response['data'] = $rows;
    $response['count'] = count($rows);

} catch (Exception $e) {
    http_response_code(500);
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
