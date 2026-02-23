<?php

error_reporting(0);
ini_set('display_errors', 0);

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

$response = [
  'success' => false,
  'message' => ''
];

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new Exception('Método no permitido. Use POST.');
  }

  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!is_array($data)) {
    throw new Exception('Body JSON inválido.');
  }

  // Validar campos requeridos
  $email = isset($data['username']) ? trim((string)$data['username']) : '';
  $password = isset($data['password']) ? trim((string)$data['password']) : '';

  if ($email === '' || $password === '') {
    throw new Exception('Faltan campos requeridos: email y password');
  }

  // Configuración de Google Sheets API
  $apiKey = 'AIzaSyDUt__oU7d7NkHcJoIkTNNR9-MVmVJODhM';
  $spreadsheetId = '1JX9wnA2Jkox3Dk0eYRXSJMiT8VHuoZ9uYfdGJu33CAc';
  
  // Primero verificar en hoja "Usuarios" si existe
  $rangeUsuarios = 'Usuarios!A1:F100';
  $getUrlUsuarios = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$rangeUsuarios}?key={$apiKey}";
  
  $ch = curl_init($getUrlUsuarios);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  $getResponse = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200) {
    throw new Exception('Error al obtener datos de Google Sheets: HTTP ' . $httpCode);
  }

  $sheetData = json_decode($getResponse, true);
  if (!isset($sheetData['values']) || !is_array($sheetData['values'])) {
    throw new Exception('No se pudieron obtener datos de la hoja Usuarios');
  }

  $rows = $sheetData['values'];
  
  // La primera fila son los headers
  if (count($rows) < 2) {
    throw new Exception('La hoja Usuarios está vacía');
  }

  // Buscar usuario por email (empezar desde fila 2, índice 1)
  $userFound = null;
  for ($i = 1; $i < count($rows); $i++) {
    $row = $rows[$i];
    
    // Estructura sin columna unidades:
    // A = email, B = password, C = role, D = cliente, E = tabs_permitidos, F = activo
    $rowEmail = isset($row[0]) ? trim(strtolower((string)$row[0])) : '';
    $rowPassword = isset($row[1]) ? trim((string)$row[1]) : '';
    $rowRole = isset($row[2]) ? trim((string)$row[2]) : '';
    $rowCliente = isset($row[3]) ? trim((string)$row[3]) : '';
    $rowTabs = isset($row[4]) ? trim((string)$row[4]) : '';
    $rowActivo = isset($row[5]) ? trim(strtoupper((string)$row[5])) : 'FALSE';
    
    // Verificar si es el usuario buscado (email case-insensitive)
    if ($rowEmail === strtolower($email)) {
      // Verificar si está activo
      if ($rowActivo !== 'TRUE' && $rowActivo !== '1') {
        throw new Exception('Usuario desactivado. Contacte al administrador.');
      }
      
      // Verificar password
      if ($rowPassword === $password) {
        // Parsear tabs (separados por coma, convertir a enteros)
        $tabsArray = [];
        if ($rowTabs !== '') {
          $tabsParts = explode(',', $rowTabs);
          foreach ($tabsParts as $tab) {
            $tabNum = intval(trim($tab));
            $tabsArray[] = $tabNum;
          }
        }
        
        // Ahora buscar las unidades asignadas en la hoja "Datos" (columna O = Lector Responsable)
        $unidadesArray = [];
        
        // Si es admin/editor, darle acceso a todo
        if ($rowRole === 'admin' || $rowRole === 'editor') {
          $unidadesArray = ['*'];
        } else {
          // Obtener datos de la hoja "Datos" para encontrar unidades asignadas
          $rangeDatos = 'Datos!A1:O1000';
          $getUrlDatos = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$rangeDatos}?key={$apiKey}";
          
          $ch2 = curl_init($getUrlDatos);
          curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
          $getDatosResponse = curl_exec($ch2);
          $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
          curl_close($ch2);
          
          if ($httpCode2 === 200) {
            $datosData = json_decode($getDatosResponse, true);
            if (isset($datosData['values']) && is_array($datosData['values'])) {
              $datosRows = $datosData['values'];
              
              // Buscar en columna O (índice 14) el email del usuario
              for ($j = 1; $j < count($datosRows); $j++) { // Empezar desde fila 2 (índice 1)
                $datosRow = $datosRows[$j];
                
                // Columna B = Unidad (índice 1), Columna O = Lector Responsable (índice 14)
                $lectorEmail = isset($datosRow[14]) ? trim(strtolower((string)$datosRow[14])) : '';
                $unidad = isset($datosRow[1]) ? trim((string)$datosRow[1]) : '';
                
                // Si el email coincide, agregar la unidad
                if ($lectorEmail === strtolower($email) && $unidad !== '') {
                  if (!in_array($unidad, $unidadesArray)) {
                    $unidadesArray[] = $unidad;
                  }
                }
              }
            }
          }
          
          // Si no encontró ninguna unidad asignada, dejar el array vacío (no verá nada)
          if (empty($unidadesArray)) {
            $unidadesArray = [];
          }
        }
        
        $userFound = [
          'username' => $rowEmail,
          'role' => $rowRole,
          'cliente' => $rowCliente,
          'unidades' => $unidadesArray,
          'tabs' => $tabsArray
        ];
        break;
      } else {
        throw new Exception('Email o contraseña incorrectos');
      }
    }
  }

  if ($userFound === null) {
    throw new Exception('Email o contraseña incorrectos');
  }

  $response['success'] = true;
  $response['message'] = 'Login exitoso';
  $response['user'] = $userFound;

} catch (Exception $e) {
  http_response_code(400);
  $response['success'] = false;
  $response['message'] = $e->getMessage();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();
