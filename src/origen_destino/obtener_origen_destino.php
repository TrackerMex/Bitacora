<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$response = [
  'success' => false,
  'message' => '',
  'data'    => [],
  'count'   => 0
];

try {
  require_once __DIR__ . '/../db/db.php';

  $sql = "SELECT folio, unidad, fecha_programada, origen, destino
          FROM despacho_origen_destino
          ORDER BY fecha_programada DESC, unidad ASC";

  $result = $conn->query($sql);
  if (!$result) {
    throw new Exception('Error en consulta: ' . $conn->error);
  }

  $rows = [];
  while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
  }
  $result->free();

  $response['success'] = true;
  $response['message'] = 'Datos obtenidos correctamente.';
  $response['data']    = $rows;
  $response['count']   = count($rows);

} catch (Exception $e) {
  http_response_code(500);
  $response['message'] = 'Error: ' . $e->getMessage();
} finally {
  if (isset($conn) && $conn) {
    $conn->close();
  }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
