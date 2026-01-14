<?php
/**
 * config/security.php
 * 
 * Utilidades de seguridad centralizadas
 * - Validación de input
 * - CORS headers
 * - Rate limiting
 * - Session management
 */

require_once __DIR__ . '/environment.php';

/**
 * Establecer headers de seguridad CORS
 */
function setSecurityHeaders() {
    // Versión simplificada - agregar en cada endpoint
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    
    // CORS restringido
    $allowed_origins = array_filter(
        explode(',', getEnvVar('ALLOWED_ORIGINS', 'http://localhost'))
    );
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array(trim($origin), array_map('trim', $allowed_origins))) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
    
    // Responder a preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

/**
 * Validar y sanitizar input JSON
 */
function getJsonInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    
    if (!is_array($data)) {
        throw new Exception('Invalid JSON input');
    }
    
    return $data;
}

/**
 * Validar que un valor sea alfanumérico + guiones/guiones bajos
 */
function isAlphanumeric($value, $allow_special = false) {
    $pattern = $allow_special 
        ? '/^[a-zA-Z0-9\-_\s]*$/' 
        : '/^[a-zA-Z0-9]*$/';
    
    return preg_match($pattern, (string)$value) === 1;
}

/**
 * Validar que sea una fecha válida en formato YYYY-MM-DD
 */
function isValidDate($date_string) {
    $date = \DateTime::createFromFormat('Y-m-d', $date_string);
    return $date && $date->format('Y-m-d') === $date_string;
}

/**
 * Validar que sea una fecha/hora válida en formato YYYY-MM-DD HH:mm:ss
 */
function isValidDateTime($datetime_string) {
    $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $datetime_string);
    return $dt && $dt->format('Y-m-d H:i:s') === $datetime_string;
}

/**
 * Escape HTML para prevenir XSS
 */
function escapeHtml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Rate limiting simple por IP
 */
class RateLimiter {
    private static $storage = [];
    
    public static function isAllowed($key, $max_requests = 100, $window_seconds = 3600) {
        $now = time();
        $window_start = $now - $window_seconds;
        
        if (!isset(self::$storage[$key])) {
            self::$storage[$key] = [];
        }
        
        // Limpiar requests antiguos
        self::$storage[$key] = array_filter(
            self::$storage[$key],
            fn($t) => $t > $window_start
        );
        
        // Verificar límite
        if (count(self::$storage[$key]) >= $max_requests) {
            return false;
        }
        
        // Registrar nuevo request
        self::$storage[$key][] = $now;
        return true;
    }
    
    public static function reset($key) {
        unset(self::$storage[$key]);
    }
}

/**
 * Session management seguro
 */
class SessionManager {
    public static function init() {
        session_set_cookie_params([
            'lifetime' => getEnvInt('SESSION_TIMEOUT', 120) * 60,
            'path' => '/',
            'domain' => '',
            'secure' => getEnvBool('SESSION_SECURE_ONLY', true),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        session_start();
    }
    
    public static function isValid() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        $timeout = getEnvInt('SESSION_TIMEOUT', 120) * 60;
        if (time() - $_SESSION['last_activity'] > $timeout) {
            self::destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public static function setUser($user_id, $role) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_role'] = $role;
        $_SESSION['last_activity'] = time();
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
    }
    
    public static function destroy() {
        session_destroy();
        setcookie(session_name(), '', time() - 3600);
    }
}

/**
 * Logging de actividades
 */
class Logger {
    const LOG_ERROR = 'error';
    const LOG_WARNING = 'warning';
    const LOG_INFO = 'info';
    const LOG_DEBUG = 'debug';
    
    public static function log($level, $message, $context = []) {
        $log_dir = __DIR__ . '/../logs';
        
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        
        $log_file = $log_dir . '/' . $level . '.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $entry = "[$timestamp] $message";
        if (!empty($context)) {
            $entry .= "\n  Context: " . json_encode($context);
        }
        $entry .= "\n";
        
        @file_put_contents($log_file, $entry, FILE_APPEND);
    }
    
    public static function error($message, $context = []) {
        self::log(self::LOG_ERROR, $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log(self::LOG_WARNING, $message, $context);
    }
    
    public static function info($message, $context = []) {
        self::log(self::LOG_INFO, $message, $context);
    }
    
    public static function debug($message, $context = []) {
        if (DEBUG) {
            self::log(self::LOG_DEBUG, $message, $context);
        }
    }
}

?>
