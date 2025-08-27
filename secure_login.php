<?php
/**
 * Enhanced Secure Login with comprehensive security features
 * Replace your existing login.php with this enhanced version
 */

// Security check
define('SECURE_ACCESS', true);

session_start();
require_once 'secure_config.php';

$error = '';
$warning = '';
$success = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    $redirect_map = [
        'student' => 'student-dashboard.php',
        'employer' => 'employer-dashboard.php',
        'alumni' => 'alumni-dashboard.php',
        'admin' => 'admin-dashboard.php'
    ];
    
    $redirect_url = $redirect_map[$_SESSION['role']] ?? 'dashboard.php';
    secure_redirect($redirect_url);
}

// Handle logout message
if (isset($_GET['logout'])) {
    $success = 'You have been successfully logged out.';
}

// Handle session timeout message
if (isset($_GET['timeout'])) {
    $warning = 'Your session has expired. Please log in again.';
}

// Handle account locked message
if (isset($_GET['locked'])) {
    $error = 'Your account has been temporarily locked due to multiple failed login attempts. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Check rate limiting
        try {
            check_rate_limit('login');
        } catch (Exception $e) {
            $error = 'Too many login attempts. Please try again later.';
        }
        
        if (empty($error)) {
            $email = sanitize($_POST['email'] ?? '', 'email');
            $password = $_POST['password'] ?? '';
            $role = sanitize($_POST['role'] ?? '', 'alphanumeric');
            
            // Validate input
            $validation_errors = [];
            
            if (!$email) {
                $validation_errors[] = 'Please enter a valid email address.';
            }
            
            if (empty($password)) {
                $validation_errors[] = 'Please enter your password.';
            }
            
            if (!in_array($role, ['student', 'employer', 'alumni', 'admin'])) {
                $validation_errors[] = 'Please select a valid role.';
            }
            
            if (!empty($validation_errors)) {
                $error = implode(' ', $validation_errors);
            } else {
                // Attempt login
                try {
                    $stmt = $conn->prepare("
                        SELECT user_id, email, password_hash, role, status, 
                               account_locked, locked_until, failed_login_attempts,
                               email_verified, two_factor_enabled
                        FROM users 
                        WHERE email = ? AND role = ? AND status = 'active'
                    ");
                    $stmt->bind_param("ss", $email, $role);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    
                    if ($user) {
                        // Check if account is locked
                        if ($user['account_locked'] && 
                            ($user['locked_until'] === null || strtotime($user['locked_until']) > time())) {
                            
                            log_security_event(
                                'LOGIN_ATTEMPT_LOCKED_ACCOUNT',
                                "Email: $email, Role: $role",
                                'WARNING'
                            );
                            
                            $error = 'Your account is temporarily locked. Please try again later.';
                        } 
                        // Check if email is verified
                        elseif (!$user['email_verified']) {
                            log_security_event(
                                'LOGIN_ATTEMPT_UNVERIFIED_EMAIL',
                                "Email: $email, Role: $role",
                                'INFO'
                            );
                            
                            $error = 'Please verify your email address before logging in.';
                        }
                        // Verify password
                        elseif (password_verify($password, $user['password_hash'])) {
                            // Successful login
                            
                            // Reset failed attempts
                            if ($user['failed_login_attempts'] > 0) {
                                $reset_stmt = $conn->prepare("
                                    UPDATE users 
                                    SET failed_login_attempts = 0, account_locked = FALSE, locked_until = NULL 
                                    WHERE user_id = ?
                                ");
                                $reset_stmt->bind_param("i", $user['user_id']);
                                $reset_stmt->execute();
                            }
                            
                            // Create session
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = (int)$user['user_id'];
                            $_SESSION['email'] = $user['email'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['last_activity'] = time();
                            $_SESSION['login_time'] = time();
                            $_SESSION['csrf_token'] = $security->generateCSRFToken();
                            
                            // Store session in database
                            $session_id = session_id();
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
                            $expires_at = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
                            
                            $session_stmt = $conn->prepare("
                                INSERT INTO user_sessions 
                                (session_id, user_id, ip_address, user_agent, expires_at) 
                                VALUES (?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                last_activity = NOW(), expires_at = ?
                            ");
                            $session_stmt->bind_param("sissss", $session_id, $user['user_id'], $ip_address, $user_agent, $expires_at, $expires_at);
                            $session_stmt->execute();
                            
                            // Get role-specific information
                            $displayName = '';
                            $redirect = '';
                            
                            if ($role === 'student') {
                                $profile_stmt = $conn->prepare("SELECT student_id, full_name FROM students WHERE user_id = ?");
                                $profile_stmt->bind_param("i", $user['user_id']);
                                $profile_stmt->execute();
                                $profile = $profile_stmt->get_result()->fetch_assoc();
                                
                                if ($profile) {
                                    $_SESSION['student_id'] = (int)$profile['student_id'];
                                    $displayName = $profile['full_name'] ?: 'Student';
                                }
                                $redirect = 'student-dashboard.php';
                                
                            } elseif ($role === 'employer') {
                                $profile_stmt = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ?");
                                $profile_stmt->bind_param("i", $user['user_id']);
                                $profile_stmt->execute();
                                $profile = $profile_stmt->get_result()->fetch_assoc();
                                
                                if ($profile) {
                                    $_SESSION['employer_id'] = (int)$profile['employer_id'];
                                    $displayName = $profile['company_name'] ?: 'Employer';
                                }
                                $redirect = 'employer-dashboard.php';
                                
                            } elseif ($role === 'alumni') {
                                $profile_stmt = $conn->prepare("SELECT alumni_id, full_name FROM alumni WHERE user_id = ?");
                                $profile_stmt->bind_param("i", $user['user_id']);
                                $profile_stmt->execute();
                                $profile = $profile_stmt->get_result()->fetch_assoc();
                                
                                if ($profile) {
                                    $_SESSION['alumni_id'] = (int)$profile['alumni_id'];
                                    $displayName = $profile['full_name'] ?: 'Alumni';
                                }
                                $redirect = 'alumni-dashboard.php';
                                
                            } elseif ($role === 'admin') {
                                $displayName = 'Administrator';
                                $redirect = 'admin-dashboard.php';
                            }
                            
                            $_SESSION['display_name'] = $displayName ?: 'User';
                            
                            // Log successful login
                            log_security_event(
                                'LOGIN_SUCCESS',
                                "Email: $email, Role: $role",
                                'INFO'
                            );
                            
                            // Record successful login attempt
                            $login_stmt = $conn->prepare("
                                INSERT INTO login_attempts (email, ip_address, user_agent, success) 
                                VALUES (?, ?, ?, TRUE)
                            ");
                            $login_stmt->bind_param("sss", $email, $ip_address, $user_agent);
                            $login_stmt->execute();
                            
                            // Check for 2FA (future implementation)
                            if ($user['two_factor_enabled']) {
                                $_SESSION['pending_2fa'] = true;
                                secure_redirect('two-factor-verify.php');
                            } else {
                                secure_redirect($redirect);
                            }
                            
                        } else {
                            // Failed login - increment failed attempts
                            $failed_attempts = $user['failed_login_attempts'] + 1;
                            $account_locked = $failed_attempts >= MAX_LOGIN_ATTEMPTS;
                            $locked_until = $account_locked ? date('Y-m-d H:i:s', time() + ACCOUNT_LOCKOUT_TIME) : null;
                            
                            $fail_stmt = $conn->prepare("
                                UPDATE users 
                                SET failed_login_attempts = ?, 
                                    account_locked = ?, 
                                    locked_until = ?
                                WHERE user_id = ?
                            ");
                            $fail_stmt->bind_param("iisi", $failed_attempts, $account_locked, $locked_until, $user['user_id']);
                            $fail_stmt->execute();
                            
                            // Record failed attempt for rate limiting
                            $security->recordFailedAttempt($_SERVER['REMOTE_ADDR'] . '_' . $email, 'login');
                            
                            // Log failed login
                            log_security_event(
                                'LOGIN_FAILED',
                                "Email: $email, Role: $role, Attempts: $failed_attempts",
                                'WARNING'
                            );
                            
                            // Record failed login attempt
                            $ip_address = $_SERVER['REMOTE_ADDR'];
                            $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
                            $fail_login_stmt = $conn->prepare("
                                INSERT INTO login_attempts (email, ip_address, user_agent, success) 
                                VALUES (?, ?, ?, FALSE)
                            ");
                            $fail_login_stmt->bind_param("sss", $email, $ip_address, $user_agent);
                            $fail_login_stmt->execute();
                            
                            if ($account_locked) {
                                $error = 'Account locked due to multiple failed login attempts. Please try again in 15 minutes.';
                            } else {
                                $remaining_attempts = MAX_LOGIN_ATTEMPTS - $failed_attempts;
                                $error = "Invalid credentials. You have $remaining_attempts attempt(s) remaining before your account is locked.";
                            }
                        }
                    } else {
                        // User not found - still record attempt for rate limiting
                        $security->recordFailedAttempt($_SERVER['REMOTE_ADDR'] . '_' . $email, 'login');
                        
                        log_security_event(
                            'LOGIN_USER_NOT_FOUND',
                            "Email: $email, Role: $role",
                            'WARNING'
                        );
                        
                        // Generic error message to prevent user enumeration
                        $error = 'Invalid credentials. Please check your email, password, and selected role.';
                    }
                    
                } catch (Exception $e) {
                    log_security_event(
                        'LOGIN_ERROR',
                        "Database error during login: " . $e->getMessage(),
                        'HIGH'
                    );
                    
                    $error = 'An error occurred during login. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login - <?= APP_NAME ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Include your existing CSS styles here -->
    <style>
        /* Include all your existing login.php styles here */
        :root {
            --primary-color: #696cff;
            --primary-light: #7367f0;
            --danger-color: #ff3e1d;
            --warning-color: #ffb400;
            --success-color: #71dd37;
            --text-primary: #566a7f;
            --border-color: #e4e6e8;
            --card-bg: #fff;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        
        .security-notice {
            background: rgba(255, 180, 0, 0.1);
            border: 1px solid var(--warning-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
        }
        
        .security-notice i {
            color: var(--warning-color);
            font-size: 1.25rem;
        }
        
        .rate-limit-warning {
            background: rgba(255, 62, 29, 0.1);
            border-color: var(--danger-color);
        }
        
        .rate-limit-warning i {
            color: var(--danger-color);
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
        }
    </style>
</head>

<body>
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Logging in...</span>
            </div>
            <div class="mt-2">Verifying credentials...</div>
        </div>
    </div>
    
    <div class="auth-container">
        <div class="auth-wrapper">
            <!-- Include your existing auth-cover section -->
            <div class="auth-cover">
                <div class="cover-content">
                    <div class="cover-logo">
                        <i class="bi bi-mortarboard"></i>
                    </div>
                    <h1 class="cover-title">Welcome to <?= APP_NAME ?></h1>
                    <p class="cover-subtitle">Your secure gateway to internship opportunities</p>
                    
                    <!-- Security features notice -->
                    <div class="security-notice">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <strong>Enhanced Security:</strong> This platform uses advanced encryption, 
                            CSRF protection, and rate limiting to keep your data safe.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="auth-form-section">
                <div class="form-header">
                    <h1>Secure Sign In</h1>
                    <p>Please authenticate to access your account</p>
                </div>

                <!-- Display messages -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($warning)): ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= htmlspecialchars($warning) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="secureLoginForm">
                    <!-- CSRF Token -->
                    <?= csrf_field() ?>
                    
                    <!-- Role Selection -->
                    <div class="role-selection">
                        <div class="role-title">Select Your Role</div>
                        <div class="role-options">
                            <div class="role-option">
                                <input type="radio" name="role" value="student" id="student" required 
                                       <?= (isset($_POST['role']) && $_POST['role'] === 'student') ? 'checked' : '' ?>>
                                <label for="student" class="role-label">
                                    <div class="role-icon">🎓</div>
                                    <div class="role-text">Student</div>
                                </label>
                            </div>
                            
                            <div class="role-option">
                                <input type="radio" name="role" value="employer" id="employer" required
                                       <?= (isset($_POST['role']) && $_POST['role'] === 'employer') ? 'checked' : '' ?>>
                                <label for="employer" class="role-label">
                                    <div class="role-icon">💼</div>
                                    <div class="role-text">Employer</div>
                                </label>
                            </div>

                            <div class="role-option">
                                <input type="radio" name="role" value="alumni" id="alumni" required
                                       <?= (isset($_POST['role']) && $_POST['role'] === 'alumni') ? 'checked' : '' ?>>
                                <label for="alumni" class="role-label">
                                    <div class="role-icon">🌟</div>
                                    <div class="role-text">Alumni</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="divider">
                        <span>Enter your credentials</span>
                    </div>

                    <!-- Email Field -->
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" id="email" name="email" class="form-control" required 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                   placeholder="Enter your email address"
                                   autocomplete="email">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" id="password" name="password" class="form-control" required
                                   placeholder="Enter your password"
                                   autocomplete="current-password">
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Security Info -->
                    <div class="security-info">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Your session will expire after <?= SESSION_TIMEOUT / 60 ?> minutes of inactivity.
                        </small>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Secure Sign In
                    </button>
                </form>

                <!-- Form Footer -->
                <div class="form-footer">
                    <a href="forgot-password.php">Forgot your password?</a>
                </div>

                <div class="footer-links">
                    <a href="register.php">Create Account</a>
                    <span style="color: var(--border-color);">|</span>
                    <a href="contact.php">Need Help?</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('secureLoginForm');
            const loginBtn = document.getElementById('loginBtn');
            const loadingOverlay = document.getElementById('loadingOverlay');
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');
            
            // Password toggle
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                
                const icon = this.querySelector('i');
                icon.className = type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
            
            // Form submission with loading state
            loginForm.addEventListener('submit', function(e) {
                const selectedRole = document.querySelector('input[name="role"]:checked');
                
                if (selectedRole) {
                    // Show loading overlay
                    loadingOverlay.style.display = 'flex';
                    
                    // Update button state
                    loginBtn.disabled = true;
                    loginBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>Signing in...`;
                    
                    // Set timeout to re-enable form if something goes wrong
                    setTimeout(() => {
                        loadingOverlay.style.display = 'none';
                        loginBtn.disabled = false;
                        loginBtn.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i>Secure Sign In';
                    }, 10000); // 10 seconds
                }
            });
            
            // Client-side rate limiting warning
            let attemptCount = parseInt(localStorage.getItem('loginAttempts') || '0');
            let lastAttempt = parseInt(localStorage.getItem('lastLoginAttempt') || '0');
            
            if (attemptCount >= 3 && (Date.now() - lastAttempt) < 300000) { // 5 minutes
                const warningDiv = document.createElement('div');
                warningDiv.className = 'security-notice rate-limit-warning';
                warningDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>
                        <strong>Security Notice:</strong> Multiple failed login attempts detected. 
                        Please ensure you are using the correct credentials.
                    </div>
                `;
                
                const formSection = document.querySelector('.auth-form-section');
                const formHeader = formSection.querySelector('.form-header');
                formHeader.parentNode.insertBefore(warningDiv, formHeader.nextSibling);
            }
            
            // Track failed attempts
            <?php if (!empty($error) && strpos($error, 'Invalid') !== false): ?>
            attemptCount++;
            localStorage.setItem('loginAttempts', attemptCount.toString());
            localStorage.setItem('lastLoginAttempt', Date.now().toString());
            <?php endif; ?>
            
            // Reset on successful elements
            <?php if (empty($error) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            localStorage.removeItem('loginAttempts');
            localStorage.removeItem('lastLoginAttempt');
            <?php endif; ?>
        });
    </script>
</body>
</html>