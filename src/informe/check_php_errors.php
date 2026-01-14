<?php
// check_php_errors.php - Revisa todos los archivos PHP por errores
header('Content-Type: text/plain; charset=utf-8');

$files = [
    'db.php',
    'guardar_informe.php', 
    'obtener_informes.php',
    'obtener_informe_detalle.php',
    'eliminar_informe.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        echo "❌ $file: NO EXISTE\n";
        continue;
    }
    
    echo "🔍 $file:\n";
    
    // Ejecutar el archivo con output buffering
    ob_start();
    
    try {
        include $file;
        $output = ob_get_clean();
        
        if (!empty(trim($output))) {
            echo "   ⚠️  GENERA OUTPUT:\n";
            echo "   " . str_replace("\n", "\n   ", substr($output, 0, 500)) . "\n";
            
            // Verificar si es JSON
            json_decode($output);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "   ✅ Output es JSON válido\n";
            } else {
                echo "   ❌ Output NO es JSON: " . json_last_error_msg() . "\n";
            }
        } else {
            echo "   ✅ No genera output\n";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}
?>