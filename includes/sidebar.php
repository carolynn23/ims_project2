<?php
// Fixed path handling for config.php
// This works whether sidebar is included from root, admin, or any subfolder

// Check if config.php exists in different possible locations
$config_paths = [
    '../config.php',     // From admin folder
    'config.php',        // From root folder
    '../../config.php'   // From deeper subfolders
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_loaded = true;
        break;
    }
}

if (!$config_loaded) {
    die('Error: config.php not found. Please check file paths.');
}

// Check if the user is logged in and their role
if (!isset($_SESSION['user_id'])) {
    // Determine correct login path based on current location
    $login_paths = [
        '../secure_login.php',  // From admin folder
        'secure_login.php',     // From root folder
        '../login.php',         // Alternative login file
        'login.php'             // Alternative from root
    ];
    
    foreach ($login_paths as $login_path) {
        if (file_exists($login_path)) {
            header("Location: $login_path");
            exit();
        }
    }
    
    // Fallback if no login file found
    die('Please login to access this page.');
}

$role = $_SESSION['role']; // 'student', 'employer', 'alumni', 'admin'
?>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sidebar</title>
  
  <!-- Include Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Custom Sneat-Style Sidebar -->
  <style>
    :root {
      --sidebar-bg: #fff;
      --sidebar-width: 260px;
      --primary-color: #696cff;
      --primary-light: #7367f0;
      --text-primary: #566a7f;
      --text-secondary: #a8aaae;
      --text-muted: #c7c8cc;
      --border-color: #e4e6e8;
      --hover-bg: #f8f9fa;
      --active-bg: #696cff;
      --active-text: #fff;
      --shadow: 0 2px 6px 0 rgba(67, 89, 113, 0.12);
      --transition: all 0.2s ease-in-out;
    }

    body {
      font-family: 'Inter', sans-serif;
      margin: 0;
      padding: 0;
    }

    /* Main Sidebar Container */
    .sidebar {
      width: var(--sidebar-width);
      height: 100vh;
      background: var(--sidebar-bg);
      position: fixed;
      top: 0;
      left: 0;
      z-index: 1000;
      border-right: 1px solid var(--border-color);
      box-shadow: var(--shadow);
      overflow-y: auto;
      overflow-x: hidden;
      transition: var(--transition);
    }

    .sidebar::-webkit-scrollbar {
      width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
      background: transparent;
    }

    .sidebar::-webkit-scrollbar-thumb {
      background: var(--text-muted);
      border-radius: 10px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
      background: var(--text-secondary);
    }

    /* Sidebar Header */
    .sidebar-header {
      padding: 1.5rem 1.25rem;
      border-bottom: 1px solid var(--border-color);
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
      color: white;
      position: relative;
      overflow: hidden;
    }

    .sidebar-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -20%;
      width: 100px;
      height: 100px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      position: relative;
      z-index: 2;
    }

    .sidebar-brand-icon {
      width: 32px;
      height: 32px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      backdrop-filter: blur(10px);
    }

    .sidebar-brand-text {
      font-size: 1.25rem;
      font-weight: 600;
      margin: 0;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .sidebar-subtitle {
      font-size: 0.75rem;
      opacity: 0.9;
      margin: 0.25rem 0 0 0;
      font-weight: 400;
    }

    /* Navigation */
    .sidebar-nav {
      padding: 1rem 0;
    }

    .nav-section {
      margin-bottom: 1.5rem;
    }

    .nav-section-title {
      padding: 0.5rem 1.25rem;
      font-size: 0.75rem;
      font-weight: 600;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.5rem;
    }

    .nav-item {
      margin: 0.125rem 0;
    }

    .nav-link {
      display: flex;
      align-items: center;
      padding: 0.75rem 1.25rem;
      color: var(--text-primary);
      text-decoration: none;
      font-weight: 500;
      font-size: 0.9375rem;
      transition: var(--transition);
      border-radius: 0;
      position: relative;
      margin: 0 0.75rem;
      border-radius: 8px;
    }

    .nav-link:hover {
      background: var(--hover-bg);
      color: var(--primary-color);
      transform: translateX(2px);
    }

    .nav-link.active {
      background: var(--active-bg);
      color: var(--active-text);
      box-shadow: 0 2px 4px rgba(105, 108, 255, 0.3);
    }

    .nav-link.active::before {
      content: '';
      position: absolute;
      left: -0.75rem;
      top: 50%;
      transform: translateY(-50%);
      width: 4px;
      height: 20px;
      background: var(--primary-color);
      border-radius: 0 4px 4px 0;
    }

    .nav-icon {
      width: 20px;
      height: 20px;
      margin-right: 0.75rem;
      font-size: 1.125rem;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: var(--transition);
    }

    .nav-link:hover .nav-icon {
      transform: scale(1.1);
    }

    .nav-text {
      flex: 1;
      transition: var(--transition);
    }

    /* Badge for notifications */
    .nav-badge {
      background: #ff4757;
      color: white;
      font-size: 0.6875rem;
      padding: 0.125rem 0.375rem;
      border-radius: 10px;
      margin-left: auto;
      font-weight: 600;
      min-width: 18px;
      text-align: center;
    }

    /* Logout Button */
    .nav-logout {
      margin-top: auto;
      border-top: 1px solid var(--border-color);
      padding-top: 1rem;
    }

    .nav-link.logout {
      color: #ff4757;
      margin: 0 0.75rem;
    }

    .nav-link.logout:hover {
      background: rgba(255, 71, 87, 0.1);
      color: #ff4757;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }
      
      .sidebar.show {
        transform: translateX(0);
      }
    }
  </style>
</head>

<aside class="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">
                <i class="bi bi-mortarboard" style="color: white; font-size: 1.125rem;"></i>
            </div>
            <div>
                <h5 class="sidebar-brand-text">InternHub</h5>
                <p class="sidebar-subtitle"><?= ucfirst($role) ?> Portal</p>
            </div>
        </div>
    </div>

    <!-- Main Navigation -->
    <nav class="sidebar-nav">
        
        <!-- Student Role Menu -->
        <?php if ($role === 'student'): ?>
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="student-dashboard.php">
                            <i class="nav-icon bi bi-house-door"></i>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="student-applications.php">
                            <i class="nav-icon bi bi-file-earmark-text"></i>
                            <span class="nav-text">My Applications</span>
                            <span class="nav-badge">3</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="saved.php">
                            <i class="nav-icon bi bi-star"></i>
                            <span class="nav-text">Saved Internships</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Communication</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="student-notifications.php">
                            <i class="nav-icon bi bi-bell"></i>
                            <span class="nav-text">Notifications</span>
                            <span class="nav-badge">5</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="mentorship.php">
                            <i class="nav-icon bi bi-person-check"></i>
                            <span class="nav-text">Mentorship</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="student-profile.php">
                            <i class="nav-icon bi bi-person"></i>
                            <span class="nav-text">Profile</span>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Employer Role Menu -->
        <?php if ($role === 'employer'): ?>
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="employer-dashboard.php">
                            <i class="nav-icon bi bi-house-door"></i>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="post-internship.php">
                            <i class="nav-icon bi bi-pencil-square"></i>
                            <span class="nav-text">Post Internship</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view-all-applications.php">
                            <i class="nav-icon bi bi-list"></i>
                            <span class="nav-text">All Applications</span>
                            <span class="nav-badge">12</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Communication</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="notifications.php">
                            <i class="nav-icon bi bi-bell"></i>
                            <span class="nav-text">Notifications</span>
                            <span class="nav-badge">8</span>
                        </a>
                    </li>
                </ul>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Account</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="edit-employer-profile.php">
                            <i class="nav-icon bi bi-person"></i>
                            <span class="nav-text">Profile</span>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Alumni Role Menu -->
        <?php if ($role === 'alumni'): ?>
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="alumni-dashboard.php">
                            <i class="nav-icon bi bi-house-door"></i>
                            <span class="nav-text">Dashboard</span>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Admin Role Menu -->
        <?php if ($role === 'admin'): ?>
            <!-- Admin Dashboard Section -->
            <div class="nav-section">
                <div class="nav-section-title">Dashboard</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="admin-dashboard.php">
                            <i class="nav-icon bi bi-speedometer2"></i>
                            <span class="nav-text">Main Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin-post-internships.php">
                            <i class="nav-icon bi bi-shield-check"></i>
                            <span class="nav-text">Post Internships</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- User Management Section -->
            <div class="nav-section">
                <div class="nav-section-title">User Management</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="admin-users.php">
                            <i class="nav-icon bi bi-people"></i>
                            <span class="nav-text">All Users</span>
                            <span class="nav-badge" id="pending-users">5</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Content Management Section -->
            <div class="nav-section">
                <div class="nav-section-title">Content Management</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="view-all-internships.php">
                            <i class="nav-icon bi bi-briefcase"></i>
                            <span class="nav-text">Internships</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin-applications.php">
                            <i class="nav-icon bi bi-file-earmark-text"></i>
                            <span class="nav-text">Applications</span>
                            <span class="nav-badge" id="pending-applications">12</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin-companies.php">
                            <i class="nav-icon bi bi-building"></i>
                            <span class="nav-text">Companies</span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- System Section -->
            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="admin-settings.php">
                            <i class="nav-icon bi bi-gear"></i>
                            <span class="nav-text">Settings</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin-reports.php">
                            <i class="nav-icon bi bi-graph-up"></i>
                            <span class="nav-text">Reports</span>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Logout Section (for all roles) -->
        <div class="nav-logout">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link logout" href="<?= file_exists('../logout.php') ? '../logout.php' : 'logout.php' ?>">
                        <i class="nav-icon bi bi-box-arrow-right"></i>
                        <span class="nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </div>

    </nav>
</aside>