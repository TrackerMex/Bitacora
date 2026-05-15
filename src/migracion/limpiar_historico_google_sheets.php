<?php

error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

$response = [
  'success' => false,
  'message' => '',
  'deleted' => [
    'despachos' => 0,
    'unidades' => 0,
    'migration_map' => 0
  ]
];

try {
  $confirm = '';
  if (PHP_SAPI === 'cli') {
    $confirm = $argv[1] ?? '';
  } else {
    $confirm = $_GET['confirm'] ?? '';
  }

  if ($confirm !== 'limpiar_google_sheets') {
    throw new Exception('Confirmación requerida.');
  }

  require_once __DIR__ . '/../db/db.php';

  $conn->begin_transaction();

  $conn->query(
    "DELETE FROM despachos
      WHERE source_system = 'google_sheets'
         OR id IN (
           SELECT target_id
             FROM migration_map
            WHERE source_system = 'google_sheets'
              AND target_table = 'despachos'
         )"
  );
  if ($conn->error) throw new Exception('Error eliminando despachos: ' . $conn->error);
  $response['deleted']['despachos'] = $conn->affected_rows;

  $conn->query(
    "DELETE FROM unidades
      WHERE id IN (
        SELECT target_id
          FROM migration_map
         WHERE source_system = 'google_sheets'
           AND target_table = 'unidades'
      )"
  );
  if ($conn->error) throw new Exception('Error eliminando unidades: ' . $conn->error);
  $response['deleted']['unidades'] = $conn->affected_rows;

  $conn->query("DELETE FROM migration_map WHERE source_system = 'google_sheets'");
  if ($conn->error) throw new Exception('Error eliminando migration_map: ' . $conn->error);
  $response['deleted']['migration_map'] = $conn->affected_rows;

  $conn->commit();

  $response['success'] = true;
  $response['message'] = 'Migración histórica google_sheets limpiada.';
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
