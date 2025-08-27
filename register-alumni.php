<?php
session_start();
require_once 'config.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Registration</title>
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
        }

        .registration-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
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

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            color: #7f8c8d;
            font-size: 1rem;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: fadeIn 0.5s ease-out;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
            transition: color 0.3s ease;
        }

        .form-control {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e0e6ed;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #ffffff;
            outline: none;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .form-control:hover {
            border-color: #c5d2ea;
        }

        .select-wrapper {
            position: relative;
        }

        .select-wrapper::after {
            content: '▼';
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            pointer-events: none;
            font-size: 12px;
        }

        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-submit:hover::before {
            left: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #7f8c8d;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: #764ba2;
        }

        .input-icon {
            position: relative;
        }

        .input-icon::before {
            content: attr(data-icon);
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 18px;
            z-index: 1;
        }

        .input-icon .form-control {
            padding-left: 50px;
        }

        @media (max-width: 480px) {
            .registration-container {
                padding: 25px 20px;
                margin: 10px;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .form-control {
                padding: 12px 15px;
            }

            .btn-submit {
                padding: 15px;
                font-size: 1rem;
            }
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        @media (max-width: 480px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="header">
            <h1>Alumni Registration</h1>
            <p>Join our alumni network and connect with fellow graduates</p>
        </div>

        <!-- Success/Error Messages -->
        <div class="alert alert-success" style="display: none;" id="success-alert">
            Registration successful! You can now <a href="login.php">log in</a>.
        </div>
        
        <div class="alert alert-danger" style="display: none;" id="error-alert">
            Email already registered.
        </div>

        <form method="POST" id="registrationForm">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <div class="input-icon" data-icon="👤">
                    <input type="text" name="full_name" class="form-control" required placeholder="Enter your full name">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-icon" data-icon="✉️">
                    <input type="email" name="email" class="form-control" required placeholder="Enter your email address">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Graduation Year</label>
                    <input type="number" name="graduation_year" class="form-control" min="1950" max="2025" required placeholder="e.g. 2020">
                </div>

                <div class="form-group">
                    <label class="form-label">Mentorship</label>
                    <div class="select-wrapper">
                        <select name="mentorship_offered" class="form-control" required>
                            <option value="">Choose...</option>
                            <option value="Yes">Yes, I'd like to mentor</option>
                            <option value="No">No, not at this time</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Current Position</label>
                <div class="input-icon" data-icon="💼">
                    <input type="text" name="current_position" class="form-control" required placeholder="Your current job title/position">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-icon" data-icon="🔒">
                    <input type="password" name="password" class="form-control" required placeholder="Create a strong password">
                </div>
            </div>

            <button type="submit" class="btn-submit">
                Create Account
            </button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Sign in here</a>
        </div>
    </div>

    <script>
        // Form validation and UX enhancements
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const button = document.querySelector('.btn-submit');
            button.innerHTML = 'Creating Account...';
            button.disabled = true;
            
            // Re-enable after 3 seconds (in case of server delay)
            setTimeout(() => {
                button.innerHTML = 'Create Account';
                button.disabled = false;
            }, 3000);
        });

        // Enhanced input interactions
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.parentElement.querySelector('.form-label').style.color = '#667eea';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.parentElement.querySelector('.form-label').style.color = '#2c3e50';
            });
        });

        // Graduation year validation
        document.querySelector('input[name="graduation_year"]').addEventListener('input', function() {
            const currentYear = new Date().getFullYear();
            if (this.value > currentYear) {
                this.setCustomValidity('Graduation year cannot be in the future');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>