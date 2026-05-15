<?php

error_reporting(0);
ini_set('display_errors', 0);

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

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
  'message' => ''
];

function default_tabs_for_role($role) {
  $role = strtolower(trim((string)$role));
  if ($role === 'lector') {
    return [3, 4, 5, 6, 7];
  }
  return [0, 1, 2, 3, 4, 5, 6, 7];
}

function fetch_user_tabs($conn, $usuario_id, $role) {
  $tabs = [];
  $stmt = $conn->prepare(
    'SELECT tab_index FROM usuario_tabs WHERE usuario_id = ? ORDER BY tab_index ASC'
  );
  if (!$stmt) {
    throw new Exception('Error preparando permisos de tabs: ' . $conn->error);
  }
  $stmt->bind_param('i', $usuario_id);
  if (!$stmt->execute()) {
    throw new Exception('Error consultando permisos de tabs: ' . $stmt->error);
  }
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $tabs[] = intval($row['tab_index']);
  }
  $stmt->close();

  return count($tabs) ? $tabs : default_tabs_for_role($role);
}

function fetch_user_clients($conn, $usuario_id, $role) {
  $role = strtolower(trim((string)$role));
  if ($role === 'admin') {
    $sql = 'SELECT id, nombre FROM clientes WHERE activo = 1 ORDER BY nombre ASC';
    $res = $conn->query($sql);
    if (!$res) {
      throw new Exception('Error consultando clientes: ' . $conn->error);
    }
    $clientes = [];
    while ($row = $res->fetch_assoc()) {
      $clientes[] = [
        'id' => intval($row['id']),
        'nombre' => (string)$row['nombre']
      ];
    }
    $res->free();
    return $clientes;
  }

  $stmt = $conn->prepare(
    'SELECT c.id, c.nombre
       FROM usuario_clientes uc
       INNER JOIN clientes c ON c.id = uc.cliente_id
      WHERE uc.usuario_id = ? AND c.activo = 1
      ORDER BY c.nombre ASC'
  );
  if (!$stmt) {
    throw new Exception('Error preparando clientes de usuario: ' . $conn->error);
  }
  $stmt->bind_param('i', $usuario_id);
  if (!$stmt->execute()) {
    throw new Exception('Error consultando clientes de usuario: ' . $stmt->error);
  }
  $res = $stmt->get_result();
  $clientes = [];
  while ($row = $res->fetch_assoc()) {
    $clientes[] = [
      'id' => intval($row['id']),
      'nombre' => (string)$row['nombre']
    ];
  }
  $stmt->close();
  return $clientes;
}

function fetch_allowed_units($conn, $usuario_id, $role, $clientes) {
  $role = strtolower(trim((string)$role));
  if ($role === 'admin') {
    return ['*'];
  }

  $cliente_ids = array_values(array_filter(array_map(
    fn($c) => intval($c['id'] ?? 0),
    $clientes
  )));
  if (!count($cliente_ids)) {
    return [];
  }

  $placeholders = implode(',', array_fill(0, count($cliente_ids), '?'));
  $types = str_repeat('i', count($cliente_ids));
  $sql = "SELECT DISTINCT economico
            FROM unidades
           WHERE cliente_id IN ($placeholders)
             AND activo = 1
           ORDER BY economico ASC";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    throw new Exception('Error preparando unidades de cliente: ' . $conn->error);
  }
  $stmt->bind_param($types, ...$cliente_ids);
  if (!$stmt->execute()) {
    throw new Exception('Error consultando unidades de cliente: ' . $stmt->error);
  }
  $res = $stmt->get_result();
  $unidades = [];
  while ($row = $res->fetch_assoc()) {
    $unidad = trim((string)$row['economico']);
    if ($unidad !== '') {
      $unidades[] = $unidad;
    }
  }
  $stmt->close();
  return $unidades;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido. Use POST.');
  }

  require_once __DIR__ . '/../db/db.php';
  require_once __DIR__ . '/../auth/jwt.php';

  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    throw new Exception('Body JSON inválido.');
  }

  $email = isset($data['username']) ? strtolower(trim((string)$data['username'])) : '';
  if ($email === '' && isset($data['email'])) {
    $email = strtolower(trim((string)$data['email']));
  }
  $password = isset($data['password']) ? trim((string)$data['password']) : '';

  if ($email === '' || $password === '') {
    http_response_code(422);
    throw new Exception('Faltan campos requeridos: email y password');
  }

  $stmt = $conn->prepare(
    'SELECT id, email, password_hash, nombre, role, activo
       FROM usuarios
      WHERE LOWER(email) = ?
      LIMIT 1'
  );
  if (!$stmt) {
    throw new Exception('Error preparando login: ' . $conn->error);
  }
  $stmt->bind_param('s', $email);
  if (!$stmt->execute()) {
    throw new Exception('Error ejecutando login: ' . $stmt->error);
  }
  $res = $stmt->get_result();
  $user = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  if (!$user || !password_verify($password, (string)$user['password_hash'])) {
    http_response_code(401);
    throw new Exception('Email o contraseña incorrectos');
  }

  if (intval($user['activo']) !== 1) {
    http_response_code(403);
    throw new Exception('Usuario desactivado. Contacte al administrador.');
  }

  $usuario_id = intval($user['id']);
  $role = (string)$user['role'];
  $tabs = fetch_user_tabs($conn, $usuario_id, $role);
  $clientes = fetch_user_clients($conn, $usuario_id, $role);
  $unidades = fetch_allowed_units($conn, $usuario_id, $role, $clientes);
  $clienteNombre = count($clientes) === 1
    ? $clientes[0]['nombre']
    : implode(', ', array_map(fn($c) => $c['nombre'], $clientes));

  $response['success'] = true;
  $response['message'] = 'Login exitoso';
  $response['user'] = [
    'id' => $usuario_id,
    'username' => (string)$user['email'],
    'nombre' => (string)$user['nombre'],
    'role' => $role,
    'cliente' => $clienteNombre,
    'clientes' => $clientes,
    'unidades' => $unidades,
    'tabs' => $tabs
  ];
  [$token, $expires_at] = jwt_encode_payload([
    'sub' => $usuario_id,
    'email' => strtolower((string)$user['email']),
    'role' => $role
  ]);
  $response['token'] = $token;
  $response['expires_at'] = $expires_at;

} catch (Exception $e) {
  if (http_response_code() < 400) {
    http_response_code(400);
  }
  $response['success'] = false;
  $response['message'] = $e->getMessage();
} finally {
  if (isset($conn) && $conn) {
    $conn->close();
  }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
