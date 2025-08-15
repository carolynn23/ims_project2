<?php
session_start();
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? ''; // 'student', 'employer', or 'alumni'

    if ($email === '' || $password === '' || $role === '') {
        $error = "All fields are required.";
    } else {
        // Find active user by email+role
        $stmt = $conn->prepare("SELECT user_id, email, password_hash, role, status FROM users WHERE email = ? AND role = ? AND status = 'active' LIMIT 1");
        $stmt->bind_param("ss", $email, $role);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Base session
            $_SESSION['user_id'] = (int)$user['user_id'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];

            $displayName = '';   // will be saved to session for navbar
            $redirect    = '';   // where to send the user
            // also save role-specific PKs
            if ($role === 'student') {
                $q = $conn->prepare("SELECT student_id, full_name FROM students WHERE user_id = ? LIMIT 1");
                $q->bind_param("i", $_SESSION['user_id']);
                $q->execute();
                if ($row = $q->get_result()->fetch_assoc()) {
                    $_SESSION['student_id']   = (int)$row['student_id'];
                    $displayName              = $row['full_name'] ?: 'Student';
                }
                $q->close();
                $redirect = 'student-dashboard.php';

            } elseif ($role === 'employer') {
                $q = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ? LIMIT 1");
                $q->bind_param("i", $_SESSION['user_id']);
                $q->execute();
                if ($row = $q->get_result()->fetch_assoc()) {
                    $_SESSION['employer_id']  = (int)$row['employer_id'];
                    $displayName              = $row['company_name'] ?: 'Employer';
                }
                $q->close();
                $redirect = 'employer-dashboard.php';

            } elseif ($role === 'alumni') {
                $q = $conn->prepare("SELECT alumni_id, full_name FROM alumni WHERE user_id = ? LIMIT 1");
                $q->bind_param("i", $_SESSION['user_id']);
                $q->execute();
                if ($row = $q->get_result()->fetch_assoc()) {
                    $_SESSION['alumni_id']    = (int)$row['alumni_id'];
                    $displayName              = $row['full_name'] ?: 'Alumni';
                }
                $q->close();
                $redirect = 'alumni-dashboard.php';
            }

            // Store display name once; navbar will just echo this
            $_SESSION['display_name'] = $displayName !== '' ? $displayName : 'User';

            header("Location: " . $redirect);
            exit;
        } else {
            $error = "Invalid email, role, or password.";
        }
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EduPortal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .floating-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }

        .floating-circle:nth-child(1) {
            top: 10%;
            left: 10%;
            width: 60px;
            height: 60px;
            animation-delay: 0s;
        }

        .floating-circle:nth-child(2) {
            top: 20%;
            right: 10%;
            width: 40px;
            height: 40px;
            animation-delay: 2s;
        }

        .floating-circle:nth-child(3) {
            bottom: 20%;
            left: 20%;
            width: 80px;
            height: 80px;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.3;
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
                opacity: 0.6;
            }
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            position: relative;
            z-index: 1;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 40px 30px;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="25" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="25" cy="75" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        }

        .login-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
        }

        .login-icon svg {
            width: 30px;
            height: 30px;
            fill: white;
        }

        .login-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }

        .login-form {
            padding: 40px 30px;
        }

        .role-selection {
            margin-bottom: 30px;
        }

        .role-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            text-align: center;
        }

        .role-options {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .role-option {
            position: relative;
        }

        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .role-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
            font-weight: 500;
            font-size: 14px;
            min-width: 100px;
            justify-content: center;
        }

        .role-option input[type="radio"]:checked + .role-label {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .role-option.student .role-label {
            --hover-color: #4CAF50;
        }

        .role-option.employer .role-label {
            --hover-color: #2196F3;
        }

        .role-option.alumni .role-label {
            --hover-color: #FF9800;
        }

        .role-option:hover .role-label:not(:has(input:checked)) {
            border-color: var(--hover-color);
            background: var(--hover-color);
            color: white;
            transform: translateY(-1px);
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 15px 45px 15px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            opacity: 0.5;
        }

        .input-icon svg {
            width: 100%;
            height: 100%;
            fill: #666;
        }

        .error-message {
            background: #fee;
            color: #c53030;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c53030;
            text-align: center;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .login-button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .login-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .login-button:hover::before {
            left: 100%;
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }

        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .footer-links {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            border-top: 1px solid #e1e5e9;
        }

        .footer-links a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
            margin: 0 10px;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: #667eea;
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
            color: #666;
            font-size: 14px;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e1e5e9;
            z-index: 1;
        }

        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            z-index: 2;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
            }
            
            .login-form {
                padding: 30px 20px;
            }
            
            .login-header {
                padding: 30px 20px;
            }

            .role-options {
                flex-direction: column;
                gap: 10px;
            }

            .role-label {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div class="floating-elements">
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2L2 7V10C2 17 6.84 23.74 12 23.74S22 17 22 10V7L12 2Z"/>
                </svg>
            </div>
            <h1>Welcome Back</h1>
            <p>Sign in to your EduPortal account</p>
        </div>

        <div class="login-form">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="role-selection">
                    <div class="role-title">Select Your Role</div>
                    <div class="role-options">
                        <div class="role-option student">
                            <input type="radio" name="role" value="student" id="student" required 
                                   <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'checked' : ''; ?>>
                            <label for="student" class="role-label">
                                🎓 Student
                            </label>
                        </div>
                        
                        <div class="role-option employer">
                            <input type="radio" name="role" value="employer" id="employer" required
                                   <?php echo (isset($_POST['role']) && $_POST['role'] === 'employer') ? 'checked' : ''; ?>>
                            <label for="employer" class="role-label">
                                💼 Employer
                            </label>
                        </div>

                        <div class="role-option alumni">
                            <input type="radio" name="role" value="alumni" id="alumni" required
                                   <?php echo (isset($_POST['role']) && $_POST['role'] === 'alumni') ? 'checked' : ''; ?>>
                            <label for="alumni" class="role-label">
                                🌟 Alumni
                            </label>
                        </div>
                    </div>
                </div>

                <div class="divider">
                    <span>Enter your credentials</span>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           placeholder="Enter your email">
                    <div class="input-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M20,8L12,13L4,8V6L12,11L20,6M20,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V6C22,4.89 21.1,4 20,4Z"/>
                        </svg>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required
                           placeholder="Enter your password">
                    <div class="input-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M12,17A2,2 0 0,0 14,15C14,13.89 13.1,13 12,13A2,2 0 0,0 10,15A2,2 0 0,0 12,17M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V10C4,8.89 4.9,8 6,8H7V6A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,3A3,3 0 0,0 9,6V8H15V6A3,3 0 0,0 12,3Z"/>
                        </svg>
                    </div>
                </div>

                <button type="submit" class="login-button" id="loginBtn">
                    Sign In
                </button>
            </form>

            <div class="forgot-password">
                <a href="forgot-password.php">Forgot your password?</a>
            </div>
        </div>

        <div class="footer-links">
            <a href="register.php">Create Account</a>
            <span style="color: #ccc;">|</span>
            <a href="contact.php">Need Help?</a>
        </div>
    </div>

    <script>
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const selectedRole = document.querySelector('input[name="role"]:checked');
            
            if (selectedRole) {
                btn.classList.add('loading');
                btn.textContent = `Signing in as ${selectedRole.value.charAt(0).toUpperCase() + selectedRole.value.slice(1)}...`;
            }
        });

        // Auto-focus on email field
        document.getElementById('email').focus();

        // Interactive feedback for form inputs
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Role selection enhancement
        const roleOptions = document.querySelectorAll('input[name="role"]');
        roleOptions.forEach(option => {
            option.addEventListener('change', function() {
                // Update button text based on selected role
                const btn = document.getElementById('loginBtn');
                const role = this.value;
                btn.textContent = `Sign In as ${role.charAt(0).toUpperCase() + role.slice(1)}`;
                
                // Add selection animation
                this.nextElementSibling.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    this.nextElementSibling.style.transform = 'translateY(-2px)';
                }, 200);
            });
        });

        // Parallax effect for floating elements
        document.addEventListener('mousemove', function(e) {
            const circles = document.querySelectorAll('.floating-circle');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            circles.forEach((circle, index) => {
                const speed = (index + 1) * 0.3;
                const xMove = (x - 0.5) * speed * 15;
                const yMove = (y - 0.5) * speed * 15;
                
                circle.style.transform = `translate(${xMove}px, ${yMove}px)`;
            });
        });

        // Enhanced error handling
        const errorMessage = document.querySelector('.error-message');
        if (errorMessage) {
            setTimeout(() => {
                errorMessage.style.opacity = '0.8';
            }, 3000);
        }
    </script>
</body>
</html>