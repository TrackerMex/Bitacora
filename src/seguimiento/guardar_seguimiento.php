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
  'id' => null
];

function to_mysql_date($value) {
  $s = trim((string)$value);
  if ($s === '') return '';

  // Si viene como "YYYY-MM-DD" o "YYYY-MM-DD..." tomar la parte de fecha.
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

function to_mysql_datetime_or_null($value) {
  $s = trim((string)$value);
  if ($s === '') return null;

  // Ya viene como "YYYY-MM-DD HH:MM[:SS]"
  if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(?::\d{2})?$/', $s)) {
    return (strlen($s) === 16) ? ($s . ':00') : $s;
  }

  // Parsear ISO (incluye Z / offset) u otros formatos
  try {
    $dt = new DateTime($s);
    return $dt->format('Y-m-d H:i:s');
  } catch (Exception $e) {
    return null;
  }
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido. Use POST.');
  }

  require_once __DIR__ . '/../db/db.php';

  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    throw new Exception('Body JSON inválido.');
  }

  $folio = isset($data['folio']) ? (string)$data['folio'] : '';
  $unidad = isset($data['unidad']) ? (string)$data['unidad'] : '';
  $fechaProgramadaRaw = isset($data['fechaProgramada']) ? (string)$data['fechaProgramada'] : '';
  $fechaProgramada = to_mysql_date($fechaProgramadaRaw);

  if ($folio === '' || $unidad === '' || $fechaProgramada === '') {
    throw new Exception('Faltan campos requeridos: folio, unidad, fechaProgramada');
  }

  $operadorMonitoreo = isset($data['operadorMonitoreoId']) ? (string)$data['operadorMonitoreoId'] : '';
  $gpsEstado = isset($data['gpsValidacionEstado']) ? (string)$data['gpsValidacionEstado'] : '';
  $gpsTs = to_mysql_datetime_or_null($data['gpsValidacionTimestamp'] ?? null);

  $realSalidaUnidad = to_mysql_datetime_or_null($data['realSalidaUnidad'] ?? null);
  $realCarga = to_mysql_datetime_or_null($data['realCarga'] ?? null);
  $realSalida = to_mysql_datetime_or_null($data['realSalida'] ?? null);
  $realDescarga = to_mysql_datetime_or_null($data['realDescarga'] ?? null);

  $confirmacion = null;
  if (array_key_exists('confirmacionEntrega', $data)) {
    $c = $data['confirmacionEntrega'];
    if ($c !== null) {
      $c = trim((string)$c);
      $confirmacion = ($c === '') ? null : $c;
    }
  }
  $estatus = isset($data['estatus']) ? (string)$data['estatus'] : '';
  $observaciones = isset($data['observaciones']) ? (string)$data['observaciones'] : '';

  $conn->begin_transaction();

  $sql = "INSERT INTO seguimiento_despacho (
      folio, unidad, fecha_programada,
      operador_monitoreo, gps_estado, gps_timestamp,
      real_salida_unidad, real_carga, real_salida, real_descarga,
      confirmacion_entrega, estatus, observaciones
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ON DUPLICATE KEY UPDATE
      operador_monitoreo = VALUES(operador_monitoreo),
      gps_estado = VALUES(gps_estado),
      gps_timestamp = VALUES(gps_timestamp),
      real_salida_unidad = VALUES(real_salida_unidad),
      real_carga = VALUES(real_carga),
      real_salida = VALUES(real_salida),
      real_descarga = VALUES(real_descarga),
      confirmacion_entrega = VALUES(confirmacion_entrega),
      estatus = VALUES(estatus),
      observaciones = VALUES(observaciones)";

  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    throw new Exception('Error preparando UPSERT: ' . $conn->error);
  }

  // confirmacion_entrega puede ser null
  $stmt->bind_param(
    'sssssssssssss',
    $folio,
    $unidad,
    $fechaProgramada,
    $operadorMonitoreo,
    $gpsEstado,
    $gpsTs,
    $realSalidaUnidad,
    $realCarga,
    $realSalida,
    $realDescarga,
    $confirmacion,
    $estatus,
    $observaciones
  );

  if (!$stmt->execute()) {
    throw new Exception('Error ejecutando UPSERT: ' . $stmt->error);
  }
  $stmt->close();

  // Obtener el id real (importante cuando fue UPDATE por clave única)
  $stmtId = $conn->prepare('SELECT id FROM seguimiento_despacho WHERE folio = ? AND unidad = ? AND fecha_programada = ? LIMIT 1');
  if (!$stmtId) {
    throw new Exception('Error preparando SELECT id: ' . $conn->error);
  }
  $stmtId->bind_param('sss', $folio, $unidad, $fechaProgramada);
  if (!$stmtId->execute()) {
    throw new Exception('Error ejecutando SELECT id: ' . $stmtId->error);
  }
  $resId = $stmtId->get_result();
  $rowId = $resId ? $resId->fetch_assoc() : null;
  $stmtId->close();

  $seguimientoId = $rowId && isset($rowId['id']) ? intval($rowId['id']) : 0;
  if ($seguimientoId <= 0) {
    throw new Exception('No se pudo resolver el ID del seguimiento.');
  }

  // Limpiar incidencias previas
  $stmtDel = $conn->prepare('DELETE FROM seguimiento_incidencias WHERE seguimiento_id = ?');
  if (!$stmtDel) {
    throw new Exception('Error preparando DELETE incidencias: ' . $conn->error);
  }
  $stmtDel->bind_param('i', $seguimientoId);
  if (!$stmtDel->execute()) {
    throw new Exception('Error ejecutando DELETE incidencias: ' . $stmtDel->error);
  }
  $stmtDel->close();

  // Insertar incidencias
  $incidencias = isset($data['incidencias']) && is_array($data['incidencias']) ? $data['incidencias'] : [];
  if (count($incidencias) > 0) {
    $stmtInc = $conn->prepare('INSERT INTO seguimiento_incidencias (seguimiento_id, tipo, severidad, fecha, direccion) VALUES (?,?,?,?,?)');
    if (!$stmtInc) {
      throw new Exception('Error preparando INSERT incidencias: ' . $conn->error);
    }

    foreach ($incidencias as $inc) {
      if (!is_array($inc)) continue;
      $tipo = isset($inc['tipo']) ? (string)$inc['tipo'] : '';
      $sev = isset($inc['severidad']) ? (string)$inc['severidad'] : '';
      $fecha = to_mysql_datetime_or_null($inc['fecha'] ?? null);
      $direccion = isset($inc['direccion']) ? (string)$inc['direccion'] : '';
      if ($tipo === '') continue;

      $stmtInc->bind_param('issss', $seguimientoId, $tipo, $sev, $fecha, $direccion);
      if (!$stmtInc->execute()) {
        throw new Exception('Error insertando incidencia: ' . $stmtInc->error);
      }
    }
    $stmtInc->close();
  }

  $conn->commit();

  $response['success'] = true;
  $response['message'] = 'Seguimiento guardado correctamente';
  $response['id'] = $seguimientoId;

} catch (Exception $e) {
  if (isset($conn) && $conn) {
    try { $conn->rollback(); } catch (Exception $ignored) {}
  }
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
