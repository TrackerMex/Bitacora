<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$response = [
  'success' => false,
  'message' => '',
  'user' => null
];

function normalize_slug($value) {
  $s = strtolower(trim((string)$value));
  $s = str_replace(
    ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã±', 'Ã¼'],
    ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'a', 'e', 'i', 'o', 'u', 'n', 'u'],
    $s
  );
  $s = preg_replace('/[^a-z0-9]+/', '-', $s);
  return trim((string)$s, '-');
}

function parse_tabs($value, $role) {
  $value = trim((string)$value);
  if ($value === '') {
    return strtolower((string)$role) === 'lector'
      ? [3, 4, 5, 6, 7]
      : [0, 1, 2, 3, 4, 5, 6, 7];
  }

  $tabs = [];
  foreach (explode(',', $value) as $part) {
    $tab = intval(trim($part));
    if ($tab >= 0 && $tab <= 7) {
      $tabs[] = $tab;
    }
  }
  return array_values(array_unique($tabs));
}

function get_input_value($key, $argvMap, $default = '') {
  if (PHP_SAPI === 'cli') {
    return $argvMap[$key] ?? $default;
  }
  return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function parse_cli_args($argv) {
  $map = [];
  foreach (array_slice($argv ?? [], 1) as $arg) {
    if (strpos($arg, '=') === false) continue;
    [$key, $value] = explode('=', $arg, 2);
    $map[ltrim($key, '-')] = $value;
  }
  return $map;
}

try {
  $argvMap = parse_cli_args($argv ?? []);

  $email = strtolower(trim((string)get_input_value('email', $argvMap)));
  $password = trim((string)get_input_value('password', $argvMap));
  $clienteNombre = trim((string)get_input_value('cliente', $argvMap));
  $nombre = trim((string)get_input_value('nombre', $argvMap, $clienteNombre));
  $role = strtolower(trim((string)get_input_value('role', $argvMap, 'editor')));
  $tabs = parse_tabs(get_input_value('tabs', $argvMap, ''), $role);

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Email inválido.');
  }
  if ($password === '' || strlen($password) < 8) {
    throw new Exception('Password requerido de al menos 8 caracteres.');
  }
  if ($clienteNombre === '') {
    throw new Exception('Cliente requerido.');
  }
  if (!in_array($role, ['admin', 'editor', 'lector'], true)) {
    throw new Exception('Role inválido. Use admin, editor o lector.');
  }

  require_once __DIR__ . '/../db/db.php';

  $clienteSlug = normalize_slug($clienteNombre);
  $stmtCliente = $conn->prepare(
    'SELECT id, nombre FROM clientes WHERE slug = ? OR nombre = ? LIMIT 1'
  );
  if (!$stmtCliente) {
    throw new Exception('Error preparando búsqueda de cliente: ' . $conn->error);
  }
  $stmtCliente->bind_param('ss', $clienteSlug, $clienteNombre);
  if (!$stmtCliente->execute()) {
    throw new Exception('Error buscando cliente: ' . $stmtCliente->error);
  }
  $resCliente = $stmtCliente->get_result();
  $cliente = $resCliente ? $resCliente->fetch_assoc() : null;
  $stmtCliente->close();

  if (!$cliente) {
    throw new Exception('Cliente no encontrado: ' . $clienteNombre);
  }

  $clienteId = intval($cliente['id']);
  $passwordHash = password_hash($password, PASSWORD_DEFAULT);

  $conn->begin_transaction();

  $stmtUser = $conn->prepare(
    "INSERT INTO usuarios (email, password_hash, nombre, role, activo)
     VALUES (?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE
       password_hash = VALUES(password_hash),
       nombre = VALUES(nombre),
       role = VALUES(role),
       activo = 1"
  );
  if (!$stmtUser) {
    throw new Exception('Error preparando usuario: ' . $conn->error);
  }
  $stmtUser->bind_param('ssss', $email, $passwordHash, $nombre, $role);
  if (!$stmtUser->execute()) {
    throw new Exception('Error guardando usuario: ' . $stmtUser->error);
  }
  $stmtUser->close();

  $stmtFindUser = $conn->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
  if (!$stmtFindUser) {
    throw new Exception('Error preparando consulta de usuario: ' . $conn->error);
  }
  $stmtFindUser->bind_param('s', $email);
  if (!$stmtFindUser->execute()) {
    throw new Exception('Error consultando usuario: ' . $stmtFindUser->error);
  }
  $resUser = $stmtFindUser->get_result();
  $user = $resUser ? $resUser->fetch_assoc() : null;
  $stmtFindUser->close();
  $usuarioId = intval($user['id'] ?? 0);
  if ($usuarioId <= 0) {
    throw new Exception('No se pudo resolver el usuario creado.');
  }

  $stmtRel = $conn->prepare(
    'INSERT IGNORE INTO usuario_clientes (usuario_id, cliente_id) VALUES (?, ?)'
  );
  if (!$stmtRel) {
    throw new Exception('Error preparando relación usuario-cliente: ' . $conn->error);
  }
  $stmtRel->bind_param('ii', $usuarioId, $clienteId);
  if (!$stmtRel->execute()) {
    throw new Exception('Error guardando relación usuario-cliente: ' . $stmtRel->error);
  }
  $stmtRel->close();

  $stmtDelTabs = $conn->prepare('DELETE FROM usuario_tabs WHERE usuario_id = ?');
  if (!$stmtDelTabs) {
    throw new Exception('Error preparando limpieza de tabs: ' . $conn->error);
  }
  $stmtDelTabs->bind_param('i', $usuarioId);
  if (!$stmtDelTabs->execute()) {
    throw new Exception('Error limpiando tabs: ' . $stmtDelTabs->error);
  }
  $stmtDelTabs->close();

  $stmtTab = $conn->prepare('INSERT INTO usuario_tabs (usuario_id, tab_index) VALUES (?, ?)');
  if (!$stmtTab) {
    throw new Exception('Error preparando tabs: ' . $conn->error);
  }
  foreach ($tabs as $tab) {
    $stmtTab->bind_param('ii', $usuarioId, $tab);
    if (!$stmtTab->execute()) {
      throw new Exception('Error guardando tabs: ' . $stmtTab->error);
    }
  }
  $stmtTab->close();

  $conn->commit();

  $response['success'] = true;
  $response['message'] = 'Usuario de cliente creado/actualizado correctamente.';
  $response['user'] = [
    'id' => $usuarioId,
    'email' => $email,
    'nombre' => $nombre,
    'role' => $role,
    'cliente_id' => $clienteId,
    'cliente' => (string)$cliente['nombre'],
    'tabs' => $tabs
  ];
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
