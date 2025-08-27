<?php
/**
 * Enhanced Secure Configuration File
 * Replace your existing config.php with this enhanced version
 */

// Prevent direct access
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Start output buffering and session management
ob_start();

// Include security class
require_once __DIR__ . '/security.php';

// Environment Configuration
define('ENVIRONMENT', 'development'); // Change to 'production' for live site
define('DEBUG_MODE', ENVIRONMENT === 'development');

// Security Constants
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'your-32-character-secret-key-change-this!');
define('CSRF_TOKEN_EXPIRY', 3600); // 1 hour
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('ACCOUNT_LOCKOUT_TIME', 900); // 15 minutes

// File Upload Configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOCUMENT_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);
define('UPLOAD_PATH', __DIR__ . '/uploads/');

// Database Configuration with enhanced security
class SecureDatabase {
    private static $instance = null;
    private $conn;
    private $host;
    private $user;
    private $password;
    private $dbname;
    
    private function __construct() {
        // Use environment variables for database credentials
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->user = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASSWORD') ?: '';
        $this->dbname = getenv('DB_NAME') ?: 'internship_db';
        
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            // Enhanced connection with SSL and security options
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'"
            ];
            
            // For production, enable SSL
            if (ENVIRONMENT === 'production') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = '/path/to/ca-cert.pem';
                $options[PDO::MYSQL_ATTR_SSL_CERT] = '/path/to/client-cert.pem';
                $options[PDO::MYSQL_ATTR_SSL_KEY] = '/path/to/client-key.pem';
            }
            
            $this->conn = new PDO($dsn, $this->user, $this->password, $options);
            
            // Set timezone
            $this->conn->exec("SET time_zone = '+00:00'");
            
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                error_log("Database connection failed: " . $e->getMessage());
                die("System temporarily unavailable. Please try again later.");
            }
        }
        
        // For backward compatibility with mysqli
        $this->createMysqliConnection();
    }
    
    private function createMysqliConnection() {
        try {
            global $conn;
            $conn = new mysqli($this->host, $this->user, $this->password, $this->dbname);
            
            if ($conn->connect_error) {
                throw new Exception("MySQL connection failed: " . $conn->connect_error);
            }
            
            $conn->set_charset("utf8mb4");
            
        } catch (Exception $e) {
            if (DEBUG_MODE) {
                die("MySQLi connection failed: " . $e->getMessage());
            } else {
                error_log("MySQLi connection failed: " . $e->getMessage());
                die("System temporarily unavailable. Please try again later.");
            }
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function getMysqliConnection() {
        global $conn;
        return $conn;
    }
    
    public function prepare($sql) {
        // Log potentially dangerous queries in development
        if (DEBUG_MODE && $this->isDangerousQuery($sql)) {
            error_log("Potentially dangerous query: " . $sql);
        }
        
        return $this->conn->prepare($sql);
    }
    
    private function isDangerousQuery($sql) {
        $dangerous_patterns = [
            '/DROP\s+TABLE/i',
            '/DELETE\s+FROM\s+users/i',
            '/TRUNCATE/i',
            '/ALTER\s+TABLE/i',
            '/GRANT/i',
            '/REVOKE/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return true;
            }
        }
        
        return false;
    }
}

// Initialize database connection
$db = SecureDatabase::getInstance();
$pdo = $db->getConnection();
$conn = $db->getMysqliConnection(); // For backward compatibility

// Initialize Security Class
$security = Security::getInstance($conn);

// Set security headers
$security->setSecurityHeaders();

// Error Handling Configuration
if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Custom Error Handler
function customErrorHandler($severity, $message, $file, $line) {
    global $security;
    
    $error_details = [
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'severity' => $severity
    ];
    
    // Log security-related errors
    if (strpos($message, 'SQL') !== false || 
        strpos($message, 'injection') !== false ||
        strpos($message, 'XSS') !== false) {
        
        $security->logSecurityEvent(
            'SECURITY_ERROR',
            json_encode($error_details),
            $_SESSION['user_id'] ?? null,
            'HIGH'
        );
    }
    
    if (ENVIRONMENT === 'production') {
        error_log("Error: $message in $file on line $line");
        return true; // Don't execute PHP internal error handler
    }
    
    return false; // Execute PHP internal error handler
}

set_error_handler('customErrorHandler');

// Utility Functions for Security
function csrf_token() {
    global $security;
    return $security->generateCSRFToken();
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf($token = null) {
    global $security;
    
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }
    
    if (!$security->validateCSRFToken($token)) {
        $security->logSecurityEvent(
            'CSRF_TOKEN_INVALID',
            'Invalid CSRF token attempt',
            $_SESSION['user_id'] ?? null,
            'HIGH'
        );
        
        if (ENVIRONMENT === 'production') {
            http_response_code(403);
            die('Access denied');
        } else {
            die('CSRF token validation failed');
        }
    }
    
    return true;
}

function sanitize($input, $type = 'string') {
    global $security;
    return $security->sanitizeInput($input, $type);
}

function validate_input($input, $rules) {
    global $security;
    return $security->validateInput($input, $rules);
}

function secure_redirect($url, $status_code = 302) {
    // Validate URL to prevent open redirects
    $allowed_hosts = [
        $_SERVER['HTTP_HOST'],
        'localhost',
        '127.0.0.1'
    ];
    
    $parsed_url = parse_url($url);
    
    if (isset($parsed_url['host']) && !in_array($parsed_url['host'], $allowed_hosts)) {
        $url = '/'; // Redirect to home if invalid host
    }
    
    header("Location: $url", true, $status_code);
    exit;
}

function require_auth($required_role = null) {
    if (!isset($_SESSION['user_id'])) {
        secure_redirect('login.php');
    }
    
    if ($required_role && $_SESSION['role'] !== $required_role) {
        http_response_code(403);
        die('Access denied: Insufficient privileges');
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        secure_redirect('login.php?timeout=1');
    }
    
    $_SESSION['last_activity'] = time();
}

function require_https() {
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        if (ENVIRONMENT === 'production') {
            $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            secure_redirect($redirect_url, 301);
        }
    }
}

function log_security_event($event_type, $details, $severity = 'INFO') {
    global $security;
    $security->logSecurityEvent(
        $event_type,
        $details,
        $_SESSION['user_id'] ?? null,
        $severity
    );
}

function check_rate_limit($action = 'login') {
    global $security;
    
    $identifier = $_SERVER['REMOTE_ADDR'];
    if (isset($_POST['email'])) {
        $identifier .= '_' . $_POST['email'];
    }
    
    if (!$security->checkRateLimit($identifier, $action)) {
        log_security_event('RATE_LIMIT_EXCEEDED', "Action: $action, IP: {$_SERVER['REMOTE_ADDR']}", 'WARNING');
        
        http_response_code(429);
        die('Too many attempts. Please try again later.');
    }
    
    return true;
}

function hash_password($password) {
    global $security;
    return $security->hashPassword($password);
}

function validate_password($password) {
    global $security;
    return $security->validatePassword($password);
}

function secure_file_upload($file, $allowed_types = [], $max_size = MAX_FILE_SIZE) {
    global $security;
    
    $errors = $security->validateFileUpload($file, $allowed_types, $max_size);
    
    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }
    
    $secure_filename = $security->generateSecureFilename(
        $file['name'],
        $_SESSION['user_id'] ?? null
    );
    
    $upload_path = UPLOAD_PATH . $secure_filename;
    
    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return ['success' => true, 'filename' => $secure_filename];
    }
    
    return ['success' => false, 'errors' => ['Failed to move uploaded file']];
}

// Initialize secure session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// For production: Require HTTPS
if (ENVIRONMENT === 'production') {
    require_https();
}

// Create logs directory if it doesn't exist
$logs_dir = __DIR__ . '/logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

// Define application constants
define('APP_NAME', 'InternHub');
define('APP_VERSION', '2.0.0');
define('APP_URL', (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);

// Success - Configuration loaded
if (DEBUG_MODE) {
    error_log("Secure configuration loaded successfully");
}
?>