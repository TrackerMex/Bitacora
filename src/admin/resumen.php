<?php

require_once __DIR__ . '/admin_common.php';

try {
  require_admin_user($conn);

  $clientes_activos = intval($conn->query('SELECT COUNT(*) AS total FROM clientes WHERE activo = 1')->fetch_assoc()['total'] ?? 0);
  $usuarios_activos = intval($conn->query('SELECT COUNT(*) AS total FROM usuarios WHERE activo = 1')->fetch_assoc()['total'] ?? 0);
  $unidades_activas = intval($conn->query('SELECT COUNT(*) AS total FROM unidades WHERE activo = 1')->fetch_assoc()['total'] ?? 0);

  $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM despachos WHERE fecha_programada = CURDATE()');
  if (!$stmt) {
    throw new Exception('Error preparando despachos del dia: ' . $conn->error, 500);
  }
  $stmt->execute();
  $despachos_hoy = intval(($stmt->get_result()->fetch_assoc()['total'] ?? 0));
  $stmt->close();

  $ultimos_clientes = [];
  $res = $conn->query('SELECT id, nombre, activo, created_at FROM clientes ORDER BY created_at DESC, id DESC LIMIT 5');
  while ($row = $res->fetch_assoc()) {
    $ultimos_clientes[] = [
      'id' => intval($row['id']),
      'nombre' => (string)$row['nombre'],
      'activo' => intval($row['activo']),
      'created_at' => (string)$row['created_at']
    ];
  }

  $ultimos_usuarios = [];
  $res = $conn->query('SELECT id, email, nombre, role, activo, created_at FROM usuarios ORDER BY created_at DESC, id DESC LIMIT 5');
  while ($row = $res->fetch_assoc()) {
    $ultimos_usuarios[] = [
      'id' => intval($row['id']),
      'email' => (string)$row['email'],
      'nombre' => (string)$row['nombre'],
      'role' => (string)$row['role'],
      'activo' => intval($row['activo']),
      'created_at' => (string)$row['created_at']
    ];
  }

  admin_json([
    'success' => true,
    'data' => [
      'clientesActivos' => $clientes_activos,
      'usuariosActivos' => $usuarios_activos,
      'unidadesActivas' => $unidades_activas,
      'despachosHoy' => $despachos_hoy,
      'ultimosClientes' => $ultimos_clientes,
      'ultimosUsuarios' => $ultimos_usuarios
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
