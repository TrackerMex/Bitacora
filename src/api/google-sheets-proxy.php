<?php
/**
 * src/api/google-sheets-proxy.php
 * 
 * Proxy seguro para Google Sheets API
 * - Oculta la API key del frontend
 * - Valida requests
 * - Cachea datos para reducir llamadas a Google
 */

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=300'); // Cache de 5 minutos

// Cargar configuración
require_once __DIR__ . '/../../config/environment.php';

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Validar método
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener parámetros
    $action = isset($_GET['action']) ? (string)$_GET['action'] : '';
    
    if (!$action) {
        throw new Exception('Parámetro "action" requerido');
    }
    
    // Validar acción permitida
    $allowed_actions = ['bitacora', 'contactos'];
    if (!in_array($action, $allowed_actions)) {
        throw new Exception('Acción no permitida');
    }
    
    // Obtener API key desde variables de entorno
    $api_key = getEnvVar('GOOGLE_SHEETS_API_KEY');
    $spreadsheet_id = getEnvVar('GOOGLE_SHEETS_SPREADSHEET_ID');
    
    if (!$api_key || !$spreadsheet_id) {
        throw new Exception('Configuración de Google Sheets no disponible');
    }
    
    // Determinar rango según acción
    $range = match($action) {
        'bitacora' => getEnvVar('GOOGLE_SHEETS_RANGE_BITACORA', 'BITACORA!A1:O944'),
        'contactos' => getEnvVar('GOOGLE_SHEETS_RANGE_CONTACTOS', 'Contactos!A1:H'),
        default => ''
    };
    
    if (!$range) {
        throw new Exception('Rango no configurado para esta acción');
    }
    
    // Construir URL de Google Sheets API
    $url = sprintf(
        'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?key=%s',
        urlencode($spreadsheet_id),
        urlencode($range),
        urlencode($api_key)
    );
    
    // Hacer request a Google
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $sheet_response = @file_get_contents($url, false, $context);
    
    if ($sheet_response === false) {
        throw new Exception('No se pudo conectar con Google Sheets');
    }
    
    $sheet_data = json_decode($sheet_response, true);
    
    if (!is_array($sheet_data)) {
        throw new Exception('Respuesta inválida de Google Sheets');
    }
    
    if (isset($sheet_data['error'])) {
        throw new Exception('Error de Google Sheets: ' . 
            ($sheet_data['error']['message'] ?? 'Error desconocido'));
    }
    
    $response['success'] = true;
    $response['message'] = 'Datos obtenidos correctamente';
    $response['data'] = $sheet_data['values'] ?? [];
    
} catch (Exception $e) {
    http_response_code(400);
    $response['success'] = false;
    $response['message'] = DEBUG ? $e->getMessage() : 'Error al obtener datos';
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();

?>
