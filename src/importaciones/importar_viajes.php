<?php

error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 180);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

register_shutdown_function(function () {
  $error = error_get_last();
  $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
  if (!$error || !in_array($error['type'], $fatal_types, true)) {
    return;
  }
  error_log('[importar_viajes] FATAL: ' . ($error['message'] ?? 'Error fatal'));
  if (!headers_sent()) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'success' => false,
      'message' => 'Error interno al importar. Revisa el log del servidor.',
      'data' => null
    ], JSON_UNESCAPED_UNICODE);
  }
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

require_once __DIR__ . '/../admin/admin_common.php';

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 180);
set_time_limit(180);

$response = [
  'success' => false,
  'message' => '',
  'data' => null
];

function import_json($payload, $code = 200) {
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit();
}

function import_error($message, $code = 400) {
  import_json(['success' => false, 'message' => $message, 'data' => null], $code);
}

function import_clean($value) {
  return trim((string)($value ?? ''));
}

function import_bool($value) {
  if (is_bool($value)) return $value ? 1 : 0;
  $s = strtolower(import_clean($value));
  return in_array($s, ['1', 'si', 'sí', 'true', 'yes', 'x'], true) ? 1 : 0;
}

function import_generated_folio($row, $fecha_inicio) {
  $unidad = import_clean($row['unidad'] ?? '');
  $ruta = import_clean($row['ruta'] ?? '');
  $seed = implode('|', [
    $unidad,
    $fecha_inicio,
    $ruta,
    import_clean($row['origen'] ?? ''),
    import_clean($row['destino'] ?? '')
  ]);
  return 'IMP-' . preg_replace('/[^A-Za-z0-9]+/', '', $unidad ?: 'UNIDAD') . '-' . str_replace('-', '', $fecha_inicio) . '-' . substr(sha1($seed), 0, 6);
}

function import_bind($stmt, $types, $params) {
  if ($types === '') return;
  $refs = [$types];
  foreach ($params as $k => $v) {
    $refs[] = &$params[$k];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);
}

function import_column_exists($conn, $table, $column) {
  static $cache = [];
  $key = $table . '.' . $column;
  if (array_key_exists($key, $cache)) {
    return $cache[$key];
  }
  $stmt = $conn->prepare(
    'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
      LIMIT 1'
  );
  if (!$stmt) {
    $cache[$key] = false;
    return false;
  }
  $stmt->bind_param('ss', $table, $column);
  if (!$stmt->execute()) {
    $stmt->close();
    $cache[$key] = false;
    return false;
  }
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $cache[$key] = (bool)$row;
  return $cache[$key];
}

function import_table_exists($conn, $table) {
  static $cache = [];
  if (array_key_exists($table, $cache)) {
    return $cache[$table];
  }
  $stmt = $conn->prepare(
    'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
      LIMIT 1'
  );
  if (!$stmt) {
    $cache[$table] = false;
    return false;
  }
  $stmt->bind_param('s', $table);
  if (!$stmt->execute()) {
    $stmt->close();
    $cache[$table] = false;
    return false;
  }
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  $cache[$table] = (bool)$row;
  return $cache[$table];
}

function import_ensure_tables($conn) {
  $conn->query(
    "CREATE TABLE IF NOT EXISTS import_plantillas_excel (
      id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      cliente_id INT(10) UNSIGNED NOT NULL,
      nombre VARCHAR(120) NOT NULL DEFAULT 'Plantilla principal',
      mapeo_json JSON NOT NULL,
      encabezados_json JSON NULL,
      activo TINYINT(1) NOT NULL DEFAULT 1,
      created_by_usuario_id INT(10) UNSIGNED NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY unique_import_plantilla_cliente_nombre (cliente_id, nombre),
      KEY idx_import_plantillas_cliente_activo (cliente_id, activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
  $conn->query(
    "CREATE TABLE IF NOT EXISTS import_lotes (
      id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      cliente_id INT(10) UNSIGNED NOT NULL,
      plantilla_id INT(10) UNSIGNED NULL,
      usuario_id INT(10) UNSIGNED NULL,
      archivo_nombre VARCHAR(255) NULL,
      hoja_nombre VARCHAR(120) NULL,
      total_filas INT(10) UNSIGNED NOT NULL DEFAULT 0,
      filas_validas INT(10) UNSIGNED NOT NULL DEFAULT 0,
      filas_error INT(10) UNSIGNED NOT NULL DEFAULT 0,
      filas_duplicadas INT(10) UNSIGNED NOT NULL DEFAULT 0,
      viajes_creados INT(10) UNSIGNED NOT NULL DEFAULT 0,
      tramos_creados INT(10) UNSIGNED NOT NULL DEFAULT 0,
      estado VARCHAR(30) NOT NULL DEFAULT 'preview',
      resumen_json JSON NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_import_lotes_cliente_fecha (cliente_id, created_at),
      KEY idx_import_lotes_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
  $conn->query(
    "CREATE TABLE IF NOT EXISTS import_lote_errores (
      id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
      lote_id INT(10) UNSIGNED NOT NULL,
      fila INT(10) UNSIGNED NOT NULL DEFAULT 0,
      campo VARCHAR(80) NULL,
      mensaje VARCHAR(255) NOT NULL,
      valor_original TEXT NULL,
      severidad VARCHAR(20) NOT NULL DEFAULT 'error',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_import_lote_errores_lote (lote_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

function import_cliente_exists($conn, $cliente_id) {
  $stmt = $conn->prepare('SELECT id, nombre FROM clientes WHERE id = ? AND activo = 1 LIMIT 1');
  if (!$stmt) throw new Exception('Error preparando cliente: ' . $conn->error);
  $stmt->bind_param('i', $cliente_id);
  if (!$stmt->execute()) throw new Exception('Error consultando cliente: ' . $stmt->error);
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row;
}

function import_excel_serial_to_datetime($serial) {
  $serial = floatval($serial);
  if ($serial <= 0) return null;
  $seconds = (int)round(($serial - 25569) * 86400);
  $dt = new DateTime('@' . $seconds);
  $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
  return $dt;
}

function import_to_date($value) {
  $s = import_clean($value);
  if ($s === '') return '';
  if (preg_match('/^\d+(\.\d+)?$/', $s)) {
    $dt = import_excel_serial_to_datetime($s);
    return $dt ? $dt->format('Y-m-d') : '';
  }
  if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return substr($s, 0, 10);
  $formats = ['d/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y', 'd/m/y', 'd-m-y'];
  foreach ($formats as $format) {
    $dt = DateTime::createFromFormat($format, $s);
    if ($dt instanceof DateTime) return $dt->format('Y-m-d');
  }
  try {
    $dt = new DateTime($s);
    return $dt->format('Y-m-d');
  } catch (Exception $e) {
    return '';
  }
}

function import_to_datetime_or_null($value, $base_date = '') {
  $s = import_clean($value);
  if ($s === '') return null;

  if (preg_match('/^\d+(\.\d+)?$/', $s)) {
    $num = floatval($s);
    if ($num > 0 && $num < 1 && $base_date) {
      $seconds = (int)round($num * 86400);
      return $base_date . ' ' . gmdate('H:i:s', $seconds);
    }
    $dt = import_excel_serial_to_datetime($s);
    return $dt ? $dt->format('Y-m-d H:i:s') : null;
  }
  if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s) && $base_date) {
    return $base_date . ' ' . (strlen($s) === 5 ? $s . ':00' : $s);
  }
  if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $s)) {
    $s = str_replace('T', ' ', $s);
  }
  if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $s)) {
    return strlen($s) === 16 ? $s . ':00' : $s;
  }
  try {
    $dt = new DateTime($s);
    return $dt->format('Y-m-d H:i:s');
  } catch (Exception $e) {
    return null;
  }
}

function import_firma_ruta($origen, $lugar_carga, $destino) {
  $norm = function ($v) {
    $s = mb_strtolower(import_clean($v), 'UTF-8');
    $s = preg_replace('/\s+/', ' ', $s);
    return $s;
  };
  $firma = $norm($origen) . '|' . $norm($lugar_carga) . '|' . $norm($destino);
  return $firma === '||' ? null : $firma;
}

function import_normalize_rows($conn, $cliente_id, $rows) {
  $valid = [];
  $errors = [];
  $warnings = [];
  $seen = [];
  $tramo_counters = [];

  foreach ($rows as $idx => $raw) {
    if (!is_array($raw)) continue;
    $source_row = intval($raw['source_row'] ?? ($idx + 2));
    $fecha_inicio = import_to_date($raw['fecha_inicio'] ?? ($raw['fecha'] ?? ''));
    $fecha_fin = import_to_date($raw['fecha_fin'] ?? '');
    if ($fecha_fin === '') $fecha_fin = $fecha_inicio;

    $folio = import_clean($raw['folio'] ?? '');
    $unidad = import_clean($raw['unidad'] ?? '');
    $origen = import_clean($raw['origen'] ?? '');
    $lugar_carga = import_clean($raw['lugar_carga'] ?? '');
    $destino = import_clean($raw['destino'] ?? '');
    $ruta = import_clean($raw['ruta'] ?? '');

    $row_errors = [];
    if ($unidad === '') $row_errors[] = ['campo' => 'unidad', 'mensaje' => 'Unidad requerida'];
    if ($fecha_inicio === '') $row_errors[] = ['campo' => 'fecha_inicio', 'mensaje' => 'Fecha requerida o invalida'];
    if ($origen === '' && $lugar_carga === '') $row_errors[] = ['campo' => 'origen', 'mensaje' => 'Origen o lugar de carga requerido'];
    if ($destino === '') $row_errors[] = ['campo' => 'destino', 'mensaje' => 'Destino requerido'];

    if ($folio === '' && $unidad !== '' && $fecha_inicio !== '') {
      $folio = import_generated_folio($raw, $fecha_inicio);
    }

    $group_key = implode('|', [$cliente_id, $folio, $unidad, $fecha_inicio]);
    $tramo_numero = intval($raw['tramo_numero'] ?? 0);
    if ($tramo_numero <= 0) {
      $tramo_counters[$group_key] = ($tramo_counters[$group_key] ?? 0) + 1;
      $tramo_numero = $tramo_counters[$group_key];
    }

    $normalized = [
      'source_row' => $source_row,
      'cliente_id' => $cliente_id,
      'folio' => $folio,
      'unidad' => $unidad,
      'placas' => import_clean($raw['placas'] ?? ''),
      'id_equipos' => import_clean($raw['id_equipos'] ?? ($raw['equipos'] ?? '')),
      'operador' => import_clean($raw['operador'] ?? ''),
      'telefono' => import_clean($raw['telefono'] ?? ''),
      'fecha_inicio' => $fecha_inicio,
      'fecha_fin' => $fecha_fin,
      'tramo_numero' => $tramo_numero,
      'ruta' => $ruta,
      'origen' => $origen,
      'lugar_carga' => $lugar_carga,
      'destino' => $destino,
      'salida_patio' => import_to_datetime_or_null($raw['salida_patio'] ?? '', $fecha_inicio),
      'cita_carga' => import_to_datetime_or_null($raw['cita_carga'] ?? '', $fecha_inicio),
      'salida_carga' => import_to_datetime_or_null($raw['salida_carga'] ?? '', $fecha_inicio),
      'descarga_programada' => import_to_datetime_or_null($raw['descarga_programada'] ?? '', $fecha_inicio),
      'requiere_regreso_origen' => import_bool($raw['requiere_regreso_origen'] ?? 0),
      'regreso_origen_programado' => import_to_datetime_or_null($raw['regreso_origen_programado'] ?? '', $fecha_inicio),
      'instrucciones' => import_clean($raw['instrucciones'] ?? '')
    ];

    $dup_key = implode('|', [
      $cliente_id,
      mb_strtolower($folio, 'UTF-8'),
      mb_strtolower($unidad, 'UTF-8'),
      $fecha_inicio,
      $tramo_numero,
      mb_strtolower($origen, 'UTF-8'),
      mb_strtolower($destino, 'UTF-8')
    ]);

    if (isset($seen[$dup_key])) {
      $row_errors[] = ['campo' => 'duplicado', 'mensaje' => 'Fila duplicada dentro del archivo'];
    }
    $seen[$dup_key] = true;

    if (count($row_errors) > 0) {
      foreach ($row_errors as $err) {
        $errors[] = [
          'row' => $source_row,
          'campo' => $err['campo'],
          'message' => $err['mensaje'],
          'value' => (string)($raw[$err['campo']] ?? '')
        ];
      }
      continue;
    }

    $existing = import_existing_tramo($conn, $cliente_id, $normalized);
    if ($existing) {
      $warnings[] = [
        'row' => $source_row,
        'campo' => 'duplicado',
        'message' => 'Ya existe un tramo similar en Bitacora',
        'viaje_id' => intval($existing['viaje_id'] ?? 0),
        'tramo_id' => intval($existing['tramo_id'] ?? 0)
      ];
      $normalized['_existing_tramo_id'] = intval($existing['tramo_id'] ?? 0);
    }

    $valid[] = $normalized;
  }

  return [
    'valid_rows' => $valid,
    'errors' => $errors,
    'warnings' => $warnings,
    'summary' => [
      'total_rows' => count($rows),
      'valid_rows' => count($valid),
      'error_rows' => count(array_unique(array_map(fn($e) => intval($e['row']), $errors))),
      'duplicate_rows' => count(array_unique(array_map(fn($w) => intval($w['row']), $warnings)))
    ]
  ];
}

function import_existing_tramo($conn, $cliente_id, $row) {
  $delete_filter = import_column_exists($conn, 'viajes', 'eliminado_at')
    ? "AND COALESCE(v.eliminado_at, '') = ''"
    : '';
  $stmt = $conn->prepare(
    "SELECT v.id AS viaje_id, vt.id AS tramo_id
       FROM viajes v
       INNER JOIN unidades u ON u.id = v.unidad_id
       INNER JOIN viaje_tramos vt ON vt.viaje_id = v.id
      WHERE v.cliente_id = ?
        AND v.folio = ?
        AND u.economico = ?
        AND v.fecha_inicio = ?
        AND vt.tramo_numero = ?
        $delete_filter
      LIMIT 1"
  );
  if (!$stmt) return null;
  $folio = $row['folio'];
  $unidad = $row['unidad'];
  $fecha = $row['fecha_inicio'];
  $tramo = intval($row['tramo_numero']);
  $stmt->bind_param('isssi', $cliente_id, $folio, $unidad, $fecha, $tramo);
  if (!$stmt->execute()) {
    $stmt->close();
    return null;
  }
  $found = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $found ?: null;
}

function import_insert_lote($conn, $cliente_id, $plantilla_id, $usuario_id, $archivo, $hoja, $summary, $estado = 'preview') {
  $resumen_json = json_encode($summary, JSON_UNESCAPED_UNICODE);
  $stmt = $conn->prepare(
    'INSERT INTO import_lotes
      (cliente_id, plantilla_id, usuario_id, archivo_nombre, hoja_nombre, total_filas, filas_validas, filas_error, filas_duplicadas, estado, resumen_json)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
  );
  if (!$stmt) throw new Exception('Error preparando lote: ' . $conn->error);
  $total = intval($summary['total_rows'] ?? 0);
  $validas = intval($summary['valid_rows'] ?? 0);
  $errores = intval($summary['error_rows'] ?? 0);
  $duplicadas = intval($summary['duplicate_rows'] ?? 0);
  $stmt->bind_param('iiissiiiiss', $cliente_id, $plantilla_id, $usuario_id, $archivo, $hoja, $total, $validas, $errores, $duplicadas, $estado, $resumen_json);
  if (!$stmt->execute()) throw new Exception('Error guardando lote: ' . $stmt->error);
  $id = intval($stmt->insert_id);
  $stmt->close();
  return $id;
}

function import_insert_errors($conn, $lote_id, $errors, $warnings = []) {
  if (!count($errors) && !count($warnings)) return;
  $stmt = $conn->prepare(
    'INSERT INTO import_lote_errores (lote_id, fila, campo, mensaje, valor_original, severidad)
     VALUES (?,?,?,?,?,?)'
  );
  if (!$stmt) throw new Exception('Error preparando errores de lote: ' . $conn->error);
  foreach ($errors as $err) {
    $fila = intval($err['row'] ?? 0);
    $campo = import_clean($err['campo'] ?? '');
    $mensaje = import_clean($err['message'] ?? '');
    $valor = (string)($err['value'] ?? '');
    $severidad = 'error';
    $stmt->bind_param('iissss', $lote_id, $fila, $campo, $mensaje, $valor, $severidad);
    $stmt->execute();
  }
  foreach ($warnings as $warn) {
    $fila = intval($warn['row'] ?? 0);
    $campo = import_clean($warn['campo'] ?? '');
    $mensaje = import_clean($warn['message'] ?? '');
    $valor = '';
    $severidad = 'warning';
    $stmt->bind_param('iissss', $lote_id, $fila, $campo, $mensaje, $valor, $severidad);
    $stmt->execute();
  }
  $stmt->close();
}

function import_find_or_create_unidad($conn, $cliente_id, $row) {
  $economico = $row['unidad'];
  $stmt = $conn->prepare('SELECT id FROM unidades WHERE cliente_id = ? AND economico = ? LIMIT 1');
  if (!$stmt) throw new Exception('Error preparando unidad: ' . $conn->error);
  $stmt->bind_param('is', $cliente_id, $economico);
  if (!$stmt->execute()) throw new Exception('Error consultando unidad: ' . $stmt->error);
  $found = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $placas = $row['placas'];
  $operador = $row['operador'];
  $telefono = $row['telefono'];
  $equipos = $row['id_equipos'] ?? '';

  if ($found) {
    $id = intval($found['id']);
    $updates = [];
    $types = '';
    $params = [];
    foreach (['placas' => $placas, 'operador' => $operador, 'telefonos' => $telefono, 'equipos' => $equipos] as $col => $value) {
      if ($value !== '' && import_column_exists($conn, 'unidades', $col)) {
        $updates[] = "$col = ?";
        $types .= 's';
        $params[] = $value;
      }
    }
    if (import_column_exists($conn, 'unidades', 'activo')) {
      $updates[] = 'activo = 1';
    }
    if (count($updates)) {
      $params[] = $id;
      $types .= 'i';
      $stmt = $conn->prepare('UPDATE unidades SET ' . implode(', ', $updates) . ' WHERE id = ?');
      if (!$stmt) throw new Exception('Error preparando actualizacion de unidad: ' . $conn->error);
      import_bind($stmt, $types, $params);
      if (!$stmt->execute()) throw new Exception('Error actualizando unidad: ' . $stmt->error);
      $stmt->close();
    }
    return $id;
  }

  $cols = ['cliente_id', 'economico'];
  $types = 'is';
  $params = [$cliente_id, $economico];
  foreach (['placas' => $placas, 'operador' => $operador, 'telefonos' => $telefono, 'equipos' => $equipos] as $col => $value) {
    if (import_column_exists($conn, 'unidades', $col)) {
      $cols[] = $col;
      $types .= 's';
      $params[] = $value;
    }
  }
  if (import_column_exists($conn, 'unidades', 'activo')) {
    $cols[] = 'activo';
    $types .= 'i';
    $params[] = 1;
  }
  $ph = implode(',', array_fill(0, count($cols), '?'));
  $stmt = $conn->prepare('INSERT INTO unidades (' . implode(',', $cols) . ") VALUES ($ph)");
  if (!$stmt) throw new Exception('Error preparando alta de unidad: ' . $conn->error);
  import_bind($stmt, $types, $params);
  if (!$stmt->execute()) throw new Exception('Error creando unidad: ' . $stmt->error);
  $id = intval($stmt->insert_id);
  $stmt->close();
  return $id;
}

function import_find_or_create_viaje($conn, $cliente_id, $unidad_id, $row, $usuario_id, &$created) {
  $delete_filter = import_column_exists($conn, 'viajes', 'eliminado_at')
    ? 'AND COALESCE(eliminado_at, "") = ""'
    : '';
  $stmt = $conn->prepare(
    "SELECT id FROM viajes WHERE cliente_id = ? AND unidad_id = ? AND folio = ? AND fecha_inicio = ? $delete_filter LIMIT 1"
  );
  if (!$stmt) throw new Exception('Error preparando viaje: ' . $conn->error);
  $folio = $row['folio'];
  $fecha = $row['fecha_inicio'];
  $stmt->bind_param('iiss', $cliente_id, $unidad_id, $folio, $fecha);
  if (!$stmt->execute()) throw new Exception('Error consultando viaje: ' . $stmt->error);
  $found = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($found) return intval($found['id']);

  $cols = ['cliente_id', 'unidad_id', 'folio', 'fecha_inicio'];
  $types = 'iiss';
  $params = [$cliente_id, $unidad_id, $row['folio'], $row['fecha_inicio']];
  foreach (['fecha_fin' => $row['fecha_fin'], 'notas' => 'Importado desde Excel'] as $col => $value) {
    if (import_column_exists($conn, 'viajes', $col)) {
      $cols[] = $col;
      $types .= 's';
      $params[] = $value;
    }
  }
  if (import_column_exists($conn, 'viajes', 'estado')) {
    $cols[] = 'estado';
    $types .= 's';
    $params[] = 'planificado';
  }
  if (import_column_exists($conn, 'viajes', 'created_by_usuario_id')) {
    $cols[] = 'created_by_usuario_id';
    $types .= 'i';
    $params[] = $usuario_id;
  }
  $ph = implode(',', array_fill(0, count($cols), '?'));
  $stmt = $conn->prepare('INSERT INTO viajes (' . implode(',', $cols) . ") VALUES ($ph)");
  if (!$stmt) throw new Exception('Error preparando alta de viaje: ' . $conn->error);
  import_bind($stmt, $types, $params);
  if (!$stmt->execute()) throw new Exception('Error creando viaje: ' . $stmt->error);
  $created++;
  $id = intval($stmt->insert_id);
  $stmt->close();
  return $id;
}

function import_create_tramo($conn, $viaje_id, $row, &$created) {
  if (!empty($row['_existing_tramo_id'])) return 0;
  $stmt = $conn->prepare('SELECT id FROM viaje_tramos WHERE viaje_id = ? AND tramo_numero = ? LIMIT 1');
  if ($stmt) {
    $tramo_numero = intval($row['tramo_numero']);
    $stmt->bind_param('ii', $viaje_id, $tramo_numero);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($found) return 0;
  }

  $cols = ['viaje_id', 'tramo_numero'];
  $types = 'ii';
  $params = [$viaje_id, intval($row['tramo_numero'])];
  $map = [
    'origen' => $row['origen'],
    'lugar_carga' => $row['lugar_carga'],
    'destino' => $row['destino'],
    'ruta' => $row['ruta'],
    'instrucciones' => $row['instrucciones'],
    'salida_patio' => $row['salida_patio'],
    'cita_carga' => $row['cita_carga'],
    'salida_carga' => $row['salida_carga'],
    'descarga_programada' => $row['descarga_programada'],
    'requiere_regreso_origen' => intval($row['requiere_regreso_origen']),
    'regreso_origen_programado' => $row['regreso_origen_programado'],
    'estado' => 'pendiente'
  ];
  foreach ($map as $col => $value) {
    if (!import_column_exists($conn, 'viaje_tramos', $col)) continue;
    $cols[] = $col;
    if ($col === 'requiere_regreso_origen') {
      $types .= 'i';
      $params[] = intval($value);
    } else {
      $types .= 's';
      $params[] = $value;
    }
  }
  $ph = implode(',', array_fill(0, count($cols), '?'));
  $stmt = $conn->prepare('INSERT INTO viaje_tramos (' . implode(',', $cols) . ") VALUES ($ph)");
  if (!$stmt) throw new Exception('Error preparando alta de tramo: ' . $conn->error);
  import_bind($stmt, $types, $params);
  if (!$stmt->execute()) throw new Exception('Error creando tramo: ' . $stmt->error);
  $id = intval($stmt->insert_id);
  $stmt->close();
  $created++;
  return $id;
}

function import_update_catalogo($conn, $cliente_id, $row) {
  if (!import_table_exists($conn, 'tramos_catalogo')) return;
  $firma = import_firma_ruta($row['origen'], $row['lugar_carga'], $row['destino']);
  if (!$firma || !import_column_exists($conn, 'tramos_catalogo', 'firma_ruta')) return;

  $base_etiqueta = $row['ruta'] !== '' ? $row['ruta'] : str_replace('|', ' - ', $firma);
  $etiqueta = mb_substr($base_etiqueta, 0, 86, 'UTF-8') . '-' . substr(sha1($firma), 0, 8);
  $stmt = $conn->prepare('SELECT id FROM tramos_catalogo WHERE cliente_id = ? AND firma_ruta = ? LIMIT 1');
  if (!$stmt) return;
  $stmt->bind_param('is', $cliente_id, $firma);
  $stmt->execute();
  $found = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($found) {
    $stmt = $conn->prepare('UPDATE tramos_catalogo SET veces_usada = COALESCE(veces_usada, 0) + 1, ruta = ?, origen = ?, lugar_carga = ?, destino = ? WHERE id = ?');
    if (!$stmt) return;
    $id = intval($found['id']);
    $stmt->bind_param('ssssi', $row['ruta'], $row['origen'], $row['lugar_carga'], $row['destino'], $id);
    $stmt->execute();
    $stmt->close();
    return;
  }
  $stmt = $conn->prepare(
    'INSERT INTO tramos_catalogo (cliente_id, etiqueta, ruta, origen, lugar_carga, destino, firma_ruta, veces_usada, activo)
     VALUES (?,?,?,?,?,?,?,?,1)'
  );
  if (!$stmt) return;
  $uno = 1;
  $stmt->bind_param('issssssi', $cliente_id, $etiqueta, $row['ruta'], $row['origen'], $row['lugar_carga'], $row['destino'], $firma, $uno);
  $stmt->execute();
  $stmt->close();
}

try {
  if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    import_error('Metodo no permitido', 405);
  }

  $user = require_admin_user($conn);
  import_ensure_tables($conn);

  $action = import_clean($_GET['action'] ?? '');
  $data = $_SERVER['REQUEST_METHOD'] === 'POST' ? read_json_body() : $_GET;
  if ($action === '') $action = import_clean($data['action'] ?? '');
  if ($action === '') import_error('Accion requerida', 400);

  if ($action === 'plantillas_listar') {
    $cliente_id = intval($data['cliente_id'] ?? 0);
    if ($cliente_id <= 0) import_error('cliente_id requerido', 400);
    if (!import_cliente_exists($conn, $cliente_id)) import_error('Cliente no encontrado', 404);
    $stmt = $conn->prepare(
      'SELECT id, cliente_id, nombre, mapeo_json, encabezados_json, updated_at
         FROM import_plantillas_excel
        WHERE cliente_id = ? AND activo = 1
        ORDER BY updated_at DESC, id DESC'
    );
    if (!$stmt) throw new Exception('Error preparando plantillas: ' . $conn->error);
    $stmt->bind_param('i', $cliente_id);
    if (!$stmt->execute()) throw new Exception('Error consultando plantillas: ' . $stmt->error);
    $rows = [];
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
      $rows[] = [
        'id' => intval($r['id']),
        'cliente_id' => intval($r['cliente_id']),
        'nombre' => (string)$r['nombre'],
        'mapeo' => json_decode((string)$r['mapeo_json'], true) ?: [],
        'encabezados' => json_decode((string)($r['encabezados_json'] ?? '[]'), true) ?: [],
        'updated_at' => (string)$r['updated_at']
      ];
    }
    $stmt->close();
    import_json(['success' => true, 'message' => 'Plantillas obtenidas', 'data' => $rows]);
  }

  if ($action === 'plantilla_guardar') {
    $cliente_id = intval($data['cliente_id'] ?? 0);
    $nombre = import_clean($data['nombre'] ?? 'Plantilla principal');
    if ($nombre === '') $nombre = 'Plantilla principal';
    $mapeo = $data['mapeo'] ?? [];
    $encabezados = $data['encabezados'] ?? [];
    if ($cliente_id <= 0) import_error('cliente_id requerido', 400);
    if (!is_array($mapeo) || !count($mapeo)) import_error('Mapeo requerido', 400);
    if (!import_cliente_exists($conn, $cliente_id)) import_error('Cliente no encontrado', 404);
    $mapeo_json = json_encode($mapeo, JSON_UNESCAPED_UNICODE);
    $enc_json = json_encode(is_array($encabezados) ? $encabezados : [], JSON_UNESCAPED_UNICODE);
    $usuario_id = intval($user['id']);
    $stmt = $conn->prepare(
      'INSERT INTO import_plantillas_excel (cliente_id, nombre, mapeo_json, encabezados_json, created_by_usuario_id, activo)
       VALUES (?,?,?,?,?,1)
       ON DUPLICATE KEY UPDATE
         mapeo_json = VALUES(mapeo_json),
         encabezados_json = VALUES(encabezados_json),
         created_by_usuario_id = VALUES(created_by_usuario_id),
         activo = 1'
    );
    if (!$stmt) throw new Exception('Error preparando plantilla: ' . $conn->error);
    $stmt->bind_param('isssi', $cliente_id, $nombre, $mapeo_json, $enc_json, $usuario_id);
    if (!$stmt->execute()) throw new Exception('Error guardando plantilla: ' . $stmt->error);
    $stmt->close();
    $stmt = $conn->prepare('SELECT id FROM import_plantillas_excel WHERE cliente_id = ? AND nombre = ? LIMIT 1');
    $stmt->bind_param('is', $cliente_id, $nombre);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    import_json(['success' => true, 'message' => 'Plantilla guardada', 'data' => ['id' => intval($row['id'] ?? 0)]]);
  }

  if ($action === 'preview' || $action === 'commit') {
    $cliente_id = intval($data['cliente_id'] ?? 0);
    $plantilla_id = intval($data['plantilla_id'] ?? 0);
    $archivo = import_clean($data['archivo_nombre'] ?? '');
    $hoja = import_clean($data['hoja_nombre'] ?? '');
    $rows = $data['rows'] ?? [];
    if ($cliente_id <= 0) import_error('cliente_id requerido', 400);
    if (!is_array($rows) || !count($rows)) import_error('No hay filas para importar', 400);
    if (count($rows) > 2000) import_error('Maximo 2000 filas por importacion', 400);
    if (!import_cliente_exists($conn, $cliente_id)) import_error('Cliente no encontrado', 404);

    $preview = import_normalize_rows($conn, $cliente_id, $rows);

    if ($action === 'preview') {
      $lote_id = import_insert_lote($conn, $cliente_id, $plantilla_id ?: null, intval($user['id']), $archivo, $hoja, $preview['summary'], 'preview');
      import_insert_errors($conn, $lote_id, $preview['errors'], $preview['warnings']);
      $preview['lote_id'] = $lote_id;
      import_json(['success' => true, 'message' => 'Vista previa generada', 'data' => $preview]);
    }

    $valid_rows = array_values(array_filter($preview['valid_rows'], fn($r) => empty($r['_existing_tramo_id'])));
    if (!count($valid_rows)) {
      import_error('No hay filas nuevas validas para importar', 400);
    }

    $conn->begin_transaction();
    try {
      $viajes_creados = 0;
      $tramos_creados = 0;
      $viaje_ids = [];
      foreach ($valid_rows as $row) {
        $unidad_id = import_find_or_create_unidad($conn, $cliente_id, $row);
        $viaje_id = import_find_or_create_viaje($conn, $cliente_id, $unidad_id, $row, intval($user['id']), $viajes_creados);
        $tramo_id = import_create_tramo($conn, $viaje_id, $row, $tramos_creados);
        if ($tramo_id > 0) import_update_catalogo($conn, $cliente_id, $row);
        $viaje_ids[$viaje_id] = true;
      }
      $summary = $preview['summary'];
      $summary['viajes_creados'] = $viajes_creados;
      $summary['tramos_creados'] = $tramos_creados;
      $summary['viajes_afectados'] = count($viaje_ids);
      $lote_id = import_insert_lote($conn, $cliente_id, $plantilla_id ?: null, intval($user['id']), $archivo, $hoja, $summary, 'importado');
      import_insert_errors($conn, $lote_id, $preview['errors'], $preview['warnings']);
      $stmt = $conn->prepare('UPDATE import_lotes SET viajes_creados = ?, tramos_creados = ? WHERE id = ?');
      if ($stmt) {
        $stmt->bind_param('iii', $viajes_creados, $tramos_creados, $lote_id);
        $stmt->execute();
        $stmt->close();
      }
      $conn->commit();
      import_json([
        'success' => true,
        'message' => 'Importacion aplicada correctamente',
        'data' => [
          'lote_id' => $lote_id,
          'summary' => $summary
        ]
      ]);
    } catch (Exception $e) {
      $conn->rollback();
      throw $e;
    }
  }

  import_error('Accion no soportada', 404);
} catch (Exception $e) {
  if (isset($conn) && $conn) {
    try { $conn->rollback(); } catch (Exception $ignored) {}
  }
  $code = is_numeric($e->getCode()) ? intval($e->getCode()) : 500;
  if ($code < 400 || $code > 599) {
    $code = 500;
  }
  error_log('[importar_viajes] ERROR: ' . $e->getMessage());
  import_json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null], $code);
} finally {
  if (isset($conn) && $conn) {
    $conn->close();
  }
}
