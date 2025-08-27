<?php
/**
 * Authentication Middleware
 * Include this file in all protected pages to enforce security policies
 * 
 * Usage: 
 * require_once 'middleware/auth_middleware.php';
 * AuthMiddleware::requireAuth('student'); // or 'employer', 'alumni'
 */

class AuthMiddleware {
    private static $security;
    private static $conn;
    
    public static function init($security_instance, $database_connection) {
        self::$security = $security_instance;
        self::$conn = $database_connection;
    }
    
    /**
     * Require authentication with optional role check
     */
    public static function requireAuth($required_role = null) {
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            self::redirectToLogin('You must be logged in to access this page.');
            return;
        }
        
        // Check role if specified
        if ($required_role && $_SESSION['role'] !== $required_role) {
            self::handleUnauthorizedAccess($required_role);
            return;
        }
        
        // Validate session
        if (!self::validateSession()) {
            return;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        // Log page access
        self::logPageAccess();
        
        return true;
    }
    
    /**
     * Allow multiple roles
     */
    public static function requireRoles($allowed_roles = []) {
        if (!isset($_SESSION['user_id'])) {
            self::redirectToLogin('You must be logged in to access this page.');
            return;
        }
        
        if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
            self::handleUnauthorizedAccess(implode(', ', $allowed_roles));
            return;
        }
        
        return self::validateSession();
    }
    
    /**
     * Validate current session
     */
    private static function validateSession() {
        $user_id = $_SESSION['user_id'];
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            
            self::logSecurityEvent('SESSION_TIMEOUT', "User session expired", $user_id, 'INFO');
            self::destroySession();
            self::redirectToLogin('Your session has expired. Please log in again.');
            return false;
        }
        
        // Validate session in database
        $session_id = session_id();
        $stmt = self::$conn->prepare("
            SELECT user_id, expires_at, is_active 
            FROM user_sessions 
            WHERE session_id = ? AND user_id = ?
        ");
        $stmt->bind_param("si", $session_id, $user_id);
        $stmt->execute();
        $session_data = $stmt->get_result()->fetch_assoc();
        
        if (!$session_data) {
            self::logSecurityEvent('SESSION_NOT_FOUND', "Session not found in database", $user_id, 'WARNING');
            self::destroySession();
            self::redirectToLogin('Invalid session. Please log in again.');
            return false;
        }
        
        if (!$session_data['is_active']) {
            self::logSecurityEvent('SESSION_INACTIVE', "Inactive session used", $user_id, 'WARNING');
            self::destroySession();
            self::redirectToLogin('Session is no longer active. Please log in again.');
            return false;
        }
        
        if (strtotime($session_data['expires_at']) < time()) {
            self::logSecurityEvent('SESSION_EXPIRED', "Expired session used", $user_id, 'INFO');
            
            // Clean up expired session
            $cleanup_stmt = self::$conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            $cleanup_stmt->bind_param("s", $session_id);
            $cleanup_stmt->execute();
            
            self::destroySession();
            self::redirectToLogin('Your session has expired. Please log in again.');
            return false;
        }
        
        // Check if user account is still active
        $user_stmt = self::$conn->prepare("
            SELECT status, account_locked, locked_until 
            FROM users 
            WHERE user_id = ?
        ");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_data = $user_stmt->get_result()->fetch_assoc();
        
        if (!$user_data || $user_data['status'] !== 'active') {
            self::logSecurityEvent('USER_ACCOUNT_INACTIVE', "Inactive user attempted access", $user_id, 'WARNING');
            self::destroySession();
            self::redirectToLogin('Your account is no longer active.');
            return false;
        }
        
        if ($user_data['account_locked']) {
            $locked_until = $user_data['locked_until'] ? strtotime($user_data['locked_until']) : null;
            
            if ($locked_until === null || $locked_until > time()) {
                self::logSecurityEvent('LOCKED_USER_ACCESS', "Locked user attempted access", $user_id, 'WARNING');
                self::destroySession();
                self::redirectToLogin('Your account is temporarily locked. Please try again later.');
                return false;
            } else {
                // Unlock account if lock period has expired
                $unlock_stmt = self::$conn->prepare("
                    UPDATE users 
                    SET account_locked = FALSE, locked_until = NULL, failed_login_attempts = 0 
                    WHERE user_id = ?
                ");
                $unlock_stmt->bind_param("i", $user_id);
                $unlock_stmt->execute();
                
                self::logSecurityEvent('ACCOUNT_AUTO_UNLOCKED', "Account automatically unlocked", $user_id, 'INFO');
            }
        }
        
        // Update session activity
        $update_stmt = self::$conn->prepare("
            UPDATE user_sessions 
            SET last_activity = NOW() 
            WHERE session_id = ?
        ");
        $update_stmt->bind_param("s", $session_id);
        $update_stmt->execute();
        
        return true;
    }
    
    /**
     * Check if user has specific permission
     */
    public static function hasPermission($permission) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $role = $_SESSION['role'];
        
        // Define role-based permissions
        $permissions = [
            'student' => [
                'view_internships',
                'apply_internships',
                'view_applications',
                'update_profile',
                'view_notifications'
            ],
            'employer' => [
                'post_internships',
                'edit_internships',
                'view_applications',
                'manage_applications',
                'view_feedback',
                'update_profile'
            ],
            'alumni' => [
                'mentor_students',
                'view_directory',
                'post_experiences',
                'update_profile'
            ],
            'admin' => [
                'manage_users',
                'view_analytics',
                'system_settings',
                'security_logs'
            ]
        ];
        
        return in_array($permission, $permissions[$role] ?? []);
    }
    
    /**
     * Require specific permission
     */
    public static function requirePermission($permission) {
        if (!self::hasPermission($permission)) {
            self::handleUnauthorizedAccess("permission: $permission");
            return false;
        }
        return true;
    }
    
    /**
     * Check CSRF token for forms
     */
    public static function requireCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
            
            if (!self::$security->validateCSRFToken($token)) {
                self::logSecurityEvent('CSRF_VALIDATION_FAILED', 'CSRF token validation failed', $_SESSION['user_id'] ?? null, 'HIGH');
                
                http_response_code(403);
                if (ENVIRONMENT === 'development') {
                    die('CSRF token validation failed. Please refresh the page and try again.');
                } else {
                    die('Security validation failed. Please refresh the page and try again.');
                }
            }
        }
        return true;
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($action = 'page_access', $limit = 100, $window = 3600) {
        $identifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
        
        if (!self::$security->checkRateLimit($identifier . '_' . $action, $action)) {
            self::logSecurityEvent('RATE_LIMIT_EXCEEDED', "Action: $action", $_SESSION['user_id'] ?? null, 'WARNING');
            
            http_response_code(429);
            die('Rate limit exceeded. Please try again later.');
        }
        
        return true;
    }
    
    /**
     * Log page access for audit trail
     */
    private static function logPageAccess() {
        if (isset($_SESSION['user_id'])) {
            $page = basename($_SERVER['PHP_SELF']);
            $query_string = $_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '';
            $full_url = $page . $query_string;
            
            // Don't log too frequently for the same page
            $last_logged_page = $_SESSION['last_logged_page'] ?? '';
            $last_log_time = $_SESSION['last_log_time'] ?? 0;
            
            if ($last_logged_page !== $full_url || (time() - $last_log_time) > 300) { // 5 minutes
                self::logSecurityEvent('PAGE_ACCESS', $full_url, $_SESSION['user_id'], 'INFO');
                $_SESSION['last_logged_page'] = $full_url;
                $_SESSION['last_log_time'] = time();
            }
        }
    }
    
    /**
     * Handle unauthorized access
     */
    private static function handleUnauthorizedAccess($required_role) {
        $user_id = $_SESSION['user_id'] ?? null;
        $current_role = $_SESSION['role'] ?? 'guest';
        
        self::logSecurityEvent(
            'UNAUTHORIZED_ACCESS', 
            "Required: $required_role, Current: $current_role, Page: " . basename($_SERVER['PHP_SELF']),
            $user_id,
            'WARNING'
        );
        
        http_response_code(403);
        
        // Redirect based on current role
        $redirect_map = [
            'student' => 'student-dashboard.php',
            'employer' => 'employer-dashboard.php',
            'alumni' => 'alumni-dashboard.php',
            'admin' => 'admin-dashboard.php'
        ];
        
        $redirect_url = $redirect_map[$current_role] ?? 'login.php';
        
        if (headers_sent()) {
            echo "<script>window.location.href='$redirect_url';</script>";
        } else {
            header("Location: $redirect_url");
        }
        exit;
    }
    
    /**
     * Redirect to login page
     */
    private static function redirectToLogin($message = '') {
        self::destroySession();
        
        $login_url = 'login.php';
        if ($message) {
            $login_url .= '?message=' . urlencode($message);
        }
        
        if (headers_sent()) {
            echo "<script>window.location.href='$login_url';</script>";
        } else {
            header("Location: $login_url");
        }
        exit;
    }
    
    /**
     * Safely destroy session
     */
    private static function destroySession() {
        if (isset($_SESSION['user_id'])) {
            // Clean up database session
            $session_id = session_id();
            $stmt = self::$conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            $stmt->bind_param("s", $session_id);
            $stmt->execute();
        }
        
        // Clear session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Log security events
     */
    private static function logSecurityEvent($event_type, $details, $user_id = null, $severity = 'INFO') {
        if (self::$security) {
            self::$security->logSecurityEvent($event_type, $details, $user_id, $severity);
        }
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    /**
     * Get current user info
     */
    public static function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        return [
            'user_id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'display_name' => $_SESSION['display_name'] ?? null
        ];
    }
    
    /**
     * Security headers for AJAX requests
     */
    public static function setAjaxSecurityHeaders() {
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
    }
    
    /**
     * Validate AJAX request
     */
    public static function validateAjaxRequest() {
        // Check if request is AJAX
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            
            self::logSecurityEvent('NON_AJAX_REQUEST', 'Non-AJAX request to AJAX endpoint', $_SESSION['user_id'] ?? null, 'WARNING');
            
            http_response_code(400);
            die(json_encode(['error' => 'Invalid request type']));
        }
        
        // Check referer
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        if (!$referer || parse_url($referer, PHP_URL_HOST) !== $host) {
            self::logSecurityEvent('INVALID_REFERER', "Referer: $referer", $_SESSION['user_id'] ?? null, 'WARNING');
            
            http_response_code(403);
            die(json_encode(['error' => 'Invalid request origin']));
        }
        
        return true;
    }
}

// Auto-initialize if security and database are available
if (isset($security) && isset($conn)) {
    AuthMiddleware::init($security, $conn);
}
?>