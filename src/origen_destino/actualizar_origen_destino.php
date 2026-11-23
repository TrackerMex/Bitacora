<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../informe/informe_auth.php';

$response = [
  'success' => false,
  'message' => '',
  'id'      => null
];

function to_mysql_date($value) {
  $s = trim((string)$value);
  if ($s === '') return '';
  if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
    return substr($s, 0, 10);
  }
  try {
    $dt = new DateTime($s);
    return $dt->format('Y-m-d');
  } catch (Exception $e) {
    return '';
  }
}

function db_column_exists_od($conn, $table, $column) {
  $sql = "SELECT COUNT(*) AS c
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    return false;
  }
  $stmt->bind_param('ss', $table, $column);
  if (!$stmt->execute()) {
    $stmt->close();
    return false;
  }
  $result = $stmt->get_result();
  $row = $result ? $result->fetch_assoc() : null;
  if ($result) {
    $result->free();
  }
  $stmt->close();
  return intval($row['c'] ?? 0) > 0;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido. Use POST.');
  }

  require_once __DIR__ . '/../db/db.php';
  $user = informes_get_write_user($conn);

  $raw  = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    throw new Exception('Body JSON inválido.');
  }

  $folio           = isset($data['folio'])           ? (string)$data['folio']           : '';
  $unidad          = isset($data['unidad'])          ? (string)$data['unidad']          : '';
  $fechaProgramada = to_mysql_date($data['fechaProgramada'] ?? '');
  $origen          = isset($data['origen'])          ? (string)$data['origen']          : '';
  $lugarCarga      = isset($data['lugarCarga'])      ? (string)$data['lugarCarga']      : (isset($data['lugar_carga']) ? (string)$data['lugar_carga'] : '');
  $destino         = isset($data['destino'])         ? (string)$data['destino']         : '';

  if ($folio === '' || $unidad === '' || $fechaProgramada === '') {
    throw new Exception('Faltan campos requeridos: folio, unidad, fechaProgramada.');
  }

  if ($user['role'] !== 'admin') {
    $stmt = $conn->prepare(
      "SELECT 1
         FROM despachos d
         INNER JOIN unidades u ON u.id = d.unidad_id
        WHERE d.folio = ? AND u.economico = ? AND d.fecha_programada = ?
          AND " . informes_sql_clientes($conn, $user, 'd.cliente_id') . "
        LIMIT 1"
    );
    if (!$stmt) {
      throw new Exception('Error preparando validación: ' . $conn->error);
    }
    $stmt->bind_param('sss', $folio, $unidad, $fechaProgramada);
    $stmt->execute();
    $permitido = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$permitido) {
      throw new Exception('Sin acceso a este despacho.', 403);
    }
  }

  $hasLugarCarga = db_column_exists_od($conn, 'despacho_origen_destino', 'lugar_carga');

  if ($hasLugarCarga) {
    $sql = "INSERT INTO despacho_origen_destino
              (folio, unidad, fecha_programada, origen, lugar_carga, destino)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              origen      = VALUES(origen),
              lugar_carga = VALUES(lugar_carga),
              destino     = VALUES(destino)";
  } else {
    $sql = "INSERT INTO despacho_origen_destino
              (folio, unidad, fecha_programada, origen, destino)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              origen  = VALUES(origen),
              destino = VALUES(destino)";
  }

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    throw new Exception('Error preparando query: ' . $conn->error);
  }

  if ($hasLugarCarga) {
    $stmt->bind_param('ssssss', $folio, $unidad, $fechaProgramada, $origen, $lugarCarga, $destino);
  } else {
    $stmt->bind_param('sssss', $folio, $unidad, $fechaProgramada, $origen, $destino);
  }
  if (!$stmt->execute()) {
    throw new Exception('Error ejecutando query: ' . $stmt->error);
  }

  $insertId = $conn->insert_id;
  $stmt->close();

  $response['success'] = true;
  $response['message'] = 'Origen, carga y destino actualizados correctamente.';
  $response['id']      = $insertId ?: 0;

} catch (Exception $e) {
  http_response_code(informes_exception_code($e));
  $response['message'] = 'Error: ' . $e->getMessage();
} finally {
  if (isset($conn) && $conn) {
    $conn->close();
  }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
