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

// Function to check if table/column exists - FIXED for MariaDB/MySQL compatibility
function tableExists($conn, $table) {
    // Validate table name to prevent SQL injection
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        return false;
    }
    
    // Use INFORMATION_SCHEMA which works better with prepared statements
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    if (!$stmt) {
        error_log("Failed to prepare tableExists statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("s", $table);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        $exists = ($row['count'] > 0);
    } else {
        $exists = false;
    }
    $stmt->close();
    
    return $exists;
}

function columnExists($conn, $table, $column) {
    // Validate table and column names to prevent SQL injection
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) || 
        !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
        return false;
    }
    
    // Use INFORMATION_SCHEMA which works better with prepared statements
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$stmt) {
        error_log("Failed to prepare columnExists statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        $exists = ($row['count'] > 0);
    } else {
        $exists = false;
    }
    $stmt->close();
    
    return $exists;
}

// Check database structure
$tables_exist = [
    'employers' => tableExists($conn, 'employers'),
    'users' => tableExists($conn, 'users'),
    'internships' => tableExists($conn, 'internships'),
    'applications' => tableExists($conn, 'applications')
];

$users_has_status = $tables_exist['users'] ? columnExists($conn, 'users', 'status') : false;
$users_has_created_at = $tables_exist['users'] ? columnExists($conn, 'users', 'created_at') : false;

$message = '';
$message_type = '';

// Handle company actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $tables_exist['employers']) {
    $employer_id = (int)($_POST['employer_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    // Validate required parameters
    if ($employer_id <= 0 && $action !== 'reset_password') {
        $message = "Invalid employer ID.";
        $message_type = "danger";
    } elseif ($user_id <= 0 && in_array($action, ['approve', 'suspend', 'reset_password'])) {
        $message = "Invalid user ID.";
        $message_type = "danger";
    } else {
        try {
            $conn->autocommit(FALSE); // Start transaction
            
            switch ($action) {
                case 'approve':
                    if ($users_has_status && $user_id) {
                        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE user_id = ? AND role = 'employer'");
                        if ($stmt) {
                            $stmt->bind_param("i", $user_id);
                            if ($stmt->execute() && $stmt->affected_rows > 0) {
                                $message = "Company approved successfully!";
                                $message_type = "success";
                            } else {
                                $message = "Failed to approve company or company not found.";
                                $message_type = "danger";
                            }
                            $stmt->close();
                        }
                    } else {
                        $message = "Cannot approve: status column not available.";
                        $message_type = "warning";
                    }
                    break;
                    
                case 'suspend':
                    if ($users_has_status && $user_id) {
                        $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ? AND role = 'employer'");
                        if ($stmt) {
                            $stmt->bind_param("i", $user_id);
                            if ($stmt->execute() && $stmt->affected_rows > 0) {
                                $message = "Company suspended successfully!";
                                $message_type = "warning";
                            } else {
                                $message = "Failed to suspend company or company not found.";
                                $message_type = "danger";
                            }
                            $stmt->close();
                        }
                    } else {
                        $message = "Cannot suspend: status column not available.";
                        $message_type = "warning";
                    }
                    break;
                    
                case 'delete':
                    // Delete related internships first
                    if ($tables_exist['internships']) {
                        $delete_internships = $conn->prepare("DELETE FROM internships WHERE employer_id = ?");
                        if ($delete_internships) {
                            $delete_internships->bind_param("i", $employer_id);
                            $delete_internships->execute();
                            $delete_internships->close();
                        }
                    }
                    
                    // Delete employer record
                    $stmt = $conn->prepare("DELETE FROM employers WHERE employer_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("i", $employer_id);
                        if ($stmt->execute() && $stmt->affected_rows > 0) {
                            // Delete user account
                            if ($user_id) {
                                $delete_user = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                                if ($delete_user) {
                                    $delete_user->bind_param("i", $user_id);
                                    $delete_user->execute();
                                    $delete_user->close();
                                }
                            }
                            $message = "Company deleted successfully!";
                            $message_type = "danger";
                        } else {
                            $message = "Failed to delete company or company not found.";
                            $message_type = "danger";
                        }
                        $stmt->close();
                    }
                    break;
                    
                case 'reset_password':
                    if ($user_id) {
                        $new_password = 'TempPass123!';
                        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                        if ($stmt) {
                            $stmt->bind_param("si", $password_hash, $user_id);
                            if ($stmt->execute() && $stmt->affected_rows > 0) {
                                $message = "Password reset successfully. New password: $new_password";
                                $message_type = "info";
                            } else {
                                $message = "Failed to reset password or user not found.";
                                $message_type = "danger";
                            }
                            $stmt->close();
                        }
                    } else {
                        $message = "Invalid user ID for password reset.";
                        $message_type = "danger";
                    }
                    break;
                    
                default:
                    $message = "Invalid action specified.";
                    $message_type = "danger";
                    break;
            }
            
            if ($message_type !== 'danger') {
                $conn->commit();
            } else {
                $conn->rollback();
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $message = "Error: " . $e->getMessage();
            $message_type = "danger";
            error_log("Companies Management Error: " . $e->getMessage());
        } finally {
            $conn->autocommit(TRUE); // Re-enable autocommit
        }
    }
}

// Get filter parameters with validation
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['all', 'active', 'inactive', 'pending']) ? $_GET['status'] : 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Initialize data
$companies = [];
$total_companies = 0;
$status_counts = [];

if ($tables_exist['employers']) {
    try {
        // Build query conditions
        $where_conditions = [];
        $params = [];
        $param_types = '';
        
        if ($status_filter !== 'all' && $users_has_status) {
            $where_conditions[] = "u.status = ?";
            $params[] = $status_filter;
            $param_types .= 's';
        }
        
        if (!empty($search)) {
            $where_conditions[] = "(e.company_name LIKE ? OR e.industry LIKE ? OR u.email LIKE ?)";
            $search_param = "%$search%";
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
            $param_types .= 'sss';
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        // Get total count
        $count_query = "
            SELECT COUNT(*) as total 
            FROM employers e 
            LEFT JOIN users u ON e.user_id = u.user_id 
            $where_clause
        ";
        
        $count_stmt = $conn->prepare($count_query);
        if ($count_stmt) {
            if (!empty($params)) {
                $count_stmt->bind_param($param_types, ...$params);
            }
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            if ($count_result) {
                $total_companies = $count_result->fetch_assoc()['total'] ?? 0;
            }
            $count_stmt->close();
        }
        
        // Get companies with pagination
        $companies_query = "
            SELECT 
                e.employer_id,
                e.company_name,
                e.industry,
                e.company_profile,
                e.contact_person,
                e.phone,
                e.address,
                e.website,
                u.user_id,
                u.email,
                " . ($users_has_status ? "u.status," : "'active' as status,") . "
                " . ($users_has_created_at ? "u.created_at," : "NOW() as created_at,") . "
                " . ($tables_exist['internships'] ? "(SELECT COUNT(*) FROM internships i WHERE i.employer_id = e.employer_id) as internship_count," : "0 as internship_count,") . "
                " . ($tables_exist['applications'] && $tables_exist['internships'] ? "(SELECT COUNT(*) FROM applications a JOIN internships i ON a.internship_id = i.internship_id WHERE i.employer_id = e.employer_id) as application_count" : "0 as application_count") . "
            FROM employers e
            LEFT JOIN users u ON e.user_id = u.user_id
            $where_clause
            ORDER BY " . ($users_has_created_at ? "u.created_at" : "e.employer_id") . " DESC
            LIMIT ? OFFSET ?
        ";
        
        // Add pagination parameters
        $pagination_params = $params;
        $pagination_params[] = $limit;
        $pagination_params[] = $offset;
        $pagination_types = $param_types . 'ii';
        
        $companies_stmt = $conn->prepare($companies_query);
        if ($companies_stmt) {
            if (!empty($pagination_params)) {
                $companies_stmt->bind_param($pagination_types, ...$pagination_params);
            }
            $companies_stmt->execute();
            $companies_result = $companies_stmt->get_result();
            
            if ($companies_result) {
                while ($row = $companies_result->fetch_assoc()) {
                    $companies[] = $row;
                }
            }
            $companies_stmt->close();
        }
        
        // Get status counts
        if ($users_has_status) {
            $status_query = "
                SELECT u.status, COUNT(*) as count 
                FROM employers e 
                LEFT JOIN users u ON e.user_id = u.user_id 
                WHERE u.role = 'employer'
                GROUP BY u.status
            ";
            $status_result = $conn->query($status_query);
            if ($status_result) {
                while ($row = $status_result->fetch_assoc()) {
                    $status_counts[$row['status']] = $row['count'];
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Companies Query Error: " . $e->getMessage());
        $message = "Error loading companies data. Please try again.";
        $message_type = "danger";
    }
}

$total_pages = $total_companies > 0 ? ceil($total_companies / $limit) : 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies Management - InternHub Admin</title>
    
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
            background: linear-gradient(135deg, var(--success-color) 0%, #66c732 100%);
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

        .table tbody tr:hover {
            background: var(--hover-bg);
        }

        /* Company Info */
        .company-name {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.125rem;
        }

        .company-industry {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .company-contact {
            font-size: 0.875rem;
            color: var(--text-primary);
            margin-bottom: 0.125rem;
        }

        .company-phone {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .company-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .stat-item {
            text-align: center;
        }

        .stat-item-number {
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Status Badges */
        .status-badge {
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

        .status-pending {
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

        .btn-approve {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        .btn-approve:hover {
            background: var(--success-color);
            color: white;
        }

        .btn-suspend {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
        }

        .btn-suspend:hover {
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

        .alert-warning {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
            border-left: 3px solid var(--warning-color);
        }

        .alert-danger {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border-left: 3px solid var(--danger-color);
        }

        .alert-info {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
            border-left: 3px solid var(--info-color);
        }

        /* Database Status Alert */
        .status-info {
            background: var(--info-color);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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

            .company-stats {
                flex-direction: column;
                gap: 0.25rem;
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
            <h1><i class="bi bi-building me-2"></i>Companies Management</h1>
            <p>Manage employer companies and their accounts</p>
        </div>

        <!-- Database Status Check -->
        <?php if (!$tables_exist['employers']): ?>
            <div class="status-info">
                <i class="bi bi-exclamation-triangle"></i>
                <div>
                    <strong>Employers table not found!</strong> The companies management feature requires the 'employers' table to be created in your database.
                </div>
            </div>
        <?php endif; ?>

        <!-- Alert Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo ($message_type === 'success') ? 'check-circle' : (($message_type === 'warning') ? 'exclamation-triangle' : (($message_type === 'info') ? 'info-circle' : 'x-circle')); ?> me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($tables_exist['employers']): ?>
       

        <!-- Filters -->
        <div class="section-card">
            <div class="section-title">
                <i class="bi bi-funnel"></i>
                Filter Companies
            </div>
            
            <div class="row">
                <?php if ($users_has_status && !empty($status_counts)): ?>
                <div class="col-lg-6">
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <div class="filter-pills">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'all'])); ?>" 
                               class="filter-pill <?php echo ($status_filter === 'all') ? 'active' : ''; ?>">
                                All (<?php echo array_sum($status_counts); ?>)
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
                <?php endif; ?>
                <div class="col-lg-6">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <form method="GET" class="search-form">
                            <?php if (isset($_GET['status'])): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($_GET['status']); ?>">
                            <?php endif; ?>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search companies..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Companies Table -->
        <div class="table-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Statistics</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($companies)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="no-data">
                                        <i class="bi bi-building"></i>
                                        <h4>No Companies Found</h4>
                                        <p>
                                            <?php if (!empty($search)): ?>
                                                No companies found matching your search criteria.
                                            <?php else: ?>
                                                No companies have registered yet.
                                            <?php endif; ?>
                                        </p>
                                        <?php if (!empty($search)): ?>
                                            <a href="admin-companies.php" class="btn btn-outline-primary">
                                                <i class="bi bi-arrow-left me-2"></i>Show All Companies
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($companies as $company): ?>
                                <tr>
                                    <td>
                                        <div class="company-name"><?php echo htmlspecialchars($company['company_name']); ?></div>
                                        <?php if (!empty($company['industry'])): ?>
                                            <div class="company-industry"><?php echo htmlspecialchars($company['industry']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="company-contact"><?php echo htmlspecialchars($company['email']); ?></div>
                                        <?php if (!empty($company['phone'])): ?>
                                            <div class="company-phone"><?php echo htmlspecialchars($company['phone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($users_has_status): ?>
                                            <span class="status-badge status-<?php echo htmlspecialchars($company['status']); ?>">
                                                <?php echo htmlspecialchars($company['status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-active">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="company-stats">
                                            <div class="stat-item">
                                                <div class="stat-item-number"><?php echo (int)$company['internship_count']; ?></div>
                                                <div>Internships</div>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-item-number"><?php echo (int)$company['application_count']; ?></div>
                                                <div>Applications</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($users_has_created_at && !empty($company['created_at'])): ?>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($company['created_at'])); ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">Unknown</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap">
                                            <?php if ($users_has_status && $company['status'] !== 'active'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Approve this company?')">
                                                    <input type="hidden" name="employer_id" value="<?php echo (int)$company['employer_id']; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$company['user_id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="action-btn btn-approve">
                                                        <i class="bi bi-check-lg me-1"></i>Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($users_has_status && $company['status'] === 'active'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Suspend this company?')">
                                                    <input type="hidden" name="employer_id" value="<?php echo (int)$company['employer_id']; ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo (int)$company['user_id']; ?>">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <button type="submit" class="action-btn btn-suspend">
                                                        <i class="bi bi-pause me-1"></i>Suspend
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Reset password for this company?')">
                                                <input type="hidden" name="employer_id" value="<?php echo (int)$company['employer_id']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$company['user_id']; ?>">
                                                <input type="hidden" name="action" value="reset_password">
                                                <button type="submit" class="action-btn btn-reset">
                                                    <i class="bi bi-key me-1"></i>Reset PWD
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this company? This will also delete all related internships and applications.')">
                                                <input type="hidden" name="employer_id" value="<?php echo (int)$company['employer_id']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int)$company['user_id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="action-btn btn-delete">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Companies pagination">
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
                    if (alert.style.display !== 'none') {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });
        });

        console.log('Companies management page initialized');
    </script>
</body>
</html>