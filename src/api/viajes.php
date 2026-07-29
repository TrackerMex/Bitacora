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

function tramo_field_has_value($value) {
  return trim((string)($value ?? '')) !== '';
}

function infer_tramo_estado_automatico($tramo, $campos_fecha_actualizados) {
  $estado_actual = (string)($tramo['estado'] ?? 'pendiente');
  if ($estado_actual === 'cancelado') {
    return $estado_actual;
  }

  if ($estado_actual === 'completado') {
    return $estado_actual;
  }

  $campos_inicio = [
    'salida_patio_real',
    'cita_carga_real',
    'salida_carga_real',
    'descarga_real',
    'vacio_real',
    'regreso_origen_real',
  ];
  foreach ($campos_inicio as $campo) {
    if (tramo_field_has_value($tramo[$campo] ?? null)) {
      return 'en_curso';
    }
  }

  return 'pendiente';
}

function activar_siguiente_tramo_pendiente($conn, $viaje_id, $tramo_numero) {
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
  if (!$stmt->execute()) {
    throw new Exception('Error ejecutando siguiente tramo: ' . $stmt->error);
  }
  $siguiente = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$siguiente) {
    return null;
  }

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

  return ['id' => $siguiente_id, 'estado' => 'en_curso'];
}

function sincronizar_estado_viaje_por_tramos($conn, $viaje_id) {
  $stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(estado = 'completado') AS completados,
        SUM(estado = 'en_curso') AS en_curso,
        SUM(estado = 'pendiente') AS pendientes
      FROM viaje_tramos
      WHERE viaje_id = ? AND estado != 'cancelado'"
  );
  if (!$stmt) {
    throw new Exception('Error preparando conteo de tramos: ' . $conn->error);
  }
  $stmt->bind_param('i', $viaje_id);
  if (!$stmt->execute()) {
    throw new Exception('Error contando tramos: ' . $stmt->error);
  }
  $resumen = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $total = (int)($resumen['total'] ?? 0);
  $completados = (int)($resumen['completados'] ?? 0);
  $en_curso = (int)($resumen['en_curso'] ?? 0);

  if ($total > 0 && $completados === $total) {
    $estado = 'completado';
  } elseif ($en_curso > 0 || $completados > 0) {
    $estado = 'en_curso';
  } else {
    $estado = 'planificado';
  }

  $stmt = $conn->prepare(
    "UPDATE viajes SET estado = ? WHERE id = ? AND estado != 'cancelado'"
  );
  if (!$stmt) {
    throw new Exception('Error preparando estado de viaje: ' . $conn->error);
  }
  $stmt->bind_param('si', $estado, $viaje_id);
  if (!$stmt->execute()) {
    throw new Exception('Error actualizando estado de viaje: ' . $stmt->error);
  }
  $stmt->close();

  return $estado;
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
    'requiere_regreso_origen' => (int)($tramo['requiere_regreso_origen'] ?? 0),
    'regreso_origen_programado' => $tramo['regreso_origen_programado'] !== null ? (string)$tramo['regreso_origen_programado'] : null,
    'regreso_origen_real' => $tramo['regreso_origen_real'] !== null ? (string)$tramo['regreso_origen_real'] : null,
    'operador_monitoreo' => $tramo['operador_monitoreo'] !== null ? (string)$tramo['operador_monitoreo'] : null,
  ];
}

function assert_admin_user($user) {
  if (($user['role'] ?? '') !== 'admin') {
    resp_err('Esta acción requiere rol administrador.', 403);
  }
}

function assert_solicitudes_edicion_table_bitacora($conn) {
  $res = $conn->query("SHOW TABLES LIKE 'solicitudes_edicion_tramo'");
  if (!$res || $res->num_rows === 0) {
    resp_err(
      'El módulo de solicitudes aún no está instalado. Aplica la migración 2026-07-28_solicitudes_edicion_tramo.sql.',
      503
    );
  }
}

function solicitud_edicion_campos_config() {
  return [
    'folio' => ['despacho' => 'folio', 'tramo' => null, 'datetime' => false, 'label' => 'Folio'],
    'ruta' => ['despacho' => 'ruta', 'tramo' => 'ruta', 'datetime' => false, 'label' => 'Ruta'],
    'origen' => ['despacho' => 'origen', 'tramo' => 'origen', 'datetime' => false, 'label' => 'Origen'],
    'lugar_carga' => ['despacho' => 'lugar_carga', 'tramo' => 'lugar_carga', 'datetime' => false, 'label' => 'Lugar de carga'],
    'destino' => ['despacho' => 'destino', 'tramo' => 'destino', 'datetime' => false, 'label' => 'Destino'],
    'instrucciones' => ['despacho' => 'instrucciones', 'tramo' => 'instrucciones', 'datetime' => false, 'label' => 'Instrucciones'],
    'salida_patio_programada' => ['despacho' => 'salida_patio_programada', 'tramo' => 'salida_patio', 'datetime' => true, 'label' => 'Inicio de ruta'],
    'cita_carga' => ['despacho' => 'cita_carga', 'tramo' => 'cita_carga', 'datetime' => true, 'label' => 'Cita de carga'],
    'salida_carga_programada' => ['despacho' => 'salida_carga_programada', 'tramo' => 'salida_carga', 'datetime' => true, 'label' => 'Salida de carga'],
    'descarga_programada' => ['despacho' => 'descarga_programada', 'tramo' => 'descarga_programada', 'datetime' => true, 'label' => 'Cita de descarga'],
  ];
}

function decode_json_array_or_empty($value) {
  $decoded = json_decode((string)($value ?? ''), true);
  return is_array($decoded) ? $decoded : [];
}

function normalize_solicitud_value($value, $datetime) {
  if ($datetime) {
    return to_mysql_datetime_or_null($value);
  }
  return str_or_null($value);
}

function fetch_solicitud_despacho($conn, $despacho_id, $for_update = false) {
  $sql =
    'SELECT d.id, d.cliente_id, d.unidad_id, d.folio, d.fecha_programada,
            d.tramo_numero, d.ruta, d.origen, d.lugar_carga, d.destino,
            d.instrucciones, d.salida_patio_programada, d.cita_carga,
            d.salida_carga_programada, d.descarga_programada,
            u.economico AS unidad, c.nombre AS cliente
       FROM despachos d
       JOIN unidades u ON u.id = d.unidad_id
       JOIN clientes c ON c.id = d.cliente_id
      WHERE d.id = ? AND d.eliminado_at IS NULL
      LIMIT 1' . ($for_update ? ' FOR UPDATE' : '');
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    throw new Exception('Error preparando despacho: ' . $conn->error, 500);
  }
  $stmt->bind_param('i', $despacho_id);
  $stmt->execute();
  $despacho = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $despacho ?: null;
}

function build_solicitud_response($row, $despacho = null) {
  $solicitados = decode_json_array_or_empty($row['campos_solicitados'] ?? null);
  $actuales = decode_json_array_or_empty($row['valores_actuales'] ?? null);
  $aplicados = decode_json_array_or_empty($row['valores_aplicados'] ?? null);
  $config = solicitud_edicion_campos_config();
  $campos = [];
  $tiene_conflicto = false;

  foreach ($solicitados as $campo => $valor_solicitado) {
    if (!isset($config[$campo])) {
      continue;
    }
    $valor_actual = $despacho
      ? ($despacho[$config[$campo]['despacho']] ?? null)
      : null;
    $valor_original = $actuales[$campo] ?? null;
    $conflicto = $despacho &&
      trim((string)$valor_actual) !== trim((string)$valor_original);
    $tiene_conflicto = $tiene_conflicto || $conflicto;
    $campos[] = [
      'campo' => $campo,
      'label' => $config[$campo]['label'],
      'valor_al_solicitar' => $valor_original,
      'valor_actual' => $valor_actual,
      'valor_solicitado' => $valor_solicitado,
      'valor_aplicado' => $aplicados[$campo] ?? null,
      'conflicto' => $conflicto,
    ];
  }

  return [
    'id' => (int)$row['id'],
    'despacho_id' => (int)$row['despacho_id'],
    'cliente_id' => (int)$row['cliente_id'],
    'estado' => (string)$row['estado'],
    'motivo' => (string)$row['motivo'],
    'comentario_admin' => $row['comentario_admin'] ?? null,
    'created_at' => $row['created_at'] ?? null,
    'reviewed_at' => $row['reviewed_at'] ?? null,
    'applied_at' => $row['applied_at'] ?? null,
    'solicitado_por' => $row['solicitado_por'] ?? 'Usuario eliminado',
    'revisado_por' => $row['revisado_por'] ?? null,
    'folio' => $despacho['folio'] ?? ($row['folio'] ?? null),
    'tramo_numero' => (int)($despacho['tramo_numero'] ?? ($row['tramo_numero'] ?? 0)),
    'unidad' => $despacho['unidad'] ?? ($row['unidad'] ?? null),
    'cliente' => $despacho['cliente'] ?? ($row['cliente'] ?? null),
    'campos' => $campos,
    'tiene_conflicto' => $tiene_conflicto,
  ];
}

function listar_solicitudes_edicion_admin($conn, $data) {
  $estado = trim((string)($data['estado'] ?? ''));
  $cliente_id = intval($data['cliente_id'] ?? 0);
  $estados = ['pendiente', 'en_revision', 'aplicada', 'rechazada', 'cancelada'];
  if ($estado !== '' && !in_array($estado, $estados, true)) {
    resp_err('Estado de solicitud inválido.', 400);
  }

  $where = ['1=1'];
  $types = '';
  $params = [];
  if ($estado !== '') {
    $where[] = 's.estado = ?';
    $types .= 's';
    $params[] = $estado;
  }
  if ($cliente_id > 0) {
    $where[] = 's.cliente_id = ?';
    $types .= 'i';
    $params[] = $cliente_id;
  }

  $sql =
    "SELECT s.*, us.nombre AS solicitado_por, ur.nombre AS revisado_por
       FROM solicitudes_edicion_tramo s
       LEFT JOIN usuarios us ON us.id = s.solicitado_por_usuario_id
       LEFT JOIN usuarios ur ON ur.id = s.revisado_por_usuario_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY FIELD(s.estado, 'pendiente', 'en_revision', 'aplicada', 'rechazada', 'cancelada'),
               s.created_at DESC
      LIMIT 250";
  $stmt = $conn->prepare($sql);
  if (!$stmt) {
    resp_err('Error preparando solicitudes: ' . $conn->error, 500);
  }
  bind_stmt_params($stmt, $types, $params);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($row = $res->fetch_assoc()) {
    $despacho = fetch_solicitud_despacho($conn, (int)$row['despacho_id']);
    $rows[] = build_solicitud_response($row, $despacho);
  }
  $stmt->close();
  resp_ok(['solicitudes' => $rows]);
}

function aplicar_solicitud_edicion_admin($conn, $user, $data) {
  $solicitud_id = intval($data['solicitud_id'] ?? 0);
  $comentario = str_or_null($data['comentario_admin'] ?? '');
  $confirmar_conflicto = !empty($data['confirmar_conflicto']);
  $valores_admin = $data['valores'] ?? [];
  if ($solicitud_id <= 0) {
    resp_err('solicitud_id requerido.', 400);
  }
  if (!is_array($valores_admin)) {
    resp_err('Los valores de aplicación son inválidos.', 400);
  }

  $conn->begin_transaction();
  try {
    $stmt = $conn->prepare(
      "SELECT * FROM solicitudes_edicion_tramo WHERE id = ? LIMIT 1 FOR UPDATE"
    );
    $stmt->bind_param('i', $solicitud_id);
    $stmt->execute();
    $solicitud = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$solicitud) {
      throw new Exception('Solicitud no encontrada.', 404);
    }
    if (!in_array($solicitud['estado'], ['pendiente', 'en_revision'], true)) {
      throw new Exception('La solicitud ya fue resuelta.', 409);
    }

    $despacho = fetch_solicitud_despacho(
      $conn,
      (int)$solicitud['despacho_id'],
      true
    );
    if (!$despacho) {
      throw new Exception('El tramo solicitado ya no está disponible.', 409);
    }

    $config = solicitud_edicion_campos_config();
    $solicitados = decode_json_array_or_empty($solicitud['campos_solicitados']);
    $originales = decode_json_array_or_empty($solicitud['valores_actuales']);
    $aplicar = [];
    $conflictos = [];
    foreach ($solicitados as $campo => $valor_solicitado) {
      if (!isset($config[$campo])) {
        continue;
      }
      $valor_actual = $despacho[$config[$campo]['despacho']] ?? null;
      if (trim((string)$valor_actual) !== trim((string)($originales[$campo] ?? null))) {
        $conflictos[] = $config[$campo]['label'];
      }
      $valor_final = array_key_exists($campo, $valores_admin)
        ? $valores_admin[$campo]
        : $valor_solicitado;
      $valor_final = normalize_solicitud_value(
        $valor_final,
        $config[$campo]['datetime']
      );
      if ($campo === 'folio' && $valor_final === null) {
        throw new Exception('El folio no puede quedar vacío.', 400);
      }
      $aplicar[$campo] = $valor_final;
    }
    if (!count($aplicar)) {
      throw new Exception('La solicitud no contiene campos aplicables.', 400);
    }
    if (count($conflictos) && !$confirmar_conflicto) {
      throw new Exception(
        'Hay cambios posteriores en: ' . implode(', ', $conflictos) .
        '. Revisa los valores y confirma el conflicto.',
        409
      );
    }

    $folio_viejo = (string)$despacho['folio'];
    $folio_nuevo = array_key_exists('folio', $aplicar)
      ? (string)$aplicar['folio']
      : $folio_viejo;
    if ($folio_nuevo !== $folio_viejo) {
      $stmt = $conn->prepare(
        'SELECT id FROM despachos
          WHERE cliente_id = ? AND unidad_id = ? AND folio = ?
            AND eliminado_at IS NULL AND id <> ?
          LIMIT 1'
      );
      $cliente_id = (int)$despacho['cliente_id'];
      $unidad_id = (int)$despacho['unidad_id'];
      $despacho_id = (int)$despacho['id'];
      $stmt->bind_param('iisi', $cliente_id, $unidad_id, $folio_nuevo, $despacho_id);
      $stmt->execute();
      $duplicado = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      if ($duplicado) {
        throw new Exception("El folio '$folio_nuevo' ya existe para esta unidad.", 409);
      }
    }

    $sets_despacho = [];
    $params_despacho = [];
    $types_despacho = '';
    $sets_tramo = [];
    $params_tramo = [];
    $types_tramo = '';
    foreach ($aplicar as $campo => $valor) {
      if ($campo === 'folio') {
        continue;
      }
      $sets_despacho[] = $config[$campo]['despacho'] . ' = ?';
      $params_despacho[] = $valor;
      $types_despacho .= 's';
      if ($config[$campo]['tramo']) {
        $sets_tramo[] = $config[$campo]['tramo'] . ' = ?';
        $params_tramo[] = $valor;
        $types_tramo .= 's';
      }
    }

    if (count($sets_despacho)) {
      $params_despacho[] = (int)$despacho['id'];
      $types_despacho .= 'i';
      $stmt = $conn->prepare(
        'UPDATE despachos SET ' . implode(', ', $sets_despacho) . ' WHERE id = ?'
      );
      bind_stmt_params($stmt, $types_despacho, $params_despacho);
      if (!$stmt->execute()) {
        throw new Exception('Error actualizando despacho: ' . $stmt->error, 500);
      }
      $stmt->close();
    }

    $stmt = $conn->prepare(
      "SELECT vt.id
         FROM viaje_tramos vt
         JOIN viajes v ON v.id = vt.viaje_id
        WHERE v.cliente_id = ? AND v.unidad_id = ? AND v.folio = ?
          AND vt.tramo_numero = ? AND v.estado <> 'cancelado'"
    );
    $cliente_id = (int)$despacho['cliente_id'];
    $unidad_id = (int)$despacho['unidad_id'];
    $tramo_numero = (int)$despacho['tramo_numero'];
    $stmt->bind_param('iisi', $cliente_id, $unidad_id, $folio_viejo, $tramo_numero);
    $stmt->execute();
    $res = $stmt->get_result();
    $tramo_ids = [];
    while ($row = $res->fetch_assoc()) {
      $tramo_ids[] = (int)$row['id'];
    }
    $stmt->close();

    if (count($sets_tramo)) {
      foreach ($tramo_ids as $tramo_id) {
        $params = $params_tramo;
        $params[] = $tramo_id;
        $stmt = $conn->prepare(
          'UPDATE viaje_tramos SET ' . implode(', ', $sets_tramo) . ' WHERE id = ?'
        );
        bind_stmt_params($stmt, $types_tramo . 'i', $params);
        if (!$stmt->execute()) {
          throw new Exception('Error sincronizando tramo: ' . $stmt->error, 500);
        }
        $stmt->close();
      }
    }

    if ($folio_nuevo !== $folio_viejo) {
      $stmt = $conn->prepare(
        'UPDATE despachos SET folio = ?
          WHERE cliente_id = ? AND unidad_id = ? AND folio = ?
            AND eliminado_at IS NULL'
      );
      $stmt->bind_param('siis', $folio_nuevo, $cliente_id, $unidad_id, $folio_viejo);
      if (!$stmt->execute()) {
        throw new Exception('Error actualizando folio en despachos: ' . $stmt->error, 500);
      }
      $stmt->close();

      $stmt = $conn->prepare(
        "UPDATE viajes SET folio = ?
          WHERE cliente_id = ? AND unidad_id = ? AND folio = ?
            AND estado <> 'cancelado'"
      );
      $stmt->bind_param('siis', $folio_nuevo, $cliente_id, $unidad_id, $folio_viejo);
      if (!$stmt->execute()) {
        throw new Exception('Error actualizando folio en viajes: ' . $stmt->error, 500);
      }
      $stmt->close();
    }

    $aplicados_json = json_encode($aplicar, JSON_UNESCAPED_UNICODE);
    if ($aplicados_json === false) {
      throw new Exception('No se pudo registrar la auditoría de cambios.', 500);
    }
    $revisor_id = (int)$user['id'];
    $stmt = $conn->prepare(
      "UPDATE solicitudes_edicion_tramo
          SET estado = 'aplicada', valores_aplicados = ?,
              comentario_admin = ?, revisado_por_usuario_id = ?,
              reviewed_at = NOW(), applied_at = NOW()
        WHERE id = ?"
    );
    $stmt->bind_param(
      'ssii',
      $aplicados_json,
      $comentario,
      $revisor_id,
      $solicitud_id
    );
    if (!$stmt->execute()) {
      throw new Exception('Error cerrando solicitud: ' . $stmt->error, 500);
    }
    $stmt->close();
    $conn->commit();
    resp_ok([
      'solicitud_id' => $solicitud_id,
      'estado' => 'aplicada',
      'tramos_sincronizados' => count($tramo_ids),
    ]);
  } catch (Exception $e) {
    $conn->rollback();
    $code = (int)$e->getCode();
    resp_err($e->getMessage(), $code >= 400 && $code <= 599 ? $code : 500);
  }
}

function cambiar_estado_solicitud_edicion_admin($conn, $user, $data) {
  $solicitud_id = intval($data['solicitud_id'] ?? 0);
  $estado = trim((string)($data['estado'] ?? ''));
  $comentario = str_or_null($data['comentario_admin'] ?? '');
  if ($solicitud_id <= 0) {
    resp_err('solicitud_id requerido.', 400);
  }
  if (!in_array($estado, ['en_revision', 'rechazada'], true)) {
    resp_err('Estado administrativo inválido.', 400);
  }
  if ($estado === 'rechazada' && !$comentario) {
    resp_err('Indica el motivo del rechazo.', 400);
  }

  $revisor_id = (int)$user['id'];
  $stmt = $conn->prepare(
    "UPDATE solicitudes_edicion_tramo
        SET estado = ?, comentario_admin = ?, revisado_por_usuario_id = ?,
            reviewed_at = NOW()
      WHERE id = ? AND estado IN ('pendiente', 'en_revision')"
  );
  $stmt->bind_param('ssii', $estado, $comentario, $revisor_id, $solicitud_id);
  if (!$stmt->execute()) {
    resp_err('Error actualizando solicitud: ' . $stmt->error, 500);
  }
  if ($stmt->affected_rows === 0) {
    $stmt->close();
    resp_err('La solicitud ya fue resuelta o no existe.', 409);
  }
  $stmt->close();
  resp_ok(['solicitud_id' => $solicitud_id, 'estado' => $estado]);
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

  if (in_array($action, [
    'contar_solicitudes_edicion',
    'listar_solicitudes_edicion',
    'aplicar_solicitud_edicion',
    'cambiar_estado_solicitud_edicion',
  ], true)) {
    assert_admin_user($user);
    assert_solicitudes_edicion_table_bitacora($conn);
  }

  if ($action === 'contar_solicitudes_edicion') {
    $res = $conn->query(
      "SELECT COUNT(*) AS total
         FROM solicitudes_edicion_tramo
        WHERE estado IN ('pendiente', 'en_revision')"
    );
    if (!$res) {
      resp_err('Error contando solicitudes: ' . $conn->error, 500);
    }
    $row = $res->fetch_assoc();
    resp_ok(['total' => (int)($row['total'] ?? 0)]);
  }

  if ($action === 'listar_solicitudes_edicion') {
    listar_solicitudes_edicion_admin($conn, $data);
  }

  if ($action === 'aplicar_solicitud_edicion') {
    aplicar_solicitud_edicion_admin($conn, $user, $data);
  }

  if ($action === 'cambiar_estado_solicitud_edicion') {
    cambiar_estado_solicitud_edicion_admin($conn, $user, $data);
  }

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
        'tramo_numero' => (int)($tramo['tramo_numero'] ?? 0),
        'tramo_origen' => $tramo['origen'] !== null ? (string)$tramo['origen'] : null,
        'tramo_destino' => $tramo['destino'] !== null ? (string)$tramo['destino'] : null,
        'ruta_tramo' => $tramo['ruta'] !== null ? (string)$tramo['ruta'] : null,
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

    $gps_timestamp = date('Y-m-d H:i:s');

    resp_ok([
      'tramo_id' => $tramo_id,
      'gps_estado' => $gps_estado,
      'gps_timestamp' => $gps_timestamp,
      'tramo' => [
        'id' => $tramo_id,
        'gps_estado' => $gps_estado,
        'gps_timestamp' => $gps_timestamp,
      ],
    ]);
  }

  if ($action === 'actualizar_tramo') {
    $tramo_id = intval($data['tramo_id'] ?? 0);
    $tramo = get_tramo_with_access_or_fail($conn, $tramo_id, $user);
    $estado_anterior = (string)($tramo['estado'] ?? 'pendiente');

    $campos_fecha = [
      'salida_patio_real',
      'cita_carga_real',
      'salida_carga_real',
      'descarga_real',
      'vacio_real',
      'regreso_origen_real',
    ];
    $campos = [];
    $types = '';
    $params = [];
    $campos_fecha_actualizados = [];
    $estado_auto = null;
    $estado_explicito = false;

    foreach ($campos_fecha as $campo) {
      if (array_key_exists($campo, $data)) {
        $valor_fecha = to_mysql_datetime_or_null($data[$campo]);
        $campos[] = "$campo = ?";
        $types .= 's';
        $params[] = $valor_fecha;
        $tramo[$campo] = $valor_fecha;
        $campos_fecha_actualizados[] = $campo;
      }
    }

    if (array_key_exists('estado', $data)) {
      $estado_explicito = true;
      $estado = trim((string)$data['estado']);
      $estados_validos = ['pendiente', 'en_curso', 'completado', 'cancelado'];
      if (!in_array($estado, $estados_validos, true)) {
        resp_err('Estado de tramo inválido.', 400);
      }
      $campos[] = 'estado = ?';
      $types .= 's';
      $params[] = $estado;
      $tramo['estado'] = $estado;
    }

    if (array_key_exists('operador_monitoreo', $data)) {
      $operador_monitoreo = str_or_null($data['operador_monitoreo']);
      if ($operador_monitoreo !== null) {
        $operadores_validos = ['GEO-01', 'GEO-02', 'GEO-03', 'GEO-04', 'GEO-05', 'GEO-06'];
        if (!in_array($operador_monitoreo, $operadores_validos, true)) {
          resp_err('Operador de monitoreo inválido.', 400);
        }
      }
      $campos[] = 'operador_monitoreo = ?';
      $types .= 's';
      $params[] = $operador_monitoreo;
    }

    if (!$estado_explicito && count($campos_fecha_actualizados) > 0) {
      $estado_auto = infer_tramo_estado_automatico($tramo, $campos_fecha_actualizados);
      if ($estado_auto !== (string)$tramo['estado']) {
        $campos[] = 'estado = ?';
        $types .= 's';
        $params[] = $estado_auto;
        $tramo['estado'] = $estado_auto;
      }
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

    $tramo_activado = null;
    if ($estado_auto === 'completado' && $estado_anterior !== 'completado') {
      try {
        $tramo_activado = activar_siguiente_tramo_pendiente(
          $conn,
          (int)$tramo['viaje_id'],
          (int)$tramo['tramo_numero']
        );
      } catch (Exception $e) {
        resp_err($e->getMessage(), 500);
      }
    }

    $viaje_estado = sincronizar_estado_viaje_por_tramos($conn, (int)$tramo['viaje_id']);

    resp_ok([
      'tramo_id' => $tramo_id,
      'tramo' => format_tramo_response($tramo),
      'tramo_activado' => $tramo_activado,
      'viaje_estado' => $viaje_estado,
    ]);
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

      $tramo_activado = activar_siguiente_tramo_pendiente(
        $conn,
        $viaje_id,
        $tramo_numero
      );
      $viaje_estado = sincronizar_estado_viaje_por_tramos($conn, $viaje_id);

      $conn->commit();
    } catch (Exception $e) {
      $conn->rollback();
      throw $e;
    }

    resp_ok([
      'tramo_completado' => ['id' => $tramo_id, 'estado' => 'completado'],
      'tramo_activado' => $tramo_activado,
      'viaje_estado' => $viaje_estado,
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

