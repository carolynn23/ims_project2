<?php
/**
 * Enhanced Security Class for Internship Management System
 * Provides comprehensive security features including input validation,
 * CSRF protection, rate limiting, and secure session management
 */

class Security {
    private static $instance = null;
    private $conn;
    private $session_timeout = 3600; // 1 hour
    private $max_login_attempts = 5;
    private $lockout_duration = 900; // 15 minutes
    
    private function __construct($database_connection) {
        $this->conn = $database_connection;
        $this->initSecureSession();
    }
    
    public static function getInstance($conn = null) {
        if (self::$instance === null) {
            self::$instance = new self($conn);
        }
        return self::$instance;
    }
    
    /**
     * Initialize secure session configuration
     */
    private function initSecureSession() {
        // Configure secure session settings
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // Set session timeout
        ini_set('session.gc_maxlifetime', $this->session_timeout);
        
        // Regenerate session ID periodically
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    /**
     * Generate CSRF Token
     */
    public function generateCSRFToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF Token
     */
    public function validateCSRFToken($token) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Enhanced input sanitization
     */
    public function sanitizeInput($input, $type = 'string') {
        if ($input === null) return null;
        
        switch ($type) {
            case 'email':
                $input = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
                return filter_var($input, FILTER_VALIDATE_EMAIL) ? $input : false;
                
            case 'url':
                $input = filter_var(trim($input), FILTER_SANITIZE_URL);
                return filter_var($input, FILTER_VALIDATE_URL) ? $input : false;
                
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT);
                
            case 'float':
                return filter_var($input, FILTER_VALIDATE_FLOAT);
                
            case 'phone':
                $input = preg_replace('/[^0-9+\-\(\)\s]/', '', $input);
                return trim($input);
                
            case 'alphanumeric':
                return preg_replace('/[^a-zA-Z0-9]/', '', $input);
                
            case 'filename':
                $input = preg_replace('/[^a-zA-Z0-9._-]/', '_', $input);
                return substr($input, 0, 255);
                
            case 'html':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
                
            default: // string
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Validate input with custom rules
     */
    public function validateInput($input, $rules) {
        $errors = [];
        
        foreach ($rules as $rule => $value) {
            switch ($rule) {
                case 'required':
                    if ($value && empty(trim($input))) {
                        $errors[] = 'This field is required';
                    }
                    break;
                    
                case 'min_length':
                    if (strlen($input) < $value) {
                        $errors[] = "Minimum length is {$value} characters";
                    }
                    break;
                    
                case 'max_length':
                    if (strlen($input) > $value) {
                        $errors[] = "Maximum length is {$value} characters";
                    }
                    break;
                    
                case 'pattern':
                    if (!preg_match($value, $input)) {
                        $errors[] = 'Invalid format';
                    }
                    break;
                    
                case 'unique_email':
                    if ($this->emailExists($input, $value)) {
                        $errors[] = 'Email address already exists';
                    }
                    break;
                    
                case 'unique_username':
                    if ($this->usernameExists($input, $value)) {
                        $errors[] = 'Username already exists';
                    }
                    break;
            }
        }
        
        return $errors;
    }
    
    /**
     * Enhanced password validation
     */
    public function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        // Check against common passwords
        $common_passwords = [
            'password', '123456', 'password123', 'admin', 'qwerty',
            'letmein', 'welcome', 'monkey', '1234567890'
        ];
        
        if (in_array(strtolower($password), $common_passwords)) {
            $errors[] = 'Password is too common. Please choose a stronger password';
        }
        
        return $errors;
    }
    
    /**
     * Secure password hashing
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    /**
     * Rate limiting for login attempts
     */
    public function checkRateLimit($identifier, $action = 'login') {
        $now = time();
        $window = 3600; // 1 hour window
        
        // Clean old attempts
        $clean_stmt = $this->conn->prepare(
            "DELETE FROM rate_limits WHERE created_at < ? AND action = ?"
        );
        $clean_time = $now - $window;
        $clean_stmt->bind_param("is", $clean_time, $action);
        $clean_stmt->execute();
        
        // Check current attempts
        $check_stmt = $this->conn->prepare(
            "SELECT COUNT(*) as attempts FROM rate_limits 
             WHERE identifier = ? AND action = ? AND created_at > ?"
        );
        $check_time = $now - $window;
        $check_stmt->bind_param("ssi", $identifier, $action, $check_time);
        $check_stmt->execute();
        $result = $check_stmt->get_result()->fetch_assoc();
        
        return $result['attempts'] < $this->max_login_attempts;
    }
    
    /**
     * Record failed attempt
     */
    public function recordFailedAttempt($identifier, $action = 'login') {
        $stmt = $this->conn->prepare(
            "INSERT INTO rate_limits (identifier, action, created_at) VALUES (?, ?, ?)"
        );
        $now = time();
        $stmt->bind_param("ssi", $identifier, $action, $now);
        $stmt->execute();
    }
    
    /**
     * Secure file upload validation
     */
    public function validateFileUpload($file, $allowed_types = [], $max_size = 5242880) { // 5MB default
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error occurred';
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors[] = 'File size exceeds maximum allowed size';
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!empty($allowed_types) && !in_array($mime_type, $allowed_types)) {
            $errors[] = 'File type not allowed';
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = [
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif']
        ];
        
        if (isset($allowed_extensions[$mime_type]) && 
            !in_array($extension, $allowed_extensions[$mime_type])) {
            $errors[] = 'File extension does not match file type';
        }
        
        return $errors;
    }
    
    /**
     * Generate secure filename
     */
    public function generateSecureFilename($original_filename, $user_id = null) {
        $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $user_part = $user_id ? "_{$user_id}" : '';
        
        return "file_{$timestamp}{$user_part}_{$random}.{$extension}";
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent($event_type, $details, $user_id = null, $severity = 'INFO') {
        $stmt = $this->conn->prepare(
            "INSERT INTO security_logs (user_id, event_type, details, severity, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        
        $ip_address = $this->getRealIP();
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        
        $stmt->bind_param("isssss", $user_id, $event_type, $details, $severity, $ip_address, $user_agent);
        $stmt->execute();
    }
    
    /**
     * Get real IP address
     */
    private function getRealIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Check if email exists
     */
    private function emailExists($email, $exclude_id = null) {
        if ($exclude_id) {
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt->bind_param("si", $email, $exclude_id);
        } else {
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
        }
        
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    /**
     * Check if username exists
     */
    private function usernameExists($username, $exclude_id = null) {
        if ($exclude_id) {
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->bind_param("si", $username, $exclude_id);
        } else {
            $stmt = $this->conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
        }
        
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encryptData($data, $key = null) {
        if ($key === null) {
            $key = $this->getEncryptionKey();
        }
        
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decryptData($encrypted_data, $key = null) {
        if ($key === null) {
            $key = $this->getEncryptionKey();
        }
        
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Get encryption key from environment
     */
    private function getEncryptionKey() {
        $key = getenv('ENCRYPTION_KEY') ?: 'your-32-character-secret-key-here!';
        return hash('sha256', $key, true);
    }
    
    /**
     * Clean and validate SQL queries
     */
    public function sanitizeSQL($query) {
        // Remove dangerous SQL keywords
        $dangerous_keywords = [
            'DROP', 'DELETE FROM users', 'TRUNCATE', 'ALTER TABLE',
            'CREATE USER', 'GRANT', 'REVOKE', 'LOAD_FILE'
        ];
        
        foreach ($dangerous_keywords as $keyword) {
            if (stripos($query, $keyword) !== false) {
                $this->logSecurityEvent('SQL_INJECTION_ATTEMPT', "Dangerous keyword detected: {$keyword}", null, 'HIGH');
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Content Security Policy header
     */
    public function setSecurityHeaders() {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'");
    }
}
?>