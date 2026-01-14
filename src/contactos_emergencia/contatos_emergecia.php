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
    // Verificar si se recibió el ID
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception("ID no especificado");
    }

    $id = intval($_GET['id']);

    if ($id <= 0) {
        throw new Exception("ID inválido");
    }

    // Conectar a la base de datos (ruta del proyecto)
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

    // Consulta para obtener el informe específico
    $sql = "SELECT 
            c.nombre,
            c.cargo,
            c.departamento,
            GROUP_CONCAT(DISTINCT t.telefono) AS telefonos,
            GROUP_CONCAT(DISTINCT e.correo) AS correos
                FROM contactos c
                LEFT JOIN contacto_telefonos t ON t.contacto_id = c.id
                LEFT JOIN contacto_correos e ON e.contacto_id = c.id
                WHERE c.id = ?
                GROUP BY c.id;";
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
        $response['success'] = true;
        $response['data'] = $row;
        $response['message'] = 'Contacto encontrado';
    } else {
        throw new Exception("Informe no encontrado");
    }

    $stmt->close();
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(404);
}
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
?>