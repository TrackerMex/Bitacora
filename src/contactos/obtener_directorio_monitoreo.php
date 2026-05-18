<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

function split_contact_values($value) {
    $parts = preg_split('/[\n,;|\/]+/', (string)($value ?? ''));
    $out = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $out[] = $part;
        }
    }
    return $out;
}

try {
    require_once __DIR__ . '/../db/db.php';

    $cliente_id = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
    $cliente_nombre = isset($_GET['cliente']) ? trim((string)$_GET['cliente']) : '';

    $where = 'dm.activo = 1';
    $types = '';
    $params = [];

    if ($cliente_id > 0) {
        $where .= ' AND dm.cliente_id = ?';
        $types .= 'i';
        $params[] = $cliente_id;
    } elseif ($cliente_nombre !== '') {
        $where .= ' AND c.nombre = ?';
        $types .= 's';
        $params[] = $cliente_nombre;
    } else {
        throw new Exception('Cliente requerido');
    }

    $sql = "SELECT
                dm.id,
                dm.cliente_id,
                c.nombre AS cliente,
                dm.nombre,
                dm.cargo,
                dm.area,
                dm.prioridad,
                dm.telefonos,
                dm.correos,
                dm.acciones,
                dm.observaciones
            FROM directorio_monitoreo dm
            INNER JOIN clientes c ON c.id = dm.cliente_id
            WHERE $where
            ORDER BY dm.nombre ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => (int)$row['id'],
            'cliente_id' => (int)$row['cliente_id'],
            'cliente' => $row['cliente'] ?? '',
            'nombre' => $row['nombre'] ?? '',
            'cargo' => $row['cargo'] ?? '',
            'departamento' => $row['area'] ?? '',
            'prioridad' => $row['prioridad'] ?: 'Normal',
            'telefonos' => split_contact_values($row['telefonos'] ?? ''),
            'correos' => split_contact_values($row['correos'] ?? ''),
            'acciones' => $row['acciones'] ?? '',
            'observaciones' => $row['observaciones'] ?? ''
        ];
    }
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Directorio de monitoreo obtenido correctamente';
    $response['data'] = $items;
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>
