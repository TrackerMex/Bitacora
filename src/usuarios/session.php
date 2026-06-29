<?php

error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

$response = [
  'success' => false,
  'message' => '',
  'user' => null,
  'token' => null,
  'expires_at' => null
];

function default_tabs_for_role_session($role) {
  $role = strtolower(trim((string)$role));
  return $role === 'admin' ? [0, 1, 2, 3, 4, 5] : [0, 1, 2, 3, 4];
}

function fetch_user_tabs_session($conn, $usuario_id, $role) {
  $role = strtolower(trim((string)$role));
  if ($role === 'lector') {
    return default_tabs_for_role_session($role);
  }

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
  return count($tabs) ? $tabs : default_tabs_for_role_session($role);
}

function fetch_user_clients_session($conn, $usuario_id, $role) {
  $role = strtolower(trim((string)$role));
  if ($role === 'admin') {
    $res = $conn->query('SELECT id, nombre FROM clientes WHERE activo = 1 ORDER BY nombre ASC');
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

function fetch_allowed_units_session($conn, $usuario_id, $role, $clientes) {
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
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    throw new Exception('Metodo no permitido. Use GET.');
  }

  require_once __DIR__ . '/../db/db.php';
  require_once __DIR__ . '/../auth/jwt.php';

  $token = get_bearer_token();
  if ($token === '') {
    http_response_code(401);
    throw new Exception('Token requerido');
  }

  $payload = jwt_decode_payload($token);
  $email = strtolower(trim((string)($payload['email'] ?? '')));
  if ($email === '') {
    http_response_code(401);
    throw new Exception('Token invalido');
  }

  $stmt = $conn->prepare(
    'SELECT id, email, nombre, role, activo
       FROM usuarios
      WHERE LOWER(email) = ?
      LIMIT 1'
  );
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

  if (!$user || intval($user['activo']) !== 1) {
    http_response_code(401);
    throw new Exception('Sesion invalida');
  }

  $usuario_id = intval($user['id']);
  $role = strtolower(trim((string)$user['role']));
  if ($role === 'editor') {
    http_response_code(403);
    throw new Exception('El rol editor no tiene acceso a la bitácora. Use un usuario lector.');
  }
  $tabs = fetch_user_tabs_session($conn, $usuario_id, $role);
  $clientes = fetch_user_clients_session($conn, $usuario_id, $role);
  $unidades = fetch_allowed_units_session($conn, $usuario_id, $role, $clientes);
  $clienteNombre = count($clientes) === 1
    ? $clientes[0]['nombre']
    : implode(', ', array_map(fn($c) => $c['nombre'], $clientes));

  $response['success'] = true;
  $response['message'] = 'Sesion valida';
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
  [$new_token, $expires_at] = jwt_encode_payload([
    'sub' => $usuario_id,
    'email' => strtolower((string)$user['email']),
    'role' => $role
  ]);
  $response['token'] = $new_token;
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
