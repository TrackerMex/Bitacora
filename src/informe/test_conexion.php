<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'message' => 'PHP está funcionando',
    'php_version' => PHP_VERSION,
    'time' => date('Y-m-d H:i:s')
]);
?>