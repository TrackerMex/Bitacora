<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../config/environment.php';

$response = [
  'success' => false,
  'message' => '',
];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido. Use POST.');
  }

  $apps_script_url = getEnvVar('GOOGLE_APPS_SCRIPT_URL');
  if (!$apps_script_url) {
    throw new Exception(
      'URL del Apps Script no configurada. ' .
      'Agrega GOOGLE_APPS_SCRIPT_URL en el archivo .env del servidor.'
    );
  }

  $raw  = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    throw new Exception('Body JSON inválido.');
  }

  $unidad     = trim((string)($data['unidad']     ?? ''));
  $fechaActual = trim((string)($data['fechaActual'] ?? ''));
  $fechaNueva  = trim((string)($data['fechaNueva']  ?? ''));

  if ($unidad === '' || $fechaActual === '' || $fechaNueva === '') {
    throw new Exception('Faltan campos requeridos: unidad, fechaActual, fechaNueva');
  }

  $payload = json_encode([
    'unidad'      => $unidad,
    'fechaActual' => $fechaActual,
    'fechaNueva'  => $fechaNueva,
  ], JSON_UNESCAPED_UNICODE);

  $context = stream_context_create([
    'http' => [
      'method'        => 'POST',
      'header'        => "Content-Type: application/json\r\n",
      'content'       => $payload,
      'timeout'       => 15,
      'ignore_errors' => true,
      'follow_location' => 1,   // Google Apps Script redirige a la URL canónica
      'max_redirects'   => 5,
    ],
  ]);

  $raw_result = @file_get_contents($apps_script_url, false, $context);
  if ($raw_result === false) {
    throw new Exception('No se pudo conectar con Google Apps Script. Verifica la URL en GOOGLE_APPS_SCRIPT_URL.');
  }

  $json = json_decode($raw_result, true);
  if (!is_array($json)) {
    throw new Exception('Respuesta inválida del Apps Script: ' . substr($raw_result, 0, 200));
  }

  $response = $json;

} catch (Exception $e) {
  http_response_code(500);
  $response['success'] = false;
  $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
