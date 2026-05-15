<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/environment.php';

$response = [
  'success' => true,
  'backup_success' => false,
  'backup_skipped' => false,
  'message' => ''
];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Metodo no permitido. Use POST.');
  }

  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    throw new Exception('Body JSON invalido.');
  }

  $unidad = trim((string)($data['unidad'] ?? ''));
  $fechaActual = trim((string)($data['fechaActual'] ?? ''));
  $fechaNueva = trim((string)($data['fechaNueva'] ?? ''));

  if ($unidad === '' || $fechaActual === '' || $fechaNueva === '') {
    throw new Exception('Faltan campos requeridos: unidad, fechaActual, fechaNueva');
  }

  $backup_enabled = getEnvBool('GOOGLE_SHEETS_BACKUP_ENABLED', true);
  if (!$backup_enabled) {
    $response['backup_skipped'] = true;
    $response['message'] = 'Respaldo de Google Sheets desactivado.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
  }

  $apps_script_url = getEnvVar('GOOGLE_APPS_SCRIPT_URL');
  if (!$apps_script_url) {
    $response['backup_skipped'] = true;
    $response['message'] = 'GOOGLE_APPS_SCRIPT_URL no configurado. Respaldo omitido.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
  }

  $payload = json_encode([
    'unidad' => $unidad,
    'fechaActual' => $fechaActual,
    'fechaNueva' => $fechaNueva
  ], JSON_UNESCAPED_UNICODE);

  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/json\r\n",
      'content' => $payload,
      'timeout' => 15,
      'ignore_errors' => true,
      'follow_location' => 1,
      'max_redirects' => 5
    ]
  ]);

  $raw_result = @file_get_contents($apps_script_url, false, $context);
  if ($raw_result === false) {
    $response['message'] = 'No se pudo conectar con Google Apps Script. Respaldo pendiente.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
  }

  $json = json_decode($raw_result, true);
  if (!is_array($json)) {
    $response['message'] = 'Respuesta invalida del Apps Script. Respaldo pendiente.';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
  }

  $response['backup_success'] = ($json['success'] ?? false) === true;
  $response['message'] = $response['backup_success']
    ? 'Respaldo de Google Sheets actualizado.'
    : (($json['message'] ?? null) ?: 'Apps Script no confirmo el respaldo.');
  $response['apps_script_response'] = $json;

} catch (Exception $e) {
  http_response_code(500);
  $response['success'] = false;
  $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
