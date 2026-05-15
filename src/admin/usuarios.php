<?php

require_once __DIR__ . '/admin_common.php';

try {
  require_admin_user($conn);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT u.id, u.email, u.nombre, u.role, u.activo, u.created_at,
                   GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', ') AS clientes,
                   GROUP_CONCAT(DISTINCT ut.tab_index ORDER BY ut.tab_index SEPARATOR ',') AS tabs
              FROM usuarios u
              LEFT JOIN usuario_clientes uc ON uc.usuario_id = u.id
              LEFT JOIN clientes c ON c.id = uc.cliente_id
              LEFT JOIN usuario_tabs ut ON ut.usuario_id = u.id
             GROUP BY u.id
             ORDER BY u.created_at DESC, u.id DESC";
    $res = $conn->query($sql);
    if (!$res) {
      throw new Exception('Error consultando usuarios: ' . $conn->error, 500);
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
      $rows[] = [
        'id' => intval($row['id']),
        'email' => (string)$row['email'],
        'nombre' => (string)$row['nombre'],
        'role' => (string)$row['role'],
        'activo' => intval($row['activo']),
        'clientes' => $row['clientes'] ? explode(', ', (string)$row['clientes']) : [],
        'tabs' => $row['tabs'] ? array_map('intval', explode(',', (string)$row['tabs'])) : [],
        'created_at' => (string)$row['created_at']
      ];
    }
    admin_json(['success' => true, 'data' => $rows]);
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Metodo no permitido', 405);
  }

  $data = read_json_body();
  $email = strtolower(clean_string($data['email'] ?? ''));
  $password = clean_string($data['passwordTemporal'] ?? $data['password'] ?? '');
  $nombre = clean_string($data['nombre'] ?? $data['usuarioNombre'] ?? '');
  $role = clean_string($data['role'] ?? 'editor');
  $activo = !isset($data['activo']) || $data['activo'] ? 1 : 0;
  $tabs = parse_admin_tabs($data['tabs'] ?? [], $role);
  $cliente_ids = is_array($data['clienteIds'] ?? null) ? $data['clienteIds'] : [];

  $conn->begin_transaction();
  try {
    $usuario_id = upsert_admin_user($conn, $email, $password, $nombre, $role, $activo, $tabs);
    if (count($cliente_ids)) {
      foreach ($cliente_ids as $cliente_id) {
        $id = intval($cliente_id);
        if ($id > 0) {
          bind_user_cliente_admin($conn, $usuario_id, $id);
        }
      }
    }
    $conn->commit();
  } catch (Exception $e) {
    $conn->rollback();
    throw $e;
  }

  admin_json(['success' => true, 'message' => 'Usuario guardado', 'data' => ['usuarioId' => $usuario_id]]);
} catch (Exception $e) {
  $code = is_numeric($e->getCode()) && $e->getCode() >= 400 ? intval($e->getCode()) : 500;
  admin_error($e->getMessage(), $code);
} finally {
  if (isset($conn) && $conn) {
    $conn->close();
  }
}

?>
