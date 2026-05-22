<?php

error_reporting(0);
ini_set('display_errors', 0);

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

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

  $refs = [];
  $refs[] = $types;
  foreach ($params as $key => $value) {
    $params[$key] = $value;
    $refs[] = &$params[$key];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);
}

function despacho_column_exists($conn, $column) {
  $column = trim((string)$column);
  if ($column === '') return false;

  $stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
       FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'despachos'
        AND COLUMN_NAME = ?"
  );
  if (!$stmt) return false;
  $stmt->bind_param('s', $column);
  if (!$stmt->execute()) {
    $stmt->close();
    return false;
  }
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return intval($row['total'] ?? 0) > 0;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    throw new Exception('Método no permitido. Use GET.');
  }

  require_once __DIR__ . '/../db/db.php';

  $cliente_ids = parse_id_list($_GET['cliente_ids'] ?? '');
  $lector_email = strtolower(trim((string)($_GET['lector_email'] ?? '')));

  $where = ['c.activo = 1', 'u.activo = 1'];
  if (despacho_column_exists($conn, 'eliminado_at')) {
    $where[] = 'd.eliminado_at IS NULL';
  }
  $types = '';
  $params = [];

  if (count($cliente_ids) > 0) {
    $placeholders = implode(',', array_fill(0, count($cliente_ids), '?'));
    $where[] = "d.cliente_id IN ($placeholders)";
    $types .= str_repeat('i', count($cliente_ids));
    foreach ($cliente_ids as $id) {
      $params[] = $id;
    }
  } elseif (isset($_GET['cliente_ids'])) {
    $where[] = '1 = 0';
  }

  if ($lector_email !== '') {
    $where[] = "EXISTS (
      SELECT 1
        FROM despacho_lectores dl_filter
        INNER JOIN usuarios u_filter ON u_filter.id = dl_filter.usuario_id
       WHERE dl_filter.despacho_id = d.id
         AND LOWER(u_filter.email) = ?
    )";
    $types .= 's';
    $params[] = $lector_email;
  }

  $where_sql = implode(' AND ', $where);

  $sql = "SELECT
      d.id AS despacho_id,
      d.cliente_id,
      c.nombre AS cliente,
      d.unidad_id,
      d.folio,
      d.fecha_programada AS fecha,
      d.tramo_numero,
      d.ruta,
      d.origen,
      d.lugar_carga,
      d.destino,
      d.instrucciones AS instrucciones_especiales,
      d.salida_patio_programada AS salida_prog,
      d.cita_carga AS carga_prog,
      d.salida_carga_programada AS salida_carga_prog,
      d.descarga_programada AS descarga_prog,
      u.economico AS unidad,
      u.placas,
      u.equipos AS id_equipos,
      u.operador,
      u.telefonos AS telefono,
      GROUP_CONCAT(DISTINCT lector.email ORDER BY lector.email SEPARATOR ', ') AS lector_emails
    FROM despachos d
    INNER JOIN clientes c ON c.id = d.cliente_id
    INNER JOIN unidades u ON u.id = d.unidad_id
    LEFT JOIN despacho_lectores dl ON dl.despacho_id = d.id
    LEFT JOIN usuarios lector ON lector.id = dl.usuario_id
    WHERE $where_sql
    GROUP BY d.id
    ORDER BY d.fecha_programada DESC, c.nombre ASC, u.economico ASC, d.tramo_numero ASC";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    throw new Exception('Error preparando consulta: ' . $conn->error);
  }

  bind_statement_params($stmt, $types, $params);

  if (!$stmt->execute()) {
    throw new Exception('Error ejecutando consulta: ' . $stmt->error);
  }

  $result = $stmt->get_result();
  $rows = [];
  while ($row = $result->fetch_assoc()) {
    $rows[] = [
      'despacho_id' => intval($row['despacho_id']),
      'cliente_id' => intval($row['cliente_id']),
      'cliente' => (string)$row['cliente'],
      'unidad_id' => intval($row['unidad_id']),
      'folio' => (string)$row['folio'],
      'fecha' => (string)$row['fecha'],
      'tramo_numero' => intval($row['tramo_numero']),
      'unidad' => (string)$row['unidad'],
      'placas' => (string)$row['placas'],
      'id_equipos' => (string)$row['id_equipos'],
      'operador' => (string)$row['operador'],
      'telefono' => (string)$row['telefono'],
      'ruta' => (string)$row['ruta'],
      'origen' => (string)$row['origen'],
      'lugar_carga' => (string)$row['lugar_carga'],
      'destino' => (string)$row['destino'],
      'salida_prog' => (string)$row['salida_prog'],
      'carga_prog' => (string)$row['carga_prog'],
      'salida_carga_prog' => (string)$row['salida_carga_prog'],
      'descarga_prog' => (string)$row['descarga_prog'],
      'instrucciones_especiales' => (string)$row['instrucciones_especiales'],
      'lector_emails' => (string)$row['lector_emails']
    ];
  }
  $stmt->close();

  $response['success'] = true;
  $response['message'] = 'Despachos obtenidos correctamente';
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
