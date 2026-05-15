<?php
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
  'success' => true,
  'service' => 'bitacora',
  'status' => 'ok',
], JSON_UNESCAPED_UNICODE);
