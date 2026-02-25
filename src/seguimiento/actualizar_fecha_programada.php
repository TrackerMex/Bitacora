<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$response = [
  'success' => false,
  'message' => '',
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

  $folio          = isset($data['folio'])                ? (string)$data['folio']                : '';
  $unidad         = isset($data['unidad'])               ? (string)$data['unidad']               : '';
  $fechaActualRaw = isset($data['fechaProgramadaActual']) ? (string)$data['fechaProgramadaActual'] : '';
  $fechaNuevaRaw  = isset($data['fechaProgramadaNueva'])  ? (string)$data['fechaProgramadaNueva']  : '';

  $fechaActual = to_mysql_date($fechaActualRaw);
  $fechaNueva  = to_mysql_date($fechaNuevaRaw);

  if ($folio === '' || $unidad === '' || $fechaActual === '' || $fechaNueva === '') {
    throw new Exception('Faltan campos requeridos: folio, unidad, fechaProgramadaActual, fechaProgramadaNueva');
  }

  if ($fechaActual === $fechaNueva) {
    $response['success'] = true;
    $response['message'] = 'La fecha no cambió';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
  }

  // Verificar que el registro actual existe
  $stmtCheck = $conn->prepare(
    'SELECT id FROM seguimiento_despacho WHERE folio = ? AND unidad = ? AND fecha_programada = ? LIMIT 1'
  );
  if (!$stmtCheck) {
    throw new Exception('Error preparando consulta: ' . $conn->error);
  }
  $stmtCheck->bind_param('sss', $folio, $unidad, $fechaActual);
  if (!$stmtCheck->execute()) {
    throw new Exception('Error ejecutando consulta: ' . $stmtCheck->error);
  }
  $resCheck = $stmtCheck->get_result();
  $rowCheck = $resCheck ? $resCheck->fetch_assoc() : null;
  $stmtCheck->close();

  if (!$rowCheck) {
    throw new Exception("No se encontró el registro con folio={$folio}, unidad={$unidad}, fecha={$fechaActual}");
  }

  // Verificar que la nueva fecha no genere duplicado
  $stmtConflict = $conn->prepare(
    'SELECT id FROM seguimiento_despacho WHERE folio = ? AND unidad = ? AND fecha_programada = ? LIMIT 1'
  );
  if (!$stmtConflict) {
    throw new Exception('Error preparando consulta de conflicto: ' . $conn->error);
  }
  $stmtConflict->bind_param('sss', $folio, $unidad, $fechaNueva);
  if (!$stmtConflict->execute()) {
    throw new Exception('Error ejecutando consulta de conflicto: ' . $stmtConflict->error);
  }
  $resConflict = $stmtConflict->get_result();
  $rowConflict  = $resConflict ? $resConflict->fetch_assoc() : null;
  $stmtConflict->close();

  if ($rowConflict) {
    throw new Exception(
      "Ya existe un registro para folio={$folio}, unidad={$unidad} con fecha={$fechaNueva}. No se puede duplicar."
    );
  }

  // Actualizar la fecha programada
  $stmt = $conn->prepare(
    'UPDATE seguimiento_despacho SET fecha_programada = ? WHERE folio = ? AND unidad = ? AND fecha_programada = ?'
  );
  if (!$stmt) {
    throw new Exception('Error preparando UPDATE: ' . $conn->error);
  }
  $stmt->bind_param('ssss', $fechaNueva, $folio, $unidad, $fechaActual);
  if (!$stmt->execute()) {
    throw new Exception('Error ejecutando UPDATE: ' . $stmt->error);
  }
  $affected = $stmt->affected_rows;
  $stmt->close();

  if ($affected === 0) {
    throw new Exception('No se actualizó ningún registro. Verifique los datos.');
  }

  $response['success'] = true;
  $response['message'] = "Fecha programada actualizada de {$fechaActual} a {$fechaNueva}";

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
