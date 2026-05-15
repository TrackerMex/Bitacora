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

  $cliente_ids = parse_id_list($_GET['cliente_ids'] ?? '');
  $despacho_ids = parse_id_list($_GET['despacho_ids'] ?? '');
  $where = [];
  $types = '';
  $params = [];

  if (count($cliente_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($cliente_ids), '?'));
    $where[] = "(s.cliente_id IN ($placeholders) OR d_match.cliente_id IN ($placeholders))";
    $types .= str_repeat('i', count($cliente_ids) * 2);
    foreach ($cliente_ids as $id) $params[] = $id;
    foreach ($cliente_ids as $id) $params[] = $id;
  } elseif (isset($_GET['cliente_ids'])) {
    $where[] = '1 = 0';
  }

  if (count($despacho_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($despacho_ids), '?'));
    $where[] = "(s.despacho_id IN ($placeholders) OR d_match.id IN ($placeholders))";
    $types .= str_repeat('i', count($despacho_ids) * 2);
    foreach ($despacho_ids as $id) $params[] = $id;
    foreach ($despacho_ids as $id) $params[] = $id;
  } elseif (isset($_GET['despacho_ids'])) {
    $where[] = '1 = 0';
  }

  $where_sql = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

  $sql = "SELECT
      s.id,
      COALESCE(s.cliente_id, d_match.cliente_id) AS cliente_id,
      COALESCE(s.despacho_id, d_match.id) AS despacho_id,
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
      s.cita_salida_unidad,
      s.cita_carga,
      s.cita_salida,
      s.cita_descarga,
      s.confirmacion_entrega,
      s.estatus,
      s.estatus_especial,
      s.observaciones,
      GROUP_CONCAT(CONCAT(i.tipo, ' | ', i.severidad, ' | ', i.fecha, ' | ', COALESCE(i.direccion, '')) SEPARATOR ';;') AS incidencias
    FROM seguimiento_despacho s
    LEFT JOIN unidades u_match
      ON u_match.economico = s.unidad
    LEFT JOIN despachos d_match
      ON d_match.unidad_id = u_match.id
     AND d_match.folio = s.folio
     AND d_match.fecha_programada = s.fecha_programada
    LEFT JOIN seguimiento_incidencias i
      ON i.seguimiento_id = s.id
    $where_sql
    GROUP BY s.id
    ORDER BY s.fecha_programada DESC, s.unidad ASC";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    throw new Exception('Error preparando consulta: ' . $conn->error);
  }
  bind_statement_params($stmt, $types, $params);
  if (!$stmt->execute()) {
    throw new Exception('Error en consulta: ' . $stmt->error);
  }

  $result = $stmt->get_result();
  $rows = [];
  while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
  }
  $stmt->close();

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
