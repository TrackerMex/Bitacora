<?php
// eliminar_informe.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

try {
    require 'db.php';
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido. Use POST.");
    }
    
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    $id = $data['id'] ?? null;
    
    if (!$id || !is_numeric($id)) {
        throw new Exception("ID no especificado o inválido");
    }
    
    $stmt = $conn->prepare("DELETE FROM informes_guardados WHERE id = ?");
    
    if (!$stmt) {
        throw new Exception("Error en la preparación: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        
        if ($affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Informe eliminado correctamente'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró el informe para eliminar'
            ], JSON_UNESCAPED_UNICODE);
        }
    } else {
        throw new Exception("Error al ejecutar: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
?>