<?php

require_once __DIR__ . '/admin_common.php';

try {
  require_admin_user($conn);

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Metodo no permitido', 405);
  }

  $data = read_json_body();
  $cliente_nombre = clean_string($data['clienteNombre'] ?? '');
  $usuario_nombre = clean_string($data['usuarioNombre'] ?? '');
  $email = strtolower(clean_string($data['email'] ?? ''));
  $password = clean_string($data['passwordTemporal'] ?? '');
  $role = clean_string($data['role'] ?? 'editor') ?: 'editor';
  $activo = !isset($data['activo']) || $data['activo'] ? 1 : 0;
  $tabs = parse_admin_tabs($data['tabs'] ?? [], $role);

  if ($cliente_nombre === '') {
    throw new Exception('Nombre de cliente requerido', 400);
  }
  if ($password === '') {
    throw new Exception('Contraseña temporal requerida', 400);
  }

  $conn->begin_transaction();
  try {
    $cliente_id = get_or_create_cliente_admin($conn, $cliente_nombre, 1);
    $usuario_id = upsert_admin_user($conn, $email, $password, $usuario_nombre, $role, $activo, $tabs);
    bind_user_cliente_admin($conn, $usuario_id, $cliente_id);
    $conn->commit();
  } catch (Exception $e) {
    $conn->rollback();
    throw $e;
  }

  admin_json([
    'success' => true,
    'message' => 'Cliente y usuario guardados',
    'data' => [
      'clienteId' => $cliente_id,
      'usuarioId' => $usuario_id
    ]
  ]);
} catch (Exception $e) {
  $code = is_numeric($e->getCode()) && $e->getCode() >= 400 ? intval($e->getCode()) : 500;
  admin_error($e->getMessage(), $code);
} finally {
  if (isset($conn) && $conn) {
    $conn->close();
  }
}

?>
