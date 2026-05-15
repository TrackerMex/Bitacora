<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$response = [
  'success' => false,
  'message' => '',
  'deleted' => 0
];

try {
  $confirm = PHP_SAPI === 'cli' ? ($argv[1] ?? '') : ($_GET['confirm'] ?? '');
  if ($confirm !== 'limpiar_clientes_orfanos') {
    throw new Exception('Confirmación requerida.');
  }

  require_once __DIR__ . '/../db/db.php';

  $sql = "DELETE c
            FROM clientes c
            LEFT JOIN usuario_clientes uc ON uc.cliente_id = c.id
            LEFT JOIN unidades u ON u.cliente_id = c.id
            LEFT JOIN despachos d ON d.cliente_id = c.id
            LEFT JOIN seguimiento_despacho s ON s.cliente_id = c.id
            LEFT JOIN informes_guardados i ON i.cliente_id = c.id
            LEFT JOIN contactos co ON co.cliente_id = c.id
           WHERE c.slug = ''
             AND uc.usuario_id IS NULL
             AND u.id IS NULL
             AND d.id IS NULL
             AND s.id IS NULL
             AND i.id IS NULL
             AND co.id IS NULL";

  if (!$conn->query($sql)) {
    throw new Exception('Error eliminando clientes huérfanos: ' . $conn->error);
  }

  $response['success'] = true;
  $response['message'] = 'Clientes huérfanos limpiados.';
  $response['deleted'] = $conn->affected_rows;
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
