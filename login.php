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
    <title>Login - InternHub</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #696cff;
            --primary-light: #7367f0;
            --secondary-color: #8592a3;
            --success-color: #71dd37;
            --warning-color: #ffb400;
            --danger-color: #ff3e1d;
            --info-color: #03c3ec;
            --light-color: #fcfdfd;
            --dark-color: #233446;
            --text-primary: #566a7f;
            --text-secondary: #a8aaae;
            --text-muted: #c7c8cc;
            --border-color: #e4e6e8;
            --card-bg: #fff;
            --hover-bg: #f8f9fa;
            --shadow-sm: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
            --shadow-md: 0 4px 8px -4px rgba(67, 89, 113, 0.1);
            --shadow-lg: 0 6px 14px 0 rgba(67, 89, 113, 0.15);
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --transition: all 0.2s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Main Container */
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .auth-wrapper {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 700px;
        }

        /* Left Side - Illustration */
        .auth-cover {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            overflow: hidden;
        }

        .auth-cover::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite;
        }

        .auth-cover::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .cover-content {
            text-align: center;
            color: white;
            position: relative;
            z-index: 2;
        }

        .cover-logo {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            backdrop-filter: blur(10px);
            font-size: 2rem;
        }

        .cover-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .cover-subtitle {
            font-size: 1.125rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .cover-features {
            display: grid;
            gap: 1rem;
            text-align: left;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
        }

        .feature-icon {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .feature-text {
            font-size: 0.9375rem;
            font-weight: 500;
        }

        /* Right Side - Form */
        .auth-form-section {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        /* Role Selection */
        .role-selection {
            margin-bottom: 2rem;
        }

        .role-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1rem;
            text-align: center;
        }

        .role-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.75rem;
        }

        .role-option {
            position: relative;
        }

        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .role-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            background: var(--hover-bg);
            transition: var(--transition);
            cursor: pointer;
            text-align: center;
            min-height: 80px;
            justify-content: center;
        }

        .role-label:hover {
            border-color: var(--primary-color);
            background: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .role-option input[type="radio"]:checked + .role-label {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(105, 108, 255, 0.3);
        }

        .role-icon {
            font-size: 1.5rem;
        }

        .role-text {
            font-size: 0.875rem;
            font-weight: 600;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9375rem;
        }

        .input-group {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            background: var(--light-color);
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(105, 108, 255, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.125rem;
            pointer-events: none;
        }

        /* Error Alert */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-danger {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        /* Submit Button */
        .btn-primary {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(105, 108, 255, 0.3);
            color: white;
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        /* Form Footer */
        .form-footer {
            text-align: center;
            margin-top: 2rem;
        }

        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .form-footer a:hover {
            color: var(--primary-light);
            text-decoration: underline;
        }

        .divider {
            position: relative;
            text-align: center;
            margin: 1.5rem 0;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-color);
            z-index: 1;
        }

        .divider span {
            background: var(--card-bg);
            padding: 0 1rem;
            position: relative;
            z-index: 2;
        }

        /* Footer Links */
        .footer-links {
            text-align: center;
            padding: 1.5rem 0 0;
            border-top: 1px solid var(--border-color);
            margin-top: 2rem;
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            margin: 0 0.75rem;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .auth-wrapper {
                grid-template-columns: 1fr;
                max-width: 500px;
            }

            .auth-cover {
                display: none;
            }

            .auth-form-section {
                padding: 2rem;
            }
        }

        @media (max-width: 576px) {
            .auth-container {
                padding: 1rem;
            }

            .auth-form-section {
                padding: 1.5rem;
            }

            .role-options {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .role-label {
                flex-direction: row;
                justify-content: flex-start;
                min-height: auto;
                padding: 0.75rem;
            }

            .cover-title {
                font-size: 2rem;
            }

            .form-header h1 {
                font-size: 1.75rem;
            }
        }

        /* Animations */
        .auth-wrapper {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .role-option {
            animation: fadeInUp 0.6s ease-out forwards;
            opacity: 0;
        }

        .role-option:nth-child(1) { animation-delay: 0.1s; }
        .role-option:nth-child(2) { animation-delay: 0.2s; }
        .role-option:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="auth-wrapper">
            <!-- Left Side - Cover/Illustration -->
            <div class="auth-cover">
                <div class="cover-content">
                    <div class="cover-logo">
                        <i class="bi bi-mortarboard"></i>
                    </div>
                    <h1 class="cover-title">Welcome to InternHub</h1>
                    <p class="cover-subtitle">Connect with opportunities, build your career, and shape your future in the internship ecosystem.</p>
                    
                    <div class="cover-features">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="bi bi-briefcase"></i>
                            </div>
                            <div class="feature-text">Discover amazing internship opportunities</div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <div class="feature-text">Connect with industry professionals</div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="bi bi-graph-up"></i>
                            </div>
                            <div class="feature-text">Track your career progress</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="auth-form-section">
                <div class="form-header">
                    <h1>Sign In</h1>
                    <p>Welcome back! Please sign in to your account</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <!-- Role Selection -->
                    <div class="role-selection">
                        <div class="role-title">Select Your Role</div>
                        <div class="role-options">
                            <div class="role-option">
                                <input type="radio" name="role" value="student" id="student" required 
                                       <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'checked' : ''; ?>>
                                <label for="student" class="role-label">
                                    <div class="role-icon">🎓</div>
                                    <div class="role-text">Student</div>
                                </label>
                            </div>
                            
                            <div class="role-option">
                                <input type="radio" name="role" value="employer" id="employer" required
                                       <?php echo (isset($_POST['role']) && $_POST['role'] === 'employer') ? 'checked' : ''; ?>>
                                <label for="employer" class="role-label">
                                    <div class="role-icon">💼</div>
                                    <div class="role-text">Employer</div>
                                </label>
                            </div>

                            <div class="role-option">
                                <input type="radio" name="role" value="alumni" id="alumni" required
                                       <?php echo (isset($_POST['role']) && $_POST['role'] === 'alumni') ? 'checked' : ''; ?>>
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
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="Enter your email address">
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" id="password" name="password" class="form-control" required
                                   placeholder="Enter your password">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Sign In
                    </button>
                </form>

                <!-- Form Footer -->
                <div class="form-footer">
                    <a href="#">Forgot your password?</a>
                </div>

                <div class="footer-links">
                    <a href="register.php">Create Account</a>
                    <span style="color: var(--border-color);">|</span>
                    <a href="#">Need Help?</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const roleInputs = document.querySelectorAll('input[name="role"]');

            // Auto-focus on email field
            emailInput.focus();

            // Form submission with loading state
            loginForm.addEventListener('submit', function() {
                const selectedRole = document.querySelector('input[name="role"]:checked');
                
                if (selectedRole) {
                    loginBtn.classList.add('btn-loading');
                    loginBtn.innerHTML = `<i class="bi bi-arrow-clockwise me-2"></i>Signing in as ${selectedRole.value.charAt(0).toUpperCase() + selectedRole.value.slice(1)}...`;
                }
            });

            // Enhanced role selection
            roleInputs.forEach(role => {
                role.addEventListener('change', function() {
                    const roleName = this.value.charAt(0).toUpperCase() + this.value.slice(1);
                    loginBtn.innerHTML = `<i class="bi bi-box-arrow-in-right me-2"></i>Sign In as ${roleName}`;
                    
                    // Add selection animation
                    const label = this.nextElementSibling;
                    label.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        label.style.transform = '';
                    }, 200);
                });
            });
        });
    </script>
</body>
</html>