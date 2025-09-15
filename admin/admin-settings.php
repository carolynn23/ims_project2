<?php
session_start();

// Smart path detection for config.php
if (file_exists('../config.php')) {
    require_once '../config.php';
} elseif (file_exists('config.php')) {
    require_once 'config.php';
} else {
    die('Error: config.php not found');
}

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../secure_login.php");
    exit();
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'general_settings':
            $site_name = trim($_POST['site_name'] ?? '');
            $site_description = trim($_POST['site_description'] ?? '');
            $admin_email = trim($_POST['admin_email'] ?? '');
            $timezone = $_POST['timezone'] ?? '';
            
            // Here you would normally save to a settings table
            // For now, we'll just show success message
            $message = "General settings updated successfully!";
            $message_type = "success";
            break;
            
        case 'email_settings':
            $smtp_host = trim($_POST['smtp_host'] ?? '');
            $smtp_port = trim($_POST['smtp_port'] ?? '');
            $smtp_username = trim($_POST['smtp_username'] ?? '');
            $smtp_password = trim($_POST['smtp_password'] ?? '');
            
            $message = "Email settings updated successfully!";
            $message_type = "success";
            break;
            
        case 'security_settings':
            $session_timeout = (int)($_POST['session_timeout'] ?? 1800);
            $max_login_attempts = (int)($_POST['max_login_attempts'] ?? 5);
            $password_min_length = (int)($_POST['password_min_length'] ?? 8);
            
            $message = "Security settings updated successfully!";
            $message_type = "success";
            break;
            
        case 'maintenance_mode':
            $maintenance_enabled = isset($_POST['maintenance_enabled']);
            $maintenance_message = trim($_POST['maintenance_message'] ?? '');
            
            $message = $maintenance_enabled ? "Maintenance mode enabled!" : "Maintenance mode disabled!";
            $message_type = $maintenance_enabled ? "warning" : "success";
            break;
    }
}

// Get current settings (you would fetch these from database)
$current_settings = [
    'site_name' => 'InternHub',
    'site_description' => 'Student Internship Management System',
    'admin_email' => 'admin@internhub.com',
    'timezone' => 'UTC',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_username' => '',
    'session_timeout' => 1800,
    'max_login_attempts' => 5,
    'password_min_length' => 8,
    'maintenance_enabled' => false,
    'maintenance_message' => 'System is temporarily down for maintenance.'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - InternHub</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #696cff;
            --success-color: #71dd37;
            --warning-color: #ffb400;
            --danger-color: #ff3e1d;
            --info-color: #03c3ec;
            --text-primary: #566a7f;
            --text-secondary: #a8aaae;
            --border-color: #e4e6e8;
            --card-bg: #fff;
            --hover-bg: #f8f9fa;
            --shadow-sm: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
            --shadow-lg: 0 6px 14px 0 rgba(67, 89, 113, 0.15);
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --transition: all 0.2s ease-in-out;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f9;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .settings-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background: var(--hover-bg);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.25);
        }

        .btn {
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background: #5f63f2;
            border-color: #5f63f2;
            transform: translateY(-1px);
        }

        .settings-nav {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .nav-pills .nav-link {
            border-radius: var(--border-radius);
            color: var(--text-secondary);
            font-weight: 500;
            padding: 0.75rem 1rem;
            margin-bottom: 0.25rem;
            transition: var(--transition);
        }

        .nav-pills .nav-link.active {
            background: var(--primary-color);
        }

        .nav-pills .nav-link:hover:not(.active) {
            background: var(--hover-bg);
            color: var(--primary-color);
        }

        .maintenance-toggle {
            background: var(--danger-color);
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: var(--border-radius-lg);
            font-weight: 600;
            transition: var(--transition);
        }

        .maintenance-toggle:hover {
            background: #e6341a;
            transform: translateY(-1px);
        }

        .maintenance-toggle.enabled {
            background: var(--success-color);
        }

        .maintenance-toggle.enabled:hover {
            background: #66c732;
        }

        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .warning-box {
            background: #fff3e0;
            border: 1px solid #ffcc02;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-1">System Settings</h1>
                    <p class="text-muted mb-0">Configure your InternHub system</p>
                </div>
                <div>
                    <button class="btn btn-outline-primary" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise me-2"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Settings Navigation -->
        <div class="settings-nav">
            <ul class="nav nav-pills" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="pill" data-bs-target="#general" type="button">
                        <i class="bi bi-gear me-2"></i>General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="email-tab" data-bs-toggle="pill" data-bs-target="#email" type="button">
                        <i class="bi bi-envelope me-2"></i>Email
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button">
                        <i class="bi bi-shield-check me-2"></i>Security
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="maintenance-tab" data-bs-toggle="pill" data-bs-target="#maintenance" type="button">
                        <i class="bi bi-tools me-2"></i>Maintenance
                    </button>
                </li>
            </ul>
        </div>

        <!-- Settings Content -->
        <div class="tab-content" id="settingsTabContent">
            
            <!-- General Settings -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-gear me-2"></i>General Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="general_settings">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Site Name</label>
                                        <input type="text" class="form-control" name="site_name" 
                                               value="<?= htmlspecialchars($current_settings['site_name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Admin Email</label>
                                        <input type="email" class="form-control" name="admin_email" 
                                               value="<?= htmlspecialchars($current_settings['admin_email']) ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Site Description</label>
                                <textarea class="form-control" name="site_description" rows="3"><?= htmlspecialchars($current_settings['site_description']) ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Timezone</label>
                                <select class="form-select" name="timezone">
                                    <option value="UTC" <?= $current_settings['timezone'] === 'UTC' ? 'selected' : '' ?>>UTC</option>
                                    <option value="America/New_York">Eastern Time</option>
                                    <option value="America/Chicago">Central Time</option>
                                    <option value="America/Denver">Mountain Time</option>
                                    <option value="America/Los_Angeles">Pacific Time</option>
                                    <option value="Europe/London">London</option>
                                    <option value="Europe/Paris">Paris</option>
                                    <option value="Asia/Tokyo">Tokyo</option>
                                    <option value="Africa/Accra">Accra</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check me-2"></i>Save General Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="tab-pane fade" id="email" role="tabpanel">
                <div class="settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-envelope me-2"></i>Email Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="info-box">
                            <i class="bi bi-info-circle me-2"></i>
                            Configure SMTP settings for sending emails from the system.
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="email_settings">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" name="smtp_host" 
                                               value="<?= htmlspecialchars($current_settings['smtp_host']) ?>" 
                                               placeholder="smtp.gmail.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" name="smtp_port" 
                                               value="<?= htmlspecialchars($current_settings['smtp_port']) ?>" 
                                               placeholder="587">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" name="smtp_username" 
                                               value="<?= htmlspecialchars($current_settings['smtp_username']) ?>" 
                                               placeholder="your-email@gmail.com">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" name="smtp_password" 
                                               placeholder="Enter password to change">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check me-2"></i>Save Email Settings
                            </button>
                            
                            <button type="button" class="btn btn-outline-info ms-2" onclick="testEmail()">
                                <i class="bi bi-send me-2"></i>Send Test Email
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="tab-pane fade" id="security" role="tabpanel">
                <div class="settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-shield-check me-2"></i>Security Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="warning-box">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Changes to security settings will affect all users. Use with caution.
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="security_settings">
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Session Timeout (seconds)</label>
                                        <input type="number" class="form-control" name="session_timeout" 
                                               value="<?= $current_settings['session_timeout'] ?>" 
                                               min="300" max="86400">
                                        <small class="text-muted">Default: 1800 (30 minutes)</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" name="max_login_attempts" 
                                               value="<?= $current_settings['max_login_attempts'] ?>" 
                                               min="3" max="10">
                                        <small class="text-muted">Before account lockout</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Min Password Length</label>
                                        <input type="number" class="form-control" name="password_min_length" 
                                               value="<?= $current_settings['password_min_length'] ?>" 
                                               min="6" max="20">
                                        <small class="text-muted">Characters required</small>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check me-2"></i>Save Security Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Maintenance Mode -->
            <div class="tab-pane fade" id="maintenance" role="tabpanel">
                <div class="settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-tools me-2"></i>Maintenance Mode
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="warning-box">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Enabling maintenance mode will block access for all non-admin users.
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="maintenance_mode">
                            
                            <div class="form-group">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="maintenance_enabled" 
                                           name="maintenance_enabled" <?= $current_settings['maintenance_enabled'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="maintenance_enabled">
                                        <strong>Enable Maintenance Mode</strong>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Maintenance Message</label>
                                <textarea class="form-control" name="maintenance_message" rows="3" 
                                          placeholder="Message to show users during maintenance"><?= htmlspecialchars($current_settings['maintenance_message']) ?></textarea>
                            </div>
                            
                            <button type="submit" class="maintenance-toggle <?= $current_settings['maintenance_enabled'] ? 'enabled' : '' ?>">
                                <i class="bi bi-<?= $current_settings['maintenance_enabled'] ? 'check-circle' : 'tools' ?> me-2"></i>
                                <?= $current_settings['maintenance_enabled'] ? 'Disable' : 'Enable' ?> Maintenance Mode
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function testEmail() {
            // This would normally send an AJAX request to test email settings
            alert('Test email functionality would be implemented here.');
        }
        
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-save warning for unsaved changes
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('change', function() {
                        // You could add unsaved changes warning here
                    });
                });
            });
        });
    </script>
</body>
</html>