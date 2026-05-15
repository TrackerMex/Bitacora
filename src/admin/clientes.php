<?php

require_once __DIR__ . '/admin_common.php';

try {
  require_admin_user($conn);

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT c.id, c.nombre, c.slug, c.activo, c.created_at,
                   COUNT(DISTINCT u.id) AS total_unidades,
                   COUNT(DISTINCT uc.usuario_id) AS total_usuarios
              FROM clientes c
              LEFT JOIN unidades u ON u.cliente_id = c.id AND u.activo = 1
              LEFT JOIN usuario_clientes uc ON uc.cliente_id = c.id
             GROUP BY c.id
             ORDER BY c.nombre ASC";
    $res = $conn->query($sql);
    if (!$res) {
      throw new Exception('Error consultando clientes: ' . $conn->error, 500);
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
      $rows[] = [
        'id' => intval($row['id']),
        'nombre' => (string)$row['nombre'],
        'slug' => (string)$row['slug'],
        'activo' => intval($row['activo']),
        'total_unidades' => intval($row['total_unidades']),
        'total_usuarios' => intval($row['total_usuarios']),
        'created_at' => (string)$row['created_at']
      ];
    }
    admin_json(['success' => true, 'data' => $rows]);
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Metodo no permitido', 405);
  }

  $data = read_json_body();
  $id = intval($data['id'] ?? 0);
  $nombre = clean_string($data['nombre'] ?? '');
  $activo = !isset($data['activo']) || $data['activo'] ? 1 : 0;
  if ($nombre === '') {
    throw new Exception('Nombre de cliente requerido', 400);
  }

  if ($id > 0) {
    $stmt = $conn->prepare('UPDATE clientes SET nombre = ?, activo = ? WHERE id = ?');
    $stmt->bind_param('sii', $nombre, $activo, $id);
    if (!$stmt->execute()) {
      throw new Exception('Error actualizando cliente: ' . $stmt->error, 500);
    }
    $stmt->close();
    admin_json(['success' => true, 'message' => 'Cliente actualizado', 'data' => ['clienteId' => $id]]);
  }

  $cliente_id = get_or_create_cliente_admin($conn, $nombre, $activo);
  admin_json(['success' => true, 'message' => 'Cliente guardado', 'data' => ['clienteId' => $cliente_id]]);
} catch (Exception $e) {
  $code = is_numeric($e->getCode()) && $e->getCode() >= 400 ? intval($e->getCode()) : 500;
  admin_error($e->getMessage(), $code);
} finally {
  if (isset($conn) && $conn) {
    $conn->close();
  }
}

?>
