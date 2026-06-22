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

function resp_ok($data = []) {
  echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
  exit();
}

function resp_err($msg, $code = 400) {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit();
}

function read_json_body() {
  $body = json_decode(file_get_contents('php://input'), true);
  if (!is_array($body)) {
    resp_err('JSON inválido.', 400);
  }
  return $body;
}

function to_mysql_datetime_or_null($value) {
  $s = trim((string)($value ?? ''));
  if ($s === '') {
    return null;
  }
  if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $s)) {
    return str_replace('T', ' ', substr($s, 0, 16)) . ':00';
  }
  if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $s)) {
    return substr($s, 0, 16) . ':00';
  }
  try {
    $dt = new DateTime($s);
    return $dt->format('Y-m-d H:i:s');
  } catch (Exception $e) {
    return null;
  }
}

function str_or_null($value) {
  $s = trim((string)($value ?? ''));
  return $s === '' ? null : $s;
}

function bind_stmt_params($stmt, $types, $params) {
  if ($types === '') {
    return;
  }
  $refs = [$types];
  foreach ($params as $k => $v) {
    $refs[] = &$params[$k];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);
}

function get_current_user_or_fail($conn) {
  require_once __DIR__ . '/../auth/jwt.php';

  $token = get_bearer_token();
  if (!$token) {
    resp_err('Sesión requerida o expirada.', 401);
  }

  try {
    $payload = jwt_decode_payload($token);
  } catch (Exception $e) {
    resp_err('Sesión requerida o expirada.', 401);
  }

  $email = strtolower(trim((string)($payload['email'] ?? '')));
  if ($email === '') {
    resp_err('Token inválido.', 401);
  }

  $stmt = $conn->prepare(
    'SELECT id, role FROM usuarios WHERE LOWER(email) = ? AND activo = 1 LIMIT 1'
  );
  if (!$stmt) {
    resp_err('Error preparando usuario: ' . $conn->error, 500);
  }
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$user) {
    resp_err('Usuario no encontrado o inactivo.', 401);
  }
  if (strtolower((string)$user['role']) === 'lector') {
    resp_err('Sin permisos de escritura.', 403);
  }

  return [
    'id' => (int)$user['id'],
    'role' => strtolower((string)$user['role']),
  ];
}

function get_tramo_with_access_or_fail($conn, $tramo_id, $user) {
  if ($tramo_id <= 0) {
    resp_err('tramo_id requerido.', 400);
  }

  $stmt = $conn->prepare(
    'SELECT vt.*, v.cliente_id
       FROM viaje_tramos vt
       INNER JOIN viajes v ON v.id = vt.viaje_id
      WHERE vt.id = ?
      LIMIT 1'
  );
  if (!$stmt) {
    resp_err('Error preparando tramo: ' . $conn->error, 500);
  }
  $stmt->bind_param('i', $tramo_id);
  $stmt->execute();
  $tramo = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$tramo) {
    resp_err('Tramo no encontrado.', 404);
  }

  if ($user['role'] !== 'admin') {
    $cliente_id = (int)$tramo['cliente_id'];
    $usuario_id = (int)$user['id'];
    $stmt = $conn->prepare(
      'SELECT 1 FROM usuario_clientes WHERE usuario_id = ? AND cliente_id = ? LIMIT 1'
    );
    if (!$stmt) {
      resp_err('Error preparando acceso: ' . $conn->error, 500);
    }
    $stmt->bind_param('ii', $usuario_id, $cliente_id);
    $stmt->execute();
    $access = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$access) {
      resp_err('Sin acceso a ese viaje.', 403);
    }
  }

  return $tramo;
}

function format_tramo_response($tramo) {
  return [
    'id' => (int)$tramo['id'],
    'viaje_id' => (int)$tramo['viaje_id'],
    'estado' => (string)$tramo['estado'],
    'gps_estado' => $tramo['gps_estado'] !== null ? (string)$tramo['gps_estado'] : null,
    'gps_timestamp' => $tramo['gps_timestamp'] !== null ? (string)$tramo['gps_timestamp'] : null,
    'salida_patio_real' => $tramo['salida_patio_real'] !== null ? (string)$tramo['salida_patio_real'] : null,
    'cita_carga_real' => $tramo['cita_carga_real'] !== null ? (string)$tramo['cita_carga_real'] : null,
    'salida_carga_real' => $tramo['salida_carga_real'] !== null ? (string)$tramo['salida_carga_real'] : null,
    'descarga_real' => $tramo['descarga_real'] !== null ? (string)$tramo['descarga_real'] : null,
    'vacio_real' => $tramo['vacio_real'] !== null ? (string)$tramo['vacio_real'] : null,
  ];
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resp_err('Método no permitido. Use POST.', 405);
  }

  require_once __DIR__ . '/../db/db.php';

  $action = trim((string)($_GET['action'] ?? ''));
  if ($action === '') {
    resp_err('Acción requerida.', 400);
  }

  $user = get_current_user_or_fail($conn);
  $data = read_json_body();

  if ($action === 'agregar_incidencia') {
    $tramo_id = intval($data['tramo_id'] ?? 0);
    $tramo = get_tramo_with_access_or_fail($conn, $tramo_id, $user);

    $estado_tramo = (string)($tramo['estado'] ?? '');
    if ($estado_tramo === 'completado' || $estado_tramo === 'cancelado') {
      resp_err('El tramo ya está cerrado. Las incidencias quedan solo para consulta.', 409);
    }

    $tipo = str_or_null($data['tipo'] ?? '');
    $severidad = trim((string)($data['severidad'] ?? 'media'));
    $fecha = to_mysql_datetime_or_null($data['fecha'] ?? '');
    $direccion = str_or_null($data['direccion'] ?? '');
    $notas = str_or_null($data['notas'] ?? '');
    $viaje_id = (int)$tramo['viaje_id'];
    $usuario_id = (int)$user['id'];
    $severidades_validas = ['alta', 'media', 'baja'];

    if (!$tipo) {
      resp_err('Tipo de incidencia requerido.', 400);
    }
    if (!$fecha) {
      resp_err('Fecha de incidencia inválida.', 400);
    }
    if (!in_array($severidad, $severidades_validas, true)) {
      $severidad = 'media';
    }

    $stmt = $conn->prepare(
      'INSERT INTO viaje_incidencias
        (viaje_id, tramo_id, tipo, severidad, fecha, direccion, notas, creado_por)
       VALUES (?,?,?,?,?,?,?,?)'
    );
    if (!$stmt) {
      resp_err('Error preparando incidencia: ' . $conn->error, 500);
    }
    $stmt->bind_param(
      'iisssssi',
      $viaje_id,
      $tramo_id,
      $tipo,
      $severidad,
      $fecha,
      $direccion,
      $notas,
      $usuario_id
    );
    if (!$stmt->execute()) {
      resp_err('Error guardando incidencia: ' . $stmt->error, 500);
    }
    $incidencia_id = (int)$stmt->insert_id;
    $stmt->close();

    resp_ok([
      'incidencia' => [
        'id' => $incidencia_id,
        'viaje_id' => $viaje_id,
        'tramo_id' => $tramo_id,
        'tipo' => $tipo,
        'severidad' => $severidad,
        'fecha' => $fecha,
        'direccion' => $direccion,
        'notas' => $notas,
      ],
    ]);
  }

  if ($action === 'eliminar_incidencia') {
    $incidencia_id = intval($data['incidencia_id'] ?? 0);
    if ($incidencia_id <= 0) {
      resp_err('incidencia_id requerido.', 400);
    }

    $stmt = $conn->prepare(
      'SELECT vi.id, vi.tramo_id
         FROM viaje_incidencias vi
        WHERE vi.id = ?
        LIMIT 1'
    );
    if (!$stmt) {
      resp_err('Error preparando incidencia: ' . $conn->error, 500);
    }
    $stmt->bind_param('i', $incidencia_id);
    $stmt->execute();
    $inc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inc) {
      resp_err('Incidencia no encontrada.', 404);
    }

    get_tramo_with_access_or_fail($conn, (int)$inc['tramo_id'], $user);

    $stmt = $conn->prepare('DELETE FROM viaje_incidencias WHERE id = ?');
    if (!$stmt) {
      resp_err('Error preparando eliminación: ' . $conn->error, 500);
    }
    $stmt->bind_param('i', $incidencia_id);
    if (!$stmt->execute()) {
      resp_err('Error eliminando incidencia: ' . $stmt->error, 500);
    }
    $stmt->close();

    resp_ok(['incidencia_id' => $incidencia_id]);
  }

  if ($action === 'guardar_gps') {
    $tramo_id = intval($data['tramo_id'] ?? 0);
    get_tramo_with_access_or_fail($conn, $tramo_id, $user);

    $gps_estado = json_encode($data['gps_estado'] ?? [], JSON_UNESCAPED_UNICODE);
    if ($gps_estado === false) {
      resp_err('gps_estado inválido.', 400);
    }

    $stmt = $conn->prepare(
      'UPDATE viaje_tramos
          SET gps_estado = ?, gps_timestamp = NOW()
        WHERE id = ?'
    );
    if (!$stmt) {
      resp_err('Error preparando GPS: ' . $conn->error, 500);
    }
    $stmt->bind_param('si', $gps_estado, $tramo_id);
    if (!$stmt->execute()) {
      resp_err('Error guardando GPS: ' . $stmt->error, 500);
    }
    $stmt->close();

    resp_ok([
      'tramo_id' => $tramo_id,
      'gps_estado' => $gps_estado,
      'gps_timestamp' => date('Y-m-d H:i:s'),
    ]);
  }

  if ($action === 'actualizar_tramo') {
    $tramo_id = intval($data['tramo_id'] ?? 0);
    get_tramo_with_access_or_fail($conn, $tramo_id, $user);

    $campos_fecha = [
      'salida_patio_real',
      'cita_carga_real',
      'salida_carga_real',
      'descarga_real',
      'vacio_real',
    ];
    $campos = [];
    $types = '';
    $params = [];

    foreach ($campos_fecha as $campo) {
      if (array_key_exists($campo, $data)) {
        $campos[] = "$campo = ?";
        $types .= 's';
        $params[] = to_mysql_datetime_or_null($data[$campo]);
      }
    }

    if (array_key_exists('estado', $data)) {
      $estado = trim((string)$data['estado']);
      $estados_validos = ['pendiente', 'en_curso', 'completado', 'cancelado'];
      if (!in_array($estado, $estados_validos, true)) {
        resp_err('Estado de tramo inválido.', 400);
      }
      $campos[] = 'estado = ?';
      $types .= 's';
      $params[] = $estado;
    }

    if (count($campos) === 0) {
      resp_err('No hay campos válidos para actualizar.', 400);
    }

    $params[] = $tramo_id;
    $types .= 'i';
    $sql = 'UPDATE viaje_tramos SET ' . implode(', ', $campos) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
      resp_err('Error preparando actualización: ' . $conn->error, 500);
    }
    bind_stmt_params($stmt, $types, $params);
    if (!$stmt->execute()) {
      resp_err('Error actualizando tramo: ' . $stmt->error, 500);
    }
    $stmt->close();

    resp_ok(['tramo_id' => $tramo_id]);
  }

  if ($action === 'completar_tramo') {
    $tramo_id = intval($data['tramo_id'] ?? 0);
    $tramo = get_tramo_with_access_or_fail($conn, $tramo_id, $user);
    $viaje_id = (int)$tramo['viaje_id'];
    $tramo_numero = (int)$tramo['tramo_numero'];

    $conn->begin_transaction();
    try {
      $stmt = $conn->prepare(
        "UPDATE viaje_tramos SET estado = 'completado' WHERE id = ?"
      );
      if (!$stmt) {
        throw new Exception('Error preparando completar tramo: ' . $conn->error);
      }
      $stmt->bind_param('i', $tramo_id);
      if (!$stmt->execute()) {
        throw new Exception('Error completando tramo: ' . $stmt->error);
      }
      $stmt->close();

      $stmt = $conn->prepare(
        "SELECT id FROM viaje_tramos
          WHERE viaje_id = ?
            AND tramo_numero > ?
            AND estado = 'pendiente'
          ORDER BY tramo_numero ASC, id ASC
          LIMIT 1"
      );
      if (!$stmt) {
        throw new Exception('Error buscando siguiente tramo: ' . $conn->error);
      }
      $stmt->bind_param('ii', $viaje_id, $tramo_numero);
      $stmt->execute();
      $siguiente = $stmt->get_result()->fetch_assoc();
      $stmt->close();

      $tramo_activado = null;
      if ($siguiente) {
        $siguiente_id = (int)$siguiente['id'];
        $stmt = $conn->prepare(
          "UPDATE viaje_tramos SET estado = 'en_curso' WHERE id = ?"
        );
        if (!$stmt) {
          throw new Exception('Error preparando activar tramo: ' . $conn->error);
        }
        $stmt->bind_param('i', $siguiente_id);
        if (!$stmt->execute()) {
          throw new Exception('Error activando tramo: ' . $stmt->error);
        }
        $stmt->close();
        $tramo_activado = ['id' => $siguiente_id, 'estado' => 'en_curso'];
      }

      $conn->commit();
    } catch (Exception $e) {
      $conn->rollback();
      throw $e;
    }

    resp_ok([
      'tramo_completado' => ['id' => $tramo_id, 'estado' => 'completado'],
      'tramo_activado' => $tramo_activado,
    ]);
  }

  resp_err('Acción no soportada.', 404);
} catch (Exception $e) {
  error_log('[api/viajes] ERROR: ' . $e->getMessage());
  resp_err('Error: ' . $e->getMessage(), 500);
} finally {
  if (isset($conn) && $conn) {
    $conn->close();
  }
}

