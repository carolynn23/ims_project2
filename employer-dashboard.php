<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get employer information
$stmt = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();

if (!$employer) {
    die("Employer profile not found.");
}

$employer_id = $employer['employer_id'];
$company_name = $employer['company_name'];

// Handle messages
$success_message = '';
$error_message = '';

// Handle internship deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    
    if ($delete_id > 0) {
        try {
            // Start transaction for safe deletion
            $conn->begin_transaction();
            
            // Verify the internship belongs to this employer
            $verify = $conn->prepare("SELECT internship_id, title FROM internships WHERE internship_id = ? AND employer_id = ?");
            $verify->bind_param("ii", $delete_id, $employer_id);
            $verify->execute();
            $internship_data = $verify->get_result()->fetch_assoc();
            
            if ($internship_data) {
                $internship_title = $internship_data['title'];
                
                // Delete related data in correct order to avoid foreign key constraints
                
                // 1. Delete notifications related to this internship
                $delete_notifications = $conn->prepare("
                    DELETE n FROM notifications n 
                    JOIN applications a ON n.message LIKE CONCAT('%', ?, '%') 
                    WHERE a.internship_id = ?
                ");
                $delete_notifications->bind_param("si", $internship_title, $delete_id);
                $delete_notifications->execute();
                
                // 2. Delete saved internships
                $delete_saved = $conn->prepare("DELETE FROM saved_internships WHERE internship_id = ?");
                $delete_saved->bind_param("i", $delete_id);
                $delete_saved->execute();
                
                // 3. Delete applications for this internship
                $delete_applications = $conn->prepare("DELETE FROM applications WHERE internship_id = ?");
                $delete_applications->bind_param("i", $delete_id);
                $delete_applications->execute();
                $affected_applications = $conn->affected_rows;
                
                // 4. Finally delete the internship itself
                $delete_internship = $conn->prepare("DELETE FROM internships WHERE internship_id = ?");
                $delete_internship->bind_param("i", $delete_id);
                $delete_internship->execute();
                
                if ($conn->affected_rows > 0) {
                    $conn->commit();
                    $success_message = "Internship '{$internship_title}' deleted successfully!" . 
                        ($affected_applications > 0 ? " ({$affected_applications} applications were also removed)" : "");
                } else {
                    $conn->rollback();
                    $error_message = "Failed to delete internship. Please try again.";
                }
                
            } else {
                $conn->rollback();
                $error_message = "Internship not found or you don't have permission to delete it.";
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error deleting internship: " . $e->getMessage();
            error_log("Internship deletion error: " . $e->getMessage());
        }
    } else {
        $error_message = "Invalid internship ID.";
    }
}

// Fetch internships posted by employer (refresh data after potential deletion)
$sql = "
    SELECT i.internship_id, i.title, i.location, i.deadline, i.created_at, i.type,
           COUNT(a.application_id) AS application_count,
           SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
           SUM(CASE WHEN a.status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count
    FROM internships i
    LEFT JOIN applications a ON i.internship_id = a.internship_id
    WHERE i.employer_id = ?
    GROUP BY i.internship_id
    ORDER BY i.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$internships = $stmt->get_result();

// Generate CSRF token for forms
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard - <?= htmlspecialchars($company_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Sneat Design System Colors */
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
            --shadow-lg: 0 6px 14px 0 rgba(67, 89, 113, 0.15);
            --transition: all 0.2s ease-in-out;
            --border-radius: 8px;
            --border-radius-lg: 12px;
            
            /* Additional colors for dashboard */
            --success-color: #71dd37;
            --warning-color: #ffb400;
            --danger-color: #ff3e1d;
            --info-color: #03c3ec;
            --light-color: #fcfdfd;
            --dark-color: #233446;
            --card-bg: #fff;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: var(--text-primary);
            margin: 0;
            padding: 0;
        }

        /* Main Content - Account for navbar height */
        .main-content {
            margin-left: 260px;
            margin-top: 70px; /* Account for fixed navbar */
            padding: 1rem;
            min-height: calc(100vh - 70px);
            transition: var(--transition);
        }

        /* Welcome Header - Smaller and less bold */
        .welcome-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius);
            color: white;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
        }

        .welcome-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .welcome-icon {
            font-size: 1.5rem;
            opacity: 0.9;
        }

        .welcome-text h1 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            line-height: 1.3;
        }

        .welcome-text p {
            font-size: 0.875rem;
            opacity: 0.85;
            margin: 0;
            font-weight: 400;
        }

        /* Compact Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }

        .stat-icon.primary { background: var(--primary-color); }
        .stat-icon.success { background: var(--success-color); }
        .stat-icon.warning { background: var(--warning-color); }
        .stat-icon.info { background: var(--info-color); }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.125rem;
            line-height: 1;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.8125rem;
            font-weight: 500;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Buttons */
        .btn {
            border-radius: var(--border-radius);
            font-weight: 500;
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            color: white;
        }

        .btn-outline-primary {
            background: transparent;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        /* Horizontal Tabular Internship Cards */
        .internships-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .internships-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: grid;
            grid-template-columns: 2fr 1fr 120px 120px 80px 180px;
            gap: 1rem;
            font-weight: 600;
            font-size: 0.8125rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .internship-row {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: grid;
            grid-template-columns: 2fr 1fr 120px 120px 80px 180px;
            gap: 1rem;
            align-items: center;
            transition: var(--transition);
        }

        .internship-row:last-child {
            border-bottom: none;
        }

        .internship-row:hover {
            background: var(--hover-bg);
        }

        .internship-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9375rem;
            margin-bottom: 0.25rem;
        }

        .internship-meta {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .internship-location {
            color: var(--text-primary);
            font-size: 0.8125rem;
        }

        .internship-stats {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-weight: 500;
        }

        .stat-item.total {
            background: rgba(105, 108, 255, 0.1);
            color: var(--primary-color);
        }

        .stat-item.pending {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
        }

        .stat-item.accepted {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        .deadline-cell {
            font-size: 0.8125rem;
            color: var(--text-primary);
        }

        .deadline-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.6875rem;
            font-weight: 600;
            margin-top: 0.25rem;
            display: inline-block;
        }

        .deadline-badge.danger {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
        }

        .deadline-badge.warning {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
        }

        .deadline-badge.success {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        .type-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .type-badge.remote {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        .type-badge.onsite {
            background: rgba(105, 108, 255, 0.1);
            color: var(--primary-color);
        }

        .type-badge.hybrid {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8125rem;
            transition: var(--transition);
        }

        .btn-view {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(3, 195, 236, 0.3);
        }

        .btn-view:hover {
            background: var(--info-color);
            color: white;
        }

        .btn-edit {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 180, 0, 0.3);
        }

        .btn-edit:hover {
            background: var(--warning-color);
            color: white;
        }

        .btn-delete {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(255, 62, 29, 0.3);
        }

        .btn-delete:hover {
            background: var(--danger-color);
            color: white;
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        .empty-state-icon {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .empty-state-subtitle {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .internships-header,
            .internship-row {
                grid-template-columns: 2fr 1fr 100px 100px 60px 140px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                margin-top: 70px;
                padding: 0.75rem;
            }

            .internships-header,
            .internship-row {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .internships-header {
                display: none;
            }

            .internship-row {
                display: block;
                padding: 1rem;
            }

            .internship-title {
                margin-bottom: 0.5rem;
            }

            .internship-meta {
                margin-bottom: 0.75rem;
            }

            .row-section {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 0.5rem;
                padding-bottom: 0.5rem;
                border-bottom: 1px solid #f0f0f0;
            }

            .row-section:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }

            .section-label {
                font-size: 0.75rem;
                color: var(--text-secondary);
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .stats-container {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 0.5rem;
            }

            .stat-card {
                padding: 0.75rem;
            }

            .stat-icon {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }

            .stat-number {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 768px) {
            .welcome-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .action-buttons {
                justify-content: center;
                gap: 0.5rem;
            }

            .btn-action {
                width: 28px;
                height: 28px;
                font-size: 0.75rem;
            }
        }
    </style>
</head>

<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
    <!-- Welcome Header -->
    <div class="welcome-header">
        <div class="welcome-content">
            <i class="bi bi-building welcome-icon"></i>
            <div class="welcome-text">
                <h1>Welcome back, <?= htmlspecialchars($company_name) ?></h1>
                <p>Manage your internship postings and applications</p>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <?php 
    $total_internships = $internships->num_rows;
    $internships->data_seek(0); // Reset pointer for stats calculation
    $total_applications = 0;
    $pending_applications = 0;
    $accepted_applications = 0;
    while ($row = $internships->fetch_assoc()) {
        $total_applications += $row['application_count'];
        $pending_applications += $row['pending_count'];
        $accepted_applications += $row['accepted_count'];
    }
    $internships->data_seek(0); // Reset pointer again for display
    ?>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-briefcase"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $total_internships ?></div>
                <div class="stat-label">Total Internships</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $total_applications ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $pending_applications ?></div>
                <div class="stat-label">Pending Reviews</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-plus-circle"></i>
            </div>
            <div class="stat-content">
                <a href="post-internship.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg"></i>
                    Post New
                </a>
            </div>
        </div>
    </div>

    <!-- Internships Section -->
    <div class="section-header">
        <h2 class="section-title">
            <i class="bi bi-briefcase"></i>
            Your Internship Postings
        </h2>
        <div class="section-actions">
            <a href="view-all-applications.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-list-check"></i>
                All Applications
            </a>
        </div>
    </div>

    <?php if ($total_internships > 0): ?>
        <div class="internships-container">
            <div class="internships-header">
                <div>Title & Details</div>
                <div>Location</div>
                <div>Applications</div>
                <div>Deadline</div>
                <div>Type</div>
                <div>Actions</div>
            </div>
            
            <?php while ($row = $internships->fetch_assoc()): 
                $days_left = ceil((strtotime($row['deadline']) - time()) / (60 * 60 * 24));
                $badge_class = $days_left <= 3 ? 'danger' : ($days_left <= 7 ? 'warning' : 'success');
            ?>
                <div class="internship-row">
                    <!-- Title & Meta -->
                    <div>
                        <div class="internship-title"><?= htmlspecialchars($row['title']) ?></div>
                        <div class="internship-meta">
                            <span><i class="bi bi-calendar3"></i> Posted <?= date('M j, Y', strtotime($row['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <!-- Location -->
                    <div class="internship-location d-none d-lg-block">
                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($row['location']) ?>
                    </div>
                    
                    <!-- Applications Stats -->
                    <div class="internship-stats d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Applications</span>
                        </div>
                        <div class="stat-item total"><?= $row['application_count'] ?></div>
                        <div class="stat-item pending"><?= $row['pending_count'] ?></div>
                        <div class="stat-item accepted"><?= $row['accepted_count'] ?></div>
                    </div>
                    
                    <!-- Deadline -->
                    <div class="deadline-cell d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Deadline</span>
                        </div>
                        <?= date('M j, Y', strtotime($row['deadline'])) ?>
                        <div class="deadline-badge <?= $badge_class ?>">
                            <?= $days_left ?> days left
                        </div>
                    </div>
                    
                    <!-- Type -->
                    <div class="d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Type</span>
                        </div>
                        <span class="type-badge <?= strtolower($row['type']) ?>">
                            <?= htmlspecialchars(ucfirst($row['type'])) ?>
                        </span>
                    </div>
                    
                    <!-- Actions -->
                    <div class="action-buttons">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Actions</span>
                        </div>
                        <a href="view-applications.php?internship_id=<?= $row['internship_id'] ?>" 
                           class="btn-action btn-view" title="View Applications">
                            <i class="bi bi-eye"></i>
                        </a>
                        
                        <a href="edit-internship.php?internship_id=<?= $row['internship_id'] ?>" 
                           class="btn-action btn-edit" title="Edit Internship">
                            <i class="bi bi-pencil"></i>
                        </a>
                        
                        <form method="POST" style="display: inline;" 
                              onsubmit="return confirm('⚠️ Are you sure you want to delete &quot;<?= addslashes(htmlspecialchars($row['title'])) ?>&quot;?\n\nThis will permanently remove:\n• The internship posting\n• All applications (<?= $row['application_count'] ?> total)\n• All related data\n\nThis action cannot be undone!');">
                            <input type="hidden" name="delete_id" value="<?= $row['internship_id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <button type="submit" class="btn-action btn-delete" title="Delete Internship">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Mobile-only sections -->
                    <div class="d-lg-none mt-2">
                        <div class="row-section">
                            <span class="section-label">Location</span>
                            <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($row['location']) ?></span>
                        </div>
                        <div class="row-section">
                            <span class="section-label">Applications</span>
                            <div class="d-flex gap-1">
                                <span class="stat-item total"><?= $row['application_count'] ?></span>
                                <span class="stat-item pending"><?= $row['pending_count'] ?></span>
                                <span class="stat-item accepted"><?= $row['accepted_count'] ?></span>
                            </div>
                        </div>
                        <div class="row-section">
                            <span class="section-label">Deadline</span>
                            <div>
                                <?= date('M j, Y', strtotime($row['deadline'])) ?>
                                <span class="deadline-badge <?= $badge_class ?> ms-2">
                                    <?= $days_left ?> days left
                                </span>
                            </div>
                        </div>
                        <div class="row-section">
                            <span class="section-label">Type</span>
                            <span class="type-badge <?= strtolower($row['type']) ?>">
                                <?= htmlspecialchars(ucfirst($row['type'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
    <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="bi bi-briefcase"></i>
            </div>
            <h3 class="empty-state-title">No Internships Posted Yet</h3>
            <p class="empty-state-subtitle">
                Start attracting talented students by posting your first internship opportunity!
            </p>
            <a href="post-internship.php" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>
                Post Your First Internship
            </a>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss success alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

</body>
</html>