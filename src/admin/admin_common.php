<?php

error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../auth/jwt.php';

function admin_json($payload, $code = 200) {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit();
}

function admin_error($message, $code = 400) {
  admin_json(['success' => false, 'message' => $message], $code);
}

function clean_string($value) {
  return trim((string)($value ?? ''));
}

function slugify_cliente_admin($value) {
  $slug = strtolower(clean_string($value));
  $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
  $slug = trim($slug, '-');
  return $slug !== '' ? $slug : 'cliente';
}

function read_json_body() {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    throw new Exception('Body JSON invalido', 400);
  }
  return $data;
}

function parse_admin_tabs($raw, $role = 'editor') {
  if (is_array($raw)) {
    $parts = $raw;
  } else {
    $parts = explode(',', clean_string($raw));
  }
  $tabs = [];
  foreach ($parts as $part) {
    $v = intval($part);
    if ($v >= 0 && !in_array($v, $tabs, true)) {
      $tabs[] = $v;
    }
  }
  if (count($tabs)) {
    sort($tabs);
    return $tabs;
  }
  return strtolower(clean_string($role)) === 'lector'
    ? [3, 4, 5, 6, 7]
    : [0, 1, 2, 3, 4, 5, 6, 7];
}

function require_admin_user($conn) {
  $token = get_bearer_token();
  if ($token === '') {
    throw new Exception('Sesion requerida', 401);
  }
  $payload = jwt_decode_payload($token);
  $email = strtolower(clean_string($payload['email'] ?? ''));
  if ($email === '') {
    throw new Exception('Token invalido', 401);
  }

  $stmt = $conn->prepare('SELECT id, email, nombre, role, activo FROM usuarios WHERE LOWER(email) = ? LIMIT 1');
  if (!$stmt) {
    throw new Exception('Error preparando validacion admin: ' . $conn->error, 500);
  }
  $stmt->bind_param('s', $email);
  if (!$stmt->execute()) {
    throw new Exception('Error validando admin: ' . $stmt->error, 500);
  }
  $res = $stmt->get_result();
  $user = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  if (!$user || intval($user['activo']) !== 1) {
    throw new Exception('Usuario no autorizado', 401);
  }
  if (strtolower((string)$user['role']) !== 'admin') {
    throw new Exception('Permisos de administrador requeridos', 403);
  }

  return [
    'id' => intval($user['id']),
    'email' => (string)$user['email'],
    'nombre' => (string)$user['nombre'],
    'role' => (string)$user['role']
  ];
}

function get_or_create_cliente_admin($conn, $nombre, $activo = 1) {
  $nombre = clean_string($nombre);
  if ($nombre === '') {
    throw new Exception('Nombre de cliente requerido', 400);
  }

  $stmt = $conn->prepare('SELECT id FROM clientes WHERE nombre = ? LIMIT 1');
  if (!$stmt) {
    throw new Exception('Error preparando cliente: ' . $conn->error, 500);
  }
  $stmt->bind_param('s', $nombre);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if ($row) {
    $cliente_id = intval($row['id']);
    $stmt = $conn->prepare('UPDATE clientes SET activo = ? WHERE id = ?');
    $stmt->bind_param('ii', $activo, $cliente_id);
    $stmt->execute();
    $stmt->close();
    return $cliente_id;
  }

  $base_slug = slugify_cliente_admin($nombre);
  $slug = $base_slug;
  $n = 2;
  while (true) {
    $stmt = $conn->prepare('SELECT id FROM clientes WHERE slug = ? LIMIT 1');
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    if (!$exists) {
      break;
    }
    $slug = $base_slug . '-' . $n;
    $n++;
  }

  $stmt = $conn->prepare('INSERT INTO clientes (nombre, slug, activo) VALUES (?, ?, ?)');
  if (!$stmt) {
    throw new Exception('Error preparando alta de cliente: ' . $conn->error, 500);
  }
  $stmt->bind_param('ssi', $nombre, $slug, $activo);
  if (!$stmt->execute()) {
    throw new Exception('Error creando cliente: ' . $stmt->error, 500);
  }
  $id = intval($stmt->insert_id);
  $stmt->close();
  return $id;
}

function upsert_admin_user($conn, $email, $password, $nombre, $role, $activo, $tabs) {
  $email = strtolower(clean_string($email));
  $nombre = clean_string($nombre);
  $role = strtolower(clean_string($role ?: 'editor'));
  if (!in_array($role, ['admin', 'editor', 'lector'], true)) {
    throw new Exception('Rol invalido', 400);
  }
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new Exception('Email invalido', 400);
  }
  if ($nombre === '') {
    $nombre = explode('@', $email)[0];
  }

  $password = clean_string($password);
  if ($password !== '') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
      'INSERT INTO usuarios (email, password_hash, nombre, role, activo)
       VALUES (?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         password_hash = VALUES(password_hash),
         nombre = VALUES(nombre),
         role = VALUES(role),
         activo = VALUES(activo)'
    );
    if (!$stmt) {
      throw new Exception('Error preparando usuario: ' . $conn->error, 500);
    }
    $stmt->bind_param('ssssi', $email, $hash, $nombre, $role, $activo);
  } else {
    $stmt = $conn->prepare(
      'INSERT INTO usuarios (email, password_hash, nombre, role, activo)
       VALUES (?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE
         nombre = VALUES(nombre),
         role = VALUES(role),
         activo = VALUES(activo)'
    );
    if (!$stmt) {
      throw new Exception('Error preparando usuario: ' . $conn->error, 500);
    }
    $empty_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt->bind_param('ssssi', $email, $empty_hash, $nombre, $role, $activo);
  }
  if (!$stmt->execute()) {
    throw new Exception('Error guardando usuario: ' . $stmt->error, 500);
  }
  $stmt->close();

  $stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!$row) {
    throw new Exception('No se pudo resolver el usuario guardado', 500);
  }
  $usuario_id = intval($row['id']);

  $stmt = $conn->prepare('DELETE FROM usuario_tabs WHERE usuario_id = ?');
  $stmt->bind_param('i', $usuario_id);
  $stmt->execute();
  $stmt->close();

  $stmt = $conn->prepare('INSERT INTO usuario_tabs (usuario_id, tab_index) VALUES (?, ?)');
  foreach ($tabs as $tab) {
    $tab_int = intval($tab);
    $stmt->bind_param('ii', $usuario_id, $tab_int);
    $stmt->execute();
  }
  $stmt->close();

  return $usuario_id;
}

function bind_user_cliente_admin($conn, $usuario_id, $cliente_id) {
  $stmt = $conn->prepare('INSERT IGNORE INTO usuario_clientes (usuario_id, cliente_id) VALUES (?, ?)');
  if (!$stmt) {
    throw new Exception('Error preparando relacion usuario-cliente: ' . $conn->error, 500);
  }
  $stmt->bind_param('ii', $usuario_id, $cliente_id);
  if (!$stmt->execute()) {
    throw new Exception('Error vinculando usuario-cliente: ' . $stmt->error, 500);
  }
  $stmt->close();
}

?>
