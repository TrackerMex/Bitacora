<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

$response = [
  'success' => false,
  'message' => '',
  'id' => null
];

require_once __DIR__ . '/../auth/jwt.php';

function clean_delete_reason($value) {
  $s = trim((string)($value ?? ''));
  if (strlen($s) > 255) {
    return substr($s, 0, 255);
  }
  return $s;
}

function require_tramo_editor($conn) {
  $token = get_bearer_token();
  if ($token === '') {
    throw new Exception('Sesion requerida.');
  }

  $payload = jwt_decode_payload($token);
  $email = strtolower(trim((string)($payload['email'] ?? '')));
  if ($email === '') {
    throw new Exception('Token invalido.');
  }

  $stmt = $conn->prepare('SELECT id, role, activo FROM usuarios WHERE LOWER(email) = ? LIMIT 1');
  if (!$stmt) {
    throw new Exception('Error preparando usuario: ' . $conn->error);
  }
  $stmt->bind_param('s', $email);
  if (!$stmt->execute()) {
    throw new Exception('Error consultando usuario: ' . $stmt->error);
  }
  $res = $stmt->get_result();
  $user = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  $role = strtolower((string)($user['role'] ?? ''));
  if (!$user || intval($user['activo'] ?? 0) !== 1 || $role === 'lector') {
    throw new Exception('Usuario no autorizado.');
  }

  $cliente_ids = [];
  if ($role !== 'admin') {
    $stmt_clientes = $conn->prepare('SELECT cliente_id FROM usuario_clientes WHERE usuario_id = ?');
    if (!$stmt_clientes) {
      throw new Exception('Error preparando clientes de usuario: ' . $conn->error);
    }
    $usuario_id = intval($user['id']);
    $stmt_clientes->bind_param('i', $usuario_id);
    if (!$stmt_clientes->execute()) {
      throw new Exception('Error consultando clientes de usuario: ' . $stmt_clientes->error);
    }
    $res_clientes = $stmt_clientes->get_result();
    while ($row_cliente = $res_clientes->fetch_assoc()) {
      $cliente_id = intval($row_cliente['cliente_id'] ?? 0);
      if ($cliente_id > 0) {
        $cliente_ids[] = $cliente_id;
      }
    }
    $stmt_clientes->close();
  }

  return [
    'id' => intval($user['id']),
    'role' => $role,
    'cliente_ids' => array_values(array_unique($cliente_ids))
  ];
}

function assert_tramo_access($conn, $despacho_id, $user) {
  if (($user['role'] ?? '') === 'admin') {
    return;
  }

  $cliente_ids = $user['cliente_ids'] ?? [];
  if (!count($cliente_ids)) {
    throw new Exception('Usuario sin clientes asignados.');
  }

  $stmt = $conn->prepare('SELECT cliente_id FROM despachos WHERE id = ? LIMIT 1');
  if (!$stmt) {
    throw new Exception('Error preparando acceso a tramo: ' . $conn->error);
  }
  $stmt->bind_param('i', $despacho_id);
  if (!$stmt->execute()) {
    throw new Exception('Error verificando acceso a tramo: ' . $stmt->error);
  }
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  $cliente_id = intval($row['cliente_id'] ?? 0);
  if (!$row || !in_array($cliente_id, $cliente_ids, true)) {
    throw new Exception('Usuario no autorizado para este tramo.');
  }
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
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Metodo no permitido. Use POST.');
  }

  require_once __DIR__ . '/../db/db.php';
  $user = require_tramo_editor($conn);

  if (!despacho_column_exists($conn, 'eliminado_at')) {
    throw new Exception('Falta aplicar la migracion de tramos eliminados.');
  }

  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    throw new Exception('Body JSON invalido.');
  }

  $despacho_id = intval($data['despachoId'] ?? $data['despacho_id'] ?? 0);
  $motivo = clean_delete_reason($data['motivo'] ?? '');

  if ($despacho_id <= 0) {
    throw new Exception('Falta despachoId valido.');
  }

  assert_tramo_access($conn, $despacho_id, $user);

  $stmt_check = $conn->prepare('SELECT id, eliminado_at FROM despachos WHERE id = ? LIMIT 1');
  if (!$stmt_check) {
    throw new Exception('Error preparando verificacion: ' . $conn->error);
  }
  $stmt_check->bind_param('i', $despacho_id);
  if (!$stmt_check->execute()) {
    throw new Exception('Error verificando tramo: ' . $stmt_check->error);
  }
  $res_check = $stmt_check->get_result();
  $row_check = $res_check ? $res_check->fetch_assoc() : null;
  $stmt_check->close();

  if (!$row_check) {
    throw new Exception('No se encontro el tramo solicitado.');
  }

  if (!empty($row_check['eliminado_at'])) {
    $response['success'] = true;
    $response['message'] = 'El tramo ya estaba eliminado.';
    $response['id'] = $despacho_id;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
  }

  $stmt = $conn->prepare(
    'UPDATE despachos
        SET eliminado_at = NOW(),
            eliminado_por_usuario_id = ?,
            eliminado_motivo = ?
      WHERE id = ?'
  );
  if (!$stmt) {
    throw new Exception('Error preparando eliminacion logica: ' . $conn->error);
  }
  $stmt->bind_param('isi', $user['id'], $motivo, $despacho_id);
  if (!$stmt->execute()) {
    throw new Exception('Error eliminando tramo: ' . $stmt->error);
  }
  $stmt->close();

  $response['success'] = true;
  $response['message'] = 'Tramo eliminado correctamente.';
  $response['id'] = $despacho_id;

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
