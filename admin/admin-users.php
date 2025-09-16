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

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        $message = "Invalid user ID.";
        $message_type = "danger";
    } else {
        try {
            switch ($action) {
                case 'activate':
                    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = "User activated successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Failed to activate user or user not found.";
                        $message_type = "danger";
                    }
                    $stmt->close();
                    break;
                    
                case 'deactivate':
                    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = "User deactivated successfully!";
                        $message_type = "warning";
                    } else {
                        $message = "Failed to deactivate user or user not found.";
                        $message_type = "danger";
                    }
                    $stmt->close();
                    break;
                    
                case 'delete':
                    // Don't allow deletion of admin users
                    $check_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
                    $check_stmt->bind_param("i", $user_id);
                    $check_stmt->execute();
                    $user_data = $check_stmt->get_result()->fetch_assoc();
                    $check_stmt->close();
                    
                    if ($user_data && $user_data['role'] === 'admin') {
                        $message = "Cannot delete admin users.";
                        $message_type = "danger";
                    } else {
                        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        if ($stmt->execute() && $stmt->affected_rows > 0) {
                            $message = "User deleted successfully!";
                            $message_type = "danger";
                        } else {
                            $message = "Failed to delete user or user not found.";
                            $message_type = "danger";
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'reset_password':
                    $new_password = 'TempPass123!';
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $password_hash, $user_id);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = "Password reset successfully. New password: $new_password";
                        $message_type = "info";
                    } else {
                        $message = "Failed to reset password or user not found.";
                        $message_type = "danger";
                    }
                    $stmt->close();
                    break;
                    
                default:
                    $message = "Invalid action specified.";
                    $message_type = "danger";
                    break;
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
            error_log("Admin Users Error: " . $e->getMessage());
        }
    }
}

// Get filter parameters
$role_filter = isset($_GET['role']) && in_array($_GET['role'], ['all', 'student', 'employer', 'alumni', 'admin']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['all', 'active', 'inactive']) ? $_GET['status'] : 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query conditions
$where_conditions = [];
$params = [];
$param_types = '';

if ($role_filter !== 'all') {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $param_types .= 's';
}

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(email LIKE ? OR username LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
    $param_types .= 'ss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM users $where_clause";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

// Get users with pagination
$users_query = "
    SELECT user_id, username, email, role, status, created_at
    FROM users 
    $where_clause
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";

$pagination_params = $params;
$pagination_params[] = $limit;
$pagination_params[] = $offset;
$pagination_types = $param_types . 'ii';

$users_stmt = $conn->prepare($users_query);
if (!empty($pagination_params)) {
    $users_stmt->bind_param($pagination_types, ...$pagination_params);
}
$users_stmt->execute();
$users_result = $users_stmt->get_result();

$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}
$users_stmt->close();

// Get role counts
$role_counts = [];
$role_query = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$role_result = $conn->query($role_query);
while ($row = $role_result->fetch_assoc()) {
    $role_counts[$row['role']] = $row['count'];
}

// Get status counts
$status_counts = [];
$status_query = "SELECT status, COUNT(*) as count FROM users GROUP BY status";
$status_result = $conn->query($status_query);
while ($row = $status_result->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}

$total_pages = $total_users > 0 ? ceil($total_users / $limit) : 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - InternHub Admin</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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
            background-color: #f8f9fa;
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 1.5rem;
            min-height: calc(100vh - 70px);
            transition: var(--transition);
        }

        /* Page Header */
        .welcome-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius-lg);
            color: white;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .welcome-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }

        .welcome-header p {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 1.25rem;
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .stat-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Section Cards */
        .section-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        /* Filter Groups */
        .filter-group {
            margin-bottom: 1.25rem;
        }

        .filter-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .filter-pills {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .filter-pill {
            padding: 0.5rem 0.875rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background: var(--card-bg);
        }

        .filter-pill:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
            text-decoration: none;
        }

        .filter-pill.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Search Form */
        .search-form {
            display: flex;
            gap: 0.5rem;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.1);
            outline: none;
        }

        .btn-outline-primary {
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Table Styling */
        .table-card {
            background: var(--card-bg);
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
            font-size: 0.875rem;
        }

        .table th {
            background: var(--hover-bg);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--text-primary);
            padding: 1rem;
            font-size: 0.875rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Status and Role Badges */
        .status-badge, .role-badge {
            padding: 0.25rem 0.625rem;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        .status-inactive {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
        }

        .role-student {
            background: rgba(105, 108, 255, 0.1);
            color: var(--primary-color);
        }

        .role-employer {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        .role-alumni {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
        }

        .role-admin {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.375rem 0.75rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.75rem;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
            margin: 0.125rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-activate {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        .btn-activate:hover {
            background: var(--success-color);
            color: white;
        }

        .btn-deactivate {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
        }

        .btn-deactivate:hover {
            background: var(--warning-color);
            color: white;
        }

        .btn-reset {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
        }

        .btn-reset:hover {
            background: var(--info-color);
            color: white;
        }

        .btn-delete {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
        }

        .btn-delete:hover {
            background: var(--danger-color);
            color: white;
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .alert-success {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
            border-left: 3px solid var(--success-color);
        }

        .alert-danger {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border-left: 3px solid var(--danger-color);
        }

        .alert-warning {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
            border-left: 3px solid var(--warning-color);
        }

        .alert-info {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
            border-left: 3px solid var(--info-color);
        }

        /* No Data State */
        .no-data {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--text-secondary);
        }

        .no-data i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 1rem;
        }

        /* Pagination */
        .pagination {
            justify-content: center;
            margin-top: 1.5rem;
        }

        .page-link {
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        .page-link:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
        }

        .page-item.active .page-link {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        /* User Info */
        .user-email {
            font-weight: 500;
            color: var(--text-primary);
        }

        .user-username {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 0.125rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .welcome-header {
                padding: 1.25rem;
            }

            .section-card {
                padding: 1.25rem;
            }

            .filter-pills {
                justify-content: center;
            }

            .search-form {
                flex-direction: column;
            }

            .table-responsive {
                font-size: 0.8125rem;
            }

            .action-btn {
                font-size: 0.6875rem;
                padding: 0.25rem 0.5rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <?php include '../includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="welcome-header">
            <h1><i class="bi bi-people me-2"></i>Users Management</h1>
            <p>Manage all system users and their accounts</p>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo ($message_type === 'success') ? 'check-circle' : (($message_type === 'warning') ? 'exclamation-triangle' : (($message_type === 'info') ? 'info-circle' : 'x-circle')); ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum($role_counts); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <?php foreach ($role_counts as $role => $count): ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $count; ?></div>
                    <div class="stat-label"><?php echo ucfirst($role); ?>s</div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="section-card">
            <div class="section-title">
                <i class="bi bi-funnel"></i>
                Filter Users
            </div>
            
            <div class="row">
                <div class="col-lg-4">
                    <div class="filter-group">
                        <label class="filter-label">Role</label>
                        <div class="filter-pills">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['role' => 'all'])); ?>" 
                               class="filter-pill <?php echo ($role_filter === 'all') ? 'active' : ''; ?>">
                                All
                            </a>
                            <?php foreach ($role_counts as $role => $count): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['role' => $role])); ?>" 
                                   class="filter-pill <?php echo ($role_filter === $role) ? 'active' : ''; ?>">
                                    <?php echo ucfirst($role); ?> (<?php echo $count; ?>)
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <div class="filter-pills">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'all'])); ?>" 
                               class="filter-pill <?php echo ($status_filter === 'all') ? 'active' : ''; ?>">
                                All
                            </a>
                            <?php foreach ($status_counts as $status => $count): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => $status])); ?>" 
                                   class="filter-pill <?php echo ($status_filter === $status) ? 'active' : ''; ?>">
                                    <?php echo ucfirst($status); ?> (<?php echo $count; ?>)
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <form method="GET" class="search-form">
                            <?php if (isset($_GET['role'])): ?>
                                <input type="hidden" name="role" value="<?php echo htmlspecialchars($_GET['role']); ?>">
                            <?php endif; ?>
                            <?php if (isset($_GET['status'])): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($_GET['status']); ?>">
                            <?php endif; ?>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <?php if (empty($users)): ?>
            <div class="section-card">
                <div class="no-data">
                    <i class="bi bi-people"></i>
                    <h4>No Users Found</h4>
                    <p>
                        <?php if (!empty($search)): ?>
                            No users found matching your search criteria.
                        <?php else: ?>
                            No users have been registered yet.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search)): ?>
                        <a href="manage-users.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-2"></i>Show All Users
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                        <?php if (!empty($user['username'])): ?>
                                            <div class="user-username"><?php echo htmlspecialchars($user['username']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo htmlspecialchars($user['role']); ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo htmlspecialchars($user['status']); ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap">
                                            <?php if ($user['status'] === 'inactive'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Activate this user?')">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <button type="submit" class="action-btn btn-activate">
                                                        <i class="bi bi-check-lg me-1"></i>Activate
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this user?')">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <button type="submit" class="action-btn btn-deactivate">
                                                        <i class="bi bi-pause me-1"></i>Deactivate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Reset password for this user?')">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                                <input type="hidden" name="action" value="reset_password">
                                                <button type="submit" class="action-btn btn-reset">
                                                    <i class="bi bi-key me-1"></i>Reset PWD
                                                </button>
                                            </form>
                                            
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="action-btn btn-delete">
                                                        <i class="bi bi-trash me-1"></i>Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Users pagination">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>