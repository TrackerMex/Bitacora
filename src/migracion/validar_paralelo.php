<?php

error_reporting(0);
ini_set('display_errors', 0);

ini_set('memory_limit', '512M');
ini_set('max_execution_time', 180);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/environment.php';

$response = [
  'success' => false,
  'message' => '',
  'summary' => [
    'sheet_rows' => 0,
    'sheet_valid_rows' => 0,
    'sheet_skipped_rows' => 0,
    'mysql_google_sheets_despachos' => 0,
    'missing_in_mysql' => 0,
    'extra_in_mysql' => 0,
    'mismatched_core_fields' => 0
  ],
  'by_cliente' => [],
  'missing_in_mysql' => [],
  'extra_in_mysql' => [],
  'mismatches' => [],
  'skipped_rows' => []
];

function normalize_header_vp($value) {
  $s = strtolower(trim((string)$value));
  $s = str_replace(
    ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', 'Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã±', 'Ã¼'],
    ['a', 'e', 'i', 'o', 'u', 'n', 'u', 'a', 'e', 'i', 'o', 'u', 'n', 'u'],
    $s
  );
  $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
  return trim(preg_replace('/\s+/', ' ', $s));
}

function is_date_like_vp($value) {
  $s = trim((string)$value);
  return preg_match('/^\d{4}-\d{2}-\d{2}/', $s)
    || preg_match('/^\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}/', $s)
    || preg_match('/^\d+(\.\d+)?$/', $s);
}

function excel_serial_to_datetime_vp($serial) {
  $serial = floatval($serial);
  if ($serial <= 0) return null;
  $seconds = (int)round(($serial - 25569) * 86400);
  $dt = new DateTime('@' . $seconds);
  $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
  return $dt;
}

function to_mysql_date_vp($value) {
  $s = trim((string)$value);
  if ($s === '') return '';

  if (preg_match('/^\d+(\.\d+)?$/', $s)) {
    $dt = excel_serial_to_datetime_vp($s);
    return $dt ? $dt->format('Y-m-d') : '';
  }

  if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) {
    return substr($s, 0, 10);
  }

  foreach (['d/m/Y', 'd-m-Y', 'm/d/Y', 'm-d-Y', 'd/m/y', 'd-m-y'] as $format) {
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

function fetch_sheet_values_vp() {
  $api_key = getEnvVar('GOOGLE_SHEETS_API_KEY');
  $spreadsheet_id = getEnvVar('GOOGLE_SHEETS_SPREADSHEET_ID');
  if (!$api_key || !$spreadsheet_id) {
    throw new Exception('Configuración de Google Sheets no disponible.');
  }

  $range = getEnvVar('GOOGLE_SHEETS_RANGE_DATOS', 'Datos!A1:Z1000');
  $url = sprintf(
    'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?key=%s',
    urlencode($spreadsheet_id),
    urlencode($range),
    urlencode($api_key)
  );

  $raw = @file_get_contents($url, false, stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => 20,
      'ignore_errors' => true
    ]
  ]));
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

function sheet_rows_to_objects_vp($rows) {
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
    'destino' => 'destino',
    'lugar de carga' => 'lugar_carga',
    'lugar carga' => 'lugar_carga',
    'carga' => 'lugar_carga',
    'salida programada' => 'salida_prog',
    'salida patio programada' => 'salida_prog',
    'carga programada' => 'carga_prog',
    'cita carga' => 'carga_prog',
    'salida carga programada' => 'salida_carga_prog',
    'descarga programada' => 'descarga_prog',
    'instrucciones' => 'instrucciones',
    'instrucciones especiales' => 'instrucciones',
    'cliente' => 'cliente',
    'sub cliente' => 'sub_cliente'
  ];

  $fallback = [
    'fecha', 'folio', 'unidad', 'placas', 'equipos', 'operador', 'telefonos',
    'ruta', 'origen', 'destino', 'salida_prog', 'carga_prog',
    'salida_carga_prog', 'descarga_prog', 'cliente', 'sub_cliente'
  ];

  $first_cell = $rows[0][0] ?? '';
  if (!is_date_like_vp($first_cell) && count($rows) >= 2) {
    $headers = array_map(function($h) use ($column_map) {
      $normalized = normalize_header_vp($h);
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

function build_legacy_key_vp($row, $fecha, $tramoNumero) {
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

function bind_params_vp($stmt, $types, $params) {
  if ($types === '') return;
  $refs = [$types];
  foreach ($params as $key => $value) {
    $params[$key] = $value;
    $refs[] = &$params[$key];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);
}

function fetch_all_vp($conn, $sql, $types = '', $params = []) {
  $stmt = $conn->prepare($sql);
  if (!$stmt) throw new Exception('Error preparando query: ' . $conn->error);
  bind_params_vp($stmt, $types, $params);
  if (!$stmt->execute()) throw new Exception('Error ejecutando query: ' . $stmt->error);
  $res = $stmt->get_result();
  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;
  $stmt->close();
  return $rows;
}

function add_cliente_counter(&$byCliente, $cliente, $side) {
  $cliente = trim((string)$cliente);
  if ($cliente === '') $cliente = 'SIN CLIENTE';
  if (!isset($byCliente[$cliente])) {
    $byCliente[$cliente] = ['sheet' => 0, 'mysql' => 0, 'missing' => 0, 'extra' => 0, 'mismatches' => 0];
  }
  $byCliente[$cliente][$side]++;
}

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    throw new Exception('Método no permitido. Use GET.');
  }

  $rows = fetch_sheet_values_vp();
  $response['summary']['sheet_rows'] = count($rows);
  $objects = sheet_rows_to_objects_vp($rows);

  $sheetByKey = [];
  $tramos = [];
  foreach ($objects as $idx => $row) {
    $fecha = to_mysql_date_vp($row['fecha'] ?? '');
    $cliente = trim((string)($row['cliente'] ?? ''));
    $folio = trim((string)($row['folio'] ?? ''));
    $unidad = trim((string)($row['unidad'] ?? ''));

    if ($cliente === '' || $folio === '' || $unidad === '' || $fecha === '') {
      $response['summary']['sheet_skipped_rows']++;
      if (count($response['skipped_rows']) < 25) {
        $response['skipped_rows'][] = [
          'row' => intval($row['source_row'] ?? ($idx + 1)),
          'reason' => 'Faltan cliente, folio, unidad o fecha.'
        ];
      }
      continue;
    }

    $tramoKey = implode('|', [$cliente, $folio, $unidad, $fecha]);
    $tramos[$tramoKey] = ($tramos[$tramoKey] ?? 0) + 1;
    $legacyKey = build_legacy_key_vp($row, $fecha, $tramos[$tramoKey]);
    $sheetByKey[$legacyKey] = [
      'legacy_key' => $legacyKey,
      'cliente' => $cliente,
      'folio' => $folio,
      'unidad' => $unidad,
      'fecha' => $fecha,
      'tramo_numero' => $tramos[$tramoKey],
      'ruta' => trim((string)($row['ruta'] ?? '')),
      'origen' => trim((string)($row['origen'] ?? '')),
      'destino' => trim((string)($row['destino'] ?? ''))
    ];
    add_cliente_counter($response['by_cliente'], $cliente, 'sheet');
  }

  $response['summary']['sheet_valid_rows'] = count($sheetByKey);

  require_once __DIR__ . '/../db/db.php';

  $mysqlRows = fetch_all_vp(
    $conn,
    "SELECT
       d.id,
       d.legacy_key,
       c.nombre AS cliente,
       d.folio,
       u.economico AS unidad,
       d.fecha_programada AS fecha,
       d.tramo_numero,
       d.ruta,
       d.origen,
       d.destino
     FROM despachos d
     INNER JOIN clientes c ON c.id = d.cliente_id
     INNER JOIN unidades u ON u.id = d.unidad_id
     WHERE d.source_system = 'google_sheets'"
  );

  $mysqlByKey = [];
  foreach ($mysqlRows as $row) {
    $key = trim((string)($row['legacy_key'] ?? ''));
    if ($key === '') continue;
    $mysqlByKey[$key] = $row;
    add_cliente_counter($response['by_cliente'], $row['cliente'] ?? '', 'mysql');
  }

  $response['summary']['mysql_google_sheets_despachos'] = count($mysqlByKey);

  foreach ($sheetByKey as $key => $sheetRow) {
    if (!isset($mysqlByKey[$key])) {
      $response['summary']['missing_in_mysql']++;
      add_cliente_counter($response['by_cliente'], $sheetRow['cliente'], 'missing');
      if (count($response['missing_in_mysql']) < 25) {
        $response['missing_in_mysql'][] = $sheetRow;
      }
      continue;
    }

    $mysqlRow = $mysqlByKey[$key];
    $fields = ['cliente', 'folio', 'unidad', 'fecha', 'tramo_numero'];
    $diffs = [];
    foreach ($fields as $field) {
      $sheetValue = trim((string)($sheetRow[$field] ?? ''));
      $mysqlValue = trim((string)($mysqlRow[$field] ?? ''));
      if ($sheetValue !== $mysqlValue) {
        $diffs[$field] = ['sheet' => $sheetValue, 'mysql' => $mysqlValue];
      }
    }

    if (count($diffs)) {
      $response['summary']['mismatched_core_fields']++;
      add_cliente_counter($response['by_cliente'], $sheetRow['cliente'], 'mismatches');
      if (count($response['mismatches']) < 25) {
        $response['mismatches'][] = [
          'legacy_key' => $key,
          'diffs' => $diffs
        ];
      }
    }
  }

  foreach ($mysqlByKey as $key => $mysqlRow) {
    if (isset($sheetByKey[$key])) continue;
    $response['summary']['extra_in_mysql']++;
    add_cliente_counter($response['by_cliente'], $mysqlRow['cliente'] ?? '', 'extra');
    if (count($response['extra_in_mysql']) < 25) {
      $response['extra_in_mysql'][] = [
        'legacy_key' => $key,
        'cliente' => $mysqlRow['cliente'] ?? '',
        'folio' => $mysqlRow['folio'] ?? '',
        'unidad' => $mysqlRow['unidad'] ?? '',
        'fecha' => $mysqlRow['fecha'] ?? '',
        'tramo_numero' => intval($mysqlRow['tramo_numero'] ?? 0)
      ];
    }
  }

  ksort($response['by_cliente']);
  $response['success'] = $response['summary']['missing_in_mysql'] === 0
    && $response['summary']['extra_in_mysql'] === 0
    && $response['summary']['mismatched_core_fields'] === 0;
  $response['message'] = $response['success']
    ? 'Validación paralela correcta. Sheets y MySQL coinciden para despachos históricos.'
    : 'Validación paralela encontró diferencias.';

} catch (Exception $e) {
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
