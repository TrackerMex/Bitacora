<?php

error_reporting(0);
ini_set('display_errors', 0);

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 600);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

require_once __DIR__ . '/../../config/environment.php';

$response = [
  'success' => false,
  'message' => '',
  'dry_run' => true,
  'summary' => [
    'source_rows' => 0,
    'valid_rows' => 0,
    'skipped_rows' => 0,
    'clientes' => 0,
    'unidades' => 0,
    'despachos' => 0,
    'seguimientos' => 0,
    'migration_map' => 0
  ],
  'errors' => []
];

function request_value($key, $default = '') {
  if (isset($_GET[$key])) return $_GET[$key];

  static $json = null;
  if ($json === null) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (!is_array($json)) $json = [];
  }

  return $json[$key] ?? $default;
}

function require_execution_token($execute) {
  if (!$execute) return;

  $configured = trim((string)getEnvVar('MIGRATION_TOKEN', ''));
  if ($configured === '') {
    throw new Exception('MIGRATION_TOKEN no está configurado. Solo se permite dry_run.');
  }

  $provided = trim((string)request_value('token', ''));
  if (!hash_equals($configured, $provided)) {
    throw new Exception('Token de migración inválido.');
  }
}

function normalize_header($value) {
  $s = strtolower(trim((string)$value));
  $s = str_replace(
    ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü'],
    ['a', 'e', 'i', 'o', 'u', 'n', 'u'],
    $s
  );
  $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
  return trim(preg_replace('/\s+/', ' ', $s));
}

function slugify($value) {
  $s = normalize_header($value);
  $s = preg_replace('/[^a-z0-9]+/', '-', $s);
  $s = trim((string)$s, '-');
  return $s !== '' ? $s : 'cliente';
}

function is_date_like($value) {
  $s = trim((string)$value);
  return preg_match('/^\d{4}-\d{2}-\d{2}/', $s)
    || preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/', $s)
    || preg_match('/^\d+(\.\d+)?$/', $s);
}

function excel_serial_to_datetime($serial) {
  $serial = floatval($serial);
  if ($serial <= 0) return null;
  $seconds = (int)round(($serial - 25569) * 86400);
  $dt = new DateTime('@' . $seconds);
  $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
  return $dt;
}

function to_mysql_date($value) {
  $s = trim((string)$value);
  if ($s === '') return '';

  if (preg_match('/^\d+(\.\d+)?$/', $s)) {
    $dt = excel_serial_to_datetime($s);
    return $dt ? $dt->format('Y-m-d') : '';
  }

  if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
    return substr($s, 0, 10);
  }

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

function to_mysql_datetime_or_null($value, $baseDate = '') {
  $s = trim((string)$value);
  if ($s === '') return null;

  if (preg_match('/^\d+(\.\d+)?$/', $s)) {
    $num = floatval($s);
    if ($num > 0 && $num < 1 && $baseDate) {
      $seconds = (int)round($num * 86400);
      return $baseDate . ' ' . gmdate('H:i:s', $seconds);
    }
    $dt = excel_serial_to_datetime($s);
    return $dt ? $dt->format('Y-m-d H:i:s') : null;
  }

  if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s) && $baseDate) {
    return $baseDate . ' ' . (strlen($s) === 5 ? $s . ':00' : $s);
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

function fetch_sheet_values($action) {
  $api_key = getEnvVar('GOOGLE_SHEETS_API_KEY');
  $spreadsheet_id = getEnvVar('GOOGLE_SHEETS_SPREADSHEET_ID');
  if (!$api_key || !$spreadsheet_id) {
    throw new Exception('Configuración de Google Sheets no disponible.');
  }

  $range = match($action) {
    'datos' => getEnvVar('GOOGLE_SHEETS_RANGE_DATOS', 'Datos!A1:Z1000'),
    'contactos' => getEnvVar('GOOGLE_SHEETS_RANGE_CONTACTOS', 'Contactos!A6:N1000'),
    default => throw new Exception('Acción de hoja no soportada.')
  };

  $url = sprintf(
    'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?key=%s',
    urlencode($spreadsheet_id),
    urlencode($range),
    urlencode($api_key)
  );

  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => 20,
      'ignore_errors' => true
    ]
  ]);

  $raw = @file_get_contents($url, false, $context);
  if ($raw === false) {
    throw new Exception('No se pudo conectar con Google Sheets.');
  }

  $json = json_decode($raw, true);
  if (!is_array($json)) {
    throw new Exception('Respuesta inválida de Google Sheets.');
  }
  if (isset($json['error'])) {
    throw new Exception('Error de Google Sheets: ' . ($json['error']['message'] ?? 'Error desconocido'));
  }

  return $json['values'] ?? [];
}

function sheet_rows_to_objects($rows) {
  if (!count($rows)) return [];

  $column_map = [
    'fecha' => 'fecha',
    'folio' => 'folio',
    'unidad' => 'unidad',
    'economico' => 'unidad',
    'placas' => 'placas',
    'id equipos' => 'equipos',
    'equipos' => 'equipos',
    'operador' => 'operador',
    'telefono' => 'telefonos',
    'telefonos' => 'telefonos',
    'ruta' => 'ruta',
    'origen' => 'origen',
    'lugar de carga' => 'lugar_carga',
    'lugar carga' => 'lugar_carga',
    'lugar carga programada' => 'lugar_carga',
    'carga' => 'lugar_carga',
    'destino' => 'destino',
    'salida programada' => 'salida_prog',
    'salida patio programada' => 'salida_prog',
    'carga programada' => 'carga_prog',
    'cita carga' => 'carga_prog',
    'salida carga programada' => 'salida_carga_prog',
    'descarga programada' => 'descarga_prog',
    'instrucciones' => 'instrucciones',
    'instrucciones especiales' => 'instrucciones',
    'cliente' => 'cliente',
    'sub cliente' => 'sub_cliente',
    'salida inicial de la unidad real' => 'real_salida_unidad',
    'cita de carga real' => 'real_carga',
    'salida de carga real' => 'real_salida',
    'proceso de descarga real' => 'real_descarga',
    'salida' => 'real_salida_unidad',
    'entrada carga' => 'real_carga',
    'salida carga' => 'real_salida',
    'descarga' => 'real_descarga'
  ];

  $fallback = [
    'fecha', 'folio', 'unidad', 'placas', 'equipos', 'operador', 'telefonos',
    'ruta', 'origen', 'destino', 'salida_prog', 'carga_prog',
    'salida_carga_prog', 'descarga_prog', 'cliente', 'sub_cliente',
    'real_salida_unidad', 'real_carga', 'real_salida', 'real_descarga'
  ];

  $first_cell = $rows[0][0] ?? '';
  if (!is_date_like($first_cell) && count($rows) >= 2) {
    $headers = array_map(function($h) use ($column_map) {
      $normalized = normalize_header($h);
      return $column_map[$normalized] ?? str_replace(' ', '_', $normalized);
    }, array_shift($rows));
  } else {
    $headers = $fallback;
  }

  $objects = [];
  foreach ($rows as $index => $row) {
    $obj = ['source_row' => $index + 1];
    foreach ($headers as $i => $header) {
      if ($header === '') continue;
      $obj[$header] = isset($row[$i]) ? trim((string)$row[$i]) : '';
    }
    $objects[] = $obj;
  }

  return $objects;
}

function bind_params($stmt, $types, $params) {
  $refs = [$types];
  foreach ($params as $key => $value) {
    $params[$key] = $value;
    $refs[] = &$params[$key];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);
}

function fetch_one($conn, $sql, $types = '', $params = []) {
  $stmt = $conn->prepare($sql);
  if (!$stmt) throw new Exception('Error preparando query: ' . $conn->error);
  if ($types !== '') bind_params($stmt, $types, $params);
  if (!$stmt->execute()) throw new Exception('Error ejecutando query: ' . $stmt->error);
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  return $row;
}

function execute_stmt($conn, $sql, $types = '', $params = []) {
  $stmt = $conn->prepare($sql);
  if (!$stmt) throw new Exception('Error preparando query: ' . $conn->error);
  if ($types !== '') bind_params($stmt, $types, $params);
  if (!$stmt->execute()) throw new Exception('Error ejecutando query: ' . $stmt->error);
  $id = $conn->insert_id;
  $affected = $stmt->affected_rows;
  $stmt->close();
  return ['id' => $id, 'affected' => $affected];
}

function upsert_migration_map($conn, $sourceCliente, $entity, $sourceKey, $targetTable, $targetId) {
  execute_stmt(
    $conn,
    "INSERT INTO migration_map (source_system, source_cliente, source_entity, source_key, target_table, target_id)
     VALUES ('google_sheets', ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE source_cliente = VALUES(source_cliente), target_id = VALUES(target_id)",
    'ssssi',
    [$sourceCliente, $entity, $sourceKey, $targetTable, $targetId]
  );
}

function upsert_cliente($conn, $nombre) {
  $slug = slugify($nombre);
  execute_stmt(
    $conn,
    "INSERT INTO clientes (nombre, slug, activo) VALUES (?, ?, 1)
     ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1",
    'ss',
    [$nombre, $slug]
  );
  $row = fetch_one($conn, 'SELECT id FROM clientes WHERE slug = ? LIMIT 1', 's', [$slug]);
  return intval($row['id'] ?? 0);
}

function upsert_unidad($conn, $clienteId, $row) {
  $economico = trim((string)($row['unidad'] ?? ''));
  $placas = trim((string)($row['placas'] ?? ''));
  $operador = trim((string)($row['operador'] ?? ''));
  $telefonos = trim((string)($row['telefonos'] ?? ''));
  $equipos = trim((string)($row['equipos'] ?? ''));

  execute_stmt(
    $conn,
    "INSERT INTO unidades (cliente_id, economico, placas, operador, telefonos, equipos, activo)
     VALUES (?, ?, ?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE
       placas = VALUES(placas),
       operador = VALUES(operador),
       telefonos = VALUES(telefonos),
       equipos = VALUES(equipos),
       activo = 1",
    'isssss',
    [$clienteId, $economico, $placas, $operador, $telefonos, $equipos]
  );

  $found = fetch_one(
    $conn,
    'SELECT id FROM unidades WHERE cliente_id = ? AND economico = ? LIMIT 1',
    'is',
    [$clienteId, $economico]
  );
  return intval($found['id'] ?? 0);
}

function upsert_despacho($conn, $clienteId, $unidadId, $row, $fecha, $tramoNumero, $legacyKey) {
  $folio = trim((string)($row['folio'] ?? ''));
  $ruta = trim((string)($row['ruta'] ?? ''));
  $origen = trim((string)($row['origen'] ?? ''));
  $lugarCarga = trim((string)($row['lugar_carga'] ?? ''));
  $destino = trim((string)($row['destino'] ?? ''));
  $instrucciones = trim((string)($row['instrucciones'] ?? ''));
  $salida = to_mysql_datetime_or_null($row['salida_prog'] ?? '', $fecha);
  $carga = to_mysql_datetime_or_null($row['carga_prog'] ?? '', $fecha);
  $salidaCarga = to_mysql_datetime_or_null($row['salida_carga_prog'] ?? '', $fecha);
  $descarga = to_mysql_datetime_or_null($row['descarga_prog'] ?? '', $fecha);

  execute_stmt(
    $conn,
    "INSERT INTO despachos (
       cliente_id, unidad_id, folio, fecha_programada, tramo_numero, ruta,
       origen, lugar_carga, destino, instrucciones,
       salida_patio_programada, cita_carga, salida_carga_programada, descarga_programada,
       source_system, legacy_key
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'google_sheets', ?)
     ON DUPLICATE KEY UPDATE
       ruta = VALUES(ruta),
       origen = VALUES(origen),
       lugar_carga = VALUES(lugar_carga),
       destino = VALUES(destino),
       instrucciones = VALUES(instrucciones),
       salida_patio_programada = VALUES(salida_patio_programada),
       cita_carga = VALUES(cita_carga),
       salida_carga_programada = VALUES(salida_carga_programada),
       descarga_programada = VALUES(descarga_programada),
       source_system = VALUES(source_system),
       legacy_key = VALUES(legacy_key)",
    'iississssssssss',
    [
      $clienteId, $unidadId, $folio, $fecha, $tramoNumero, $ruta,
      $origen, $lugarCarga, $destino, $instrucciones,
      $salida, $carga, $salidaCarga, $descarga, $legacyKey
    ]
  );

  $found = fetch_one(
    $conn,
    'SELECT id FROM despachos WHERE cliente_id = ? AND folio = ? AND unidad_id = ? AND fecha_programada = ? AND tramo_numero = ? LIMIT 1',
    'isisi',
    [$clienteId, $folio, $unidadId, $fecha, $tramoNumero]
  );
  return intval($found['id'] ?? 0);
}

function upsert_seguimiento($conn, $clienteId, $despachoId, $row, $fecha) {
  $folio = trim((string)($row['folio'] ?? ''));
  $unidad = trim((string)($row['unidad'] ?? ''));
  $realSalidaUnidad = to_mysql_datetime_or_null($row['real_salida_unidad'] ?? '', $fecha);
  $realCarga = to_mysql_datetime_or_null($row['real_carga'] ?? '', $fecha);
  $realSalida = to_mysql_datetime_or_null($row['real_salida'] ?? '', $fecha);
  $realDescarga = to_mysql_datetime_or_null($row['real_descarga'] ?? '', $fecha);
  $citaSalida = to_mysql_datetime_or_null($row['salida_prog'] ?? '', $fecha);
  $citaCarga = to_mysql_datetime_or_null($row['carga_prog'] ?? '', $fecha);
  $citaSalidaCarga = to_mysql_datetime_or_null($row['salida_carga_prog'] ?? '', $fecha);
  $citaDescarga = to_mysql_datetime_or_null($row['descarga_prog'] ?? '', $fecha);

  if (!$realSalidaUnidad && !$realCarga && !$realSalida && !$realDescarga) {
    return 0;
  }

  $estatus = $realDescarga ? 'Despacho realizado' : 'En ruta';

  execute_stmt(
    $conn,
    "INSERT INTO seguimiento_despacho (
       cliente_id, despacho_id, folio, unidad, fecha_programada,
       real_salida_unidad, real_carga, real_salida, real_descarga,
       cita_salida_unidad, cita_carga, cita_salida, cita_descarga,
       estatus
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       cliente_id = VALUES(cliente_id),
       despacho_id = VALUES(despacho_id),
       real_salida_unidad = COALESCE(VALUES(real_salida_unidad), real_salida_unidad),
       real_carga = COALESCE(VALUES(real_carga), real_carga),
       real_salida = COALESCE(VALUES(real_salida), real_salida),
       real_descarga = COALESCE(VALUES(real_descarga), real_descarga),
       cita_salida_unidad = COALESCE(VALUES(cita_salida_unidad), cita_salida_unidad),
       cita_carga = COALESCE(VALUES(cita_carga), cita_carga),
       cita_salida = COALESCE(VALUES(cita_salida), cita_salida),
       cita_descarga = COALESCE(VALUES(cita_descarga), cita_descarga),
       estatus = VALUES(estatus)",
    'iissssssssssss',
    [
      $clienteId, $despachoId, $folio, $unidad, $fecha,
      $realSalidaUnidad, $realCarga, $realSalida, $realDescarga,
      $citaSalida, $citaCarga, $citaSalidaCarga, $citaDescarga, $estatus
    ]
  );

  $found = fetch_one($conn, 'SELECT id FROM seguimiento_despacho WHERE despacho_id = ? LIMIT 1', 'i', [$despachoId]);
  return intval($found['id'] ?? 0);
}

function build_legacy_key($row, $fecha, $tramoNumero) {
  $parts = [
    $row['cliente'] ?? '',
    $row['folio'] ?? '',
    $row['unidad'] ?? '',
    $fecha,
    $tramoNumero,
    $row['ruta'] ?? '',
    $row['origen'] ?? '',
    $row['destino'] ?? ''
  ];
  return sha1(implode('|', array_map(fn($v) => trim((string)$v), $parts)));
}

try {
  if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    throw new Exception('Método no permitido.');
  }

  $execute = in_array(strtolower((string)request_value('execute', '0')), ['1', 'true', 'yes'], true);
  require_execution_token($execute);
  $response['dry_run'] = !$execute;

  $rows = fetch_sheet_values('datos');
  $response['summary']['source_rows'] = count($rows);
  $objects = sheet_rows_to_objects($rows);

  $validRows = [];
  $tramos = [];
  foreach ($objects as $idx => $row) {
    $fecha = to_mysql_date($row['fecha'] ?? '');
    $cliente = trim((string)($row['cliente'] ?? ''));
    $folio = trim((string)($row['folio'] ?? ''));
    $unidad = trim((string)($row['unidad'] ?? ''));

    if ($cliente === '' || $folio === '' || $unidad === '' || $fecha === '') {
      $response['summary']['skipped_rows']++;
      if (count($response['errors']) < 25) {
        $response['errors'][] = [
          'row' => intval($row['source_row'] ?? ($idx + 1)),
          'reason' => 'Faltan cliente, folio, unidad o fecha.'
        ];
      }
      continue;
    }

    $tramoKey = implode('|', [$cliente, $folio, $unidad, $fecha]);
    $tramos[$tramoKey] = ($tramos[$tramoKey] ?? 0) + 1;
    $row['_fecha'] = $fecha;
    $row['_tramo_numero'] = $tramos[$tramoKey];
    $row['_legacy_key'] = build_legacy_key($row, $fecha, $row['_tramo_numero']);
    $validRows[] = $row;
  }

  $response['summary']['valid_rows'] = count($validRows);

  if (!$execute) {
    $clientes = [];
    $unidades = [];
    foreach ($validRows as $row) {
      $clientes[trim((string)$row['cliente'])] = true;
      $unidades[trim((string)$row['cliente']) . '|' . trim((string)$row['unidad'])] = true;
    }
    $response['summary']['clientes'] = count($clientes);
    $response['summary']['unidades'] = count($unidades);
    $response['summary']['despachos'] = count($validRows);
    $response['message'] = 'Dry run completado. No se escribió en base de datos.';
    $response['success'] = true;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
  }

  require_once __DIR__ . '/../db/db.php';
  $conn->begin_transaction();

  $clienteCache = [];
  $unidadCache = [];
  foreach ($validRows as $row) {
    $clienteNombre = trim((string)$row['cliente']);
    if (!isset($clienteCache[$clienteNombre])) {
      $clienteCache[$clienteNombre] = upsert_cliente($conn, $clienteNombre);
      $response['summary']['clientes']++;
      upsert_migration_map($conn, $clienteNombre, 'cliente', $clienteNombre, 'clientes', $clienteCache[$clienteNombre]);
      $response['summary']['migration_map']++;
    }

    $clienteId = $clienteCache[$clienteNombre];
    $unidadKey = $clienteId . '|' . trim((string)$row['unidad']);
    if (!isset($unidadCache[$unidadKey])) {
      $unidadCache[$unidadKey] = upsert_unidad($conn, $clienteId, $row);
      $response['summary']['unidades']++;
      upsert_migration_map($conn, $clienteNombre, 'unidad', $unidadKey, 'unidades', $unidadCache[$unidadKey]);
      $response['summary']['migration_map']++;
    }

    $unidadId = $unidadCache[$unidadKey];
    $despachoId = upsert_despacho(
      $conn,
      $clienteId,
      $unidadId,
      $row,
      $row['_fecha'],
      intval($row['_tramo_numero']),
      $row['_legacy_key']
    );
    $response['summary']['despachos']++;
    upsert_migration_map($conn, $clienteNombre, 'despacho', $row['_legacy_key'], 'despachos', $despachoId);
    $response['summary']['migration_map']++;

    $seguimientoId = upsert_seguimiento($conn, $clienteId, $despachoId, $row, $row['_fecha']);
    if ($seguimientoId > 0) {
      $response['summary']['seguimientos']++;
      upsert_migration_map($conn, $clienteNombre, 'seguimiento', $row['_legacy_key'], 'seguimiento_despacho', $seguimientoId);
      $response['summary']['migration_map']++;
    }
  }

  $conn->commit();
  $response['success'] = true;
  $response['message'] = 'Migración histórica aplicada correctamente.';

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
