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

  $sql = "SELECT
      s.id,
      s.folio,
      s.unidad,
      s.fecha_programada,
      s.operador_monitoreo,
      s.gps_estado,
      s.gps_timestamp,
      s.real_salida_unidad,
      s.real_carga,
      s.real_salida,
      s.real_descarga,
      s.confirmacion_entrega,
      s.estatus,
      s.observaciones,
      GROUP_CONCAT(CONCAT(i.tipo, ' | ', i.severidad, ' | ', i.fecha, ' | ', COALESCE(i.direccion, '')) SEPARATOR ';;') AS incidencias
    FROM seguimiento_despacho s
    LEFT JOIN seguimiento_incidencias i
      ON i.seguimiento_id = s.id
    GROUP BY s.id
    ORDER BY s.fecha_programada DESC, s.unidad ASC";

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
  $response['message'] = 'Seguimientos obtenidos correctamente';
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
