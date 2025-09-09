<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php'); 
    exit();
}

$user_id = (int)$_SESSION['user_id'];

// Get student ID
$st = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$st->bind_param("i", $user_id);
$st->execute();
$student_result = $st->get_result()->fetch_assoc();
$student_id = (int)($student_result['student_id'] ?? 0);

if ($student_id <= 0) {
    echo "Student profile not found."; 
    exit();
}

// Handle feedback messages
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'application_withdrawn':
            $success_message = 'Application successfully withdrawn.';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_application':
            $error_message = 'Invalid application ID.';
            break;
        case 'invalid_student':
            $error_message = 'Student profile error.';
            break;
        case 'application_not_found':
            $error_message = 'Application not found or doesn\'t belong to you.';
            break;
        case 'withdrawal_failed':
            $error_message = 'Failed to withdraw application. Please try again.';
            break;
        case 'cannot_withdraw':
            $status = htmlspecialchars($_GET['status'] ?? 'unknown');
            $error_message = "Cannot withdraw application with status: " . ucfirst($status);
            break;
    }
}

// Fetch all applications for this student
$sql = "
    SELECT a.application_id, a.cover_letter, a.status, a.applied_at, a.resume,
           i.title, i.location, i.duration, 
           e.company_name
    FROM applications a
    JOIN internships i ON a.internship_id = i.internship_id
    JOIN employers e ON i.employer_id = e.employer_id
    WHERE a.student_id = ?
    ORDER BY a.applied_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$applications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - Student Portal</title>
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

        /* Main Content - Account for navbar height */
        .main-content {
            margin-left: 260px;
            margin-top: 70px; /* Account for fixed navbar */
            padding: 1rem;
            min-height: calc(100vh - 70px);
            transition: var(--transition);
        }

        /* Compact Welcome Header */
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        .stat-icon.danger { background: var(--danger-color); }
        .stat-icon.warning { background: var(--warning-color); }

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
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
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
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        /* Tabular Applications Display */
        .applications-container {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .applications-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: grid;
            grid-template-columns: 2fr 1fr 120px 100px 120px 80px 150px;
            gap: 1rem;
            font-weight: 600;
            font-size: 0.8125rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .application-row {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: grid;
            grid-template-columns: 2fr 1fr 120px 100px 120px 80px 150px;
            gap: 1rem;
            align-items: center;
            transition: var(--transition);
        }

        .application-row:last-child {
            border-bottom: none;
        }

        .application-row:hover {
            background: var(--hover-bg);
        }

        .application-title {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9375rem;
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .application-meta {
            color: var(--text-secondary);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .company-name {
            color: var(--primary-color);
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .location-text {
            color: var(--text-primary);
            font-size: 0.8125rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-badge.pending {
            background: rgba(105, 108, 255, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(105, 108, 255, 0.3);
        }

        .status-badge.accepted {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(113, 221, 55, 0.3);
        }

        .status-badge.rejected {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(255, 62, 29, 0.3);
        }

        .status-badge.withdrawn {
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 180, 0, 0.3);
        }

        .date-text {
            font-size: 0.8125rem;
            color: var(--text-primary);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.25rem;
        }

        .btn-action {
            width: 28px;
            height: 28px;
            padding: 0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-download {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(3, 195, 236, 0.3);
        }

        .btn-download:hover {
            background: var(--info-color);
            color: white;
        }

        .btn-withdraw {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(255, 62, 29, 0.3);
        }

        .btn-withdraw:hover {
            background: var(--danger-color);
            color: white;
        }

        .status-text {
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-text.success {
            color: var(--success-color);
        }

        .status-text.danger {
            color: var(--danger-color);
        }

        .status-text.muted {
            color: var(--text-muted);
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-weight: 500;
            font-size: 0.875rem;
            border-left: 3px solid;
        }

        .alert-success {
            background-color: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
            border-left-color: var(--success-color);
        }

        .alert-danger {
            background-color: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border-left-color: var(--danger-color);
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

        .empty-state-text {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            font-size: 0.9375rem;
        }

        /* Dummy Content Section */
        .dummy-content {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .dummy-content h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dummy-content p {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1rem;
            font-size: 0.9375rem;
        }

        .dummy-content .highlight {
            background: rgba(105, 108, 255, 0.1);
            color: var(--primary-color);
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .applications-header,
            .application-row {
                grid-template-columns: 2fr 1fr 100px 80px 100px 60px 120px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                margin-top: 70px;
                padding: 0.75rem;
            }

            .applications-header {
                display: none;
            }

            .application-row {
                display: block;
                padding: 1rem;
            }

            .application-title {
                margin-bottom: 0.5rem;
            }

            .application-meta {
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
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
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

            .action-buttons {
                justify-content: center;
                gap: 0.5rem;
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
            <i class="bi bi-file-text welcome-icon"></i>
            <div class="welcome-text">
                <h1>My Applications</h1>
                <p>Track and manage your internship applications</p>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <?php
    $applications->data_seek(0); // Reset pointer
    $stats = ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'withdrawn' => 0];
    $total_applications = 0;
    while ($row = $applications->fetch_assoc()) {
        $stats[$row['status']]++;
        $total_applications++;
    }
    $applications->data_seek(0); // Reset pointer again for display
    ?>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $stats['pending'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $stats['accepted'] ?></div>
                <div class="stat-label">Accepted</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="bi bi-x-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $stats['rejected'] ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-arrow-counterclockwise"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $stats['withdrawn'] ?></div>
                <div class="stat-label">Withdrawn</div>
            </div>
        </div>
    </div>

    <!-- Applications Section -->
    <div class="section-header">
        <h2 class="section-title">
            <i class="bi bi-list-check"></i>
            Application History
        </h2>
        <div class="section-actions">
            <a href="student-dashboard.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-house"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($total_applications > 0): ?>
        <div class="applications-container">
            <div class="applications-header">
                <div>Position & Company</div>
                <div>Location</div>
                <div>Applied Date</div>
                <div>Duration</div>
                <div>Status</div>
                <div>Resume</div>
                <div>Actions</div>
            </div>
            
            <?php while ($row = $applications->fetch_assoc()): ?>
                <div class="application-row">
                    <!-- Position & Company -->
                    <div>
                        <div class="application-title"><?= htmlspecialchars($row['title']) ?></div>
                        <div class="company-name">
                            <i class="bi bi-building"></i>
                            <?= htmlspecialchars($row['company_name']) ?>
                        </div>
                    </div>
                    
                    <!-- Location -->
                    <div class="location-text d-none d-lg-flex">
                        <i class="bi bi-geo-alt"></i>
                        <?= htmlspecialchars($row['location']) ?>
                    </div>
                    
                    <!-- Applied Date -->
                    <div class="date-text d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Applied</span>
                        </div>
                        <?= date('M j, Y', strtotime($row['applied_at'])) ?>
                    </div>
                    
                    <!-- Duration -->
                    <div class="d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Duration</span>
                        </div>
                        <div class="application-meta">
                            <i class="bi bi-clock"></i>
                            <?= htmlspecialchars($row['duration']) ?>
                        </div>
                    </div>
                    
                    <!-- Status -->
                    <div class="d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Status</span>
                        </div>
                        <span class="status-badge <?= $row['status'] ?>">
                            <?= htmlspecialchars(ucfirst($row['status'])) ?>
                        </span>
                    </div>
                    
                    <!-- Resume -->
                    <div class="d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Resume</span>
                        </div>
                        <?php if (!empty($row['resume'])): ?>
                            <?php 
                            $resume_data = json_decode($row['resume'], true);
                            if (is_array($resume_data) && count($resume_data) > 0): ?>
                                <a href="uploads/<?= rawurlencode($resume_data[0]) ?>" target="_blank" 
                                   class="btn-action btn-download" title="Download Resume">
                                    <i class="bi bi-download"></i>
                                </a>
                            <?php else: ?>
                                <a href="uploads/<?= rawurlencode($row['resume']) ?>" target="_blank" 
                                   class="btn-action btn-download" title="Download Resume">
                                    <i class="bi bi-download"></i>
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted" style="font-size: 0.75rem;">N/A</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Actions -->
                    <div class="action-buttons">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Actions</span>
                        </div>
                        <?php if ($row['status'] === 'pending'): ?>
                            <form method="post" action="withdraw-application.php" style="display: inline;" 
                                  onsubmit="return confirm('Are you sure you want to withdraw this application? This action cannot be undone.');">
                                <input type="hidden" name="application_id" value="<?= (int)$row['application_id'] ?>">
                                <button type="submit" class="btn-action btn-withdraw" title="Withdraw Application">
                                    <i class="bi bi-x"></i>
                                </button>
                            </form>
                        <?php elseif ($row['status'] === 'withdrawn'): ?>
                            <span class="status-text muted">
                                <i class="bi bi-x-circle-fill"></i>
                                Withdrawn
                            </span>
                        <?php elseif ($row['status'] === 'accepted'): ?>
                            <span class="status-text success">
                                <i class="bi bi-check-circle-fill"></i>
                                Accepted
                            </span>
                        <?php elseif ($row['status'] === 'rejected'): ?>
                            <span class="status-text danger">
                                <i class="bi bi-x-circle-fill"></i>
                                Rejected
                            </span>
                        <?php else: ?>
                            <span class="status-text muted">—</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Mobile-only sections -->
                    <div class="d-lg-none mt-2">
                        <div class="row-section">
                            <span class="section-label">Location</span>
                            <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($row['location']) ?></span>
                        </div>
                        <div class="row-section">
                            <span class="section-label">Applied</span>
                            <span><?= date('M j, Y', strtotime($row['applied_at'])) ?></span>
                        </div>
                        <div class="row-section">
                            <span class="section-label">Duration</span>
                            <span><i class="bi bi-clock"></i> <?= htmlspecialchars($row['duration']) ?></span>
                        </div>
                        <div class="row-section">
                            <span class="section-label">Status</span>
                            <span class="status-badge <?= $row['status'] ?>">
                                <?= htmlspecialchars(ucfirst($row['status'])) ?>
                            </span>
                        </div>
                        <div class="row-section">
                            <span class="section-label">Resume</span>
                            <?php if (!empty($row['resume'])): ?>
                                <?php 
                                $resume_data = json_decode($row['resume'], true);
                                if (is_array($resume_data) && count($resume_data) > 0): ?>
                                    <a href="uploads/<?= rawurlencode($resume_data[0]) ?>" target="_blank" 
                                       class="btn-action btn-download" title="Download Resume">
                                        <i class="bi bi-download"></i>
                                    </a>
                                <?php else: ?>
                                    <a href="uploads/<?= rawurlencode($row['resume']) ?>" target="_blank" 
                                       class="btn-action btn-download" title="Download Resume">
                                        <i class="bi bi-download"></i>
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 0.75rem;">N/A</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
    <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="bi bi-inbox"></i>
            </div>
            <h3 class="empty-state-title">No Applications Yet</h3>
            <p class="empty-state-text">
                You haven't applied to any internships yet. Start exploring opportunities and submit your first application!
            </p>
            <a href="student-dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-search"></i> Browse Internships
            </a>
        </div>
    <?php endif; ?>

    <!-- Dummy Content Section -->
    <div class="dummy-content">
        <h5>
            <i class="bi bi-lightbulb text-warning"></i>
            Application Tips & Insights
        </h5>
        <p>
            Your application journey is unique, and every step brings you closer to your ideal internship. Here are some 
            insights to help you succeed: <span class="highlight">Customize your cover letter</span> for each position 
            to show genuine interest. <span class="highlight">Keep your resume updated</span> with recent projects and 
            skills. Most importantly, <span class="highlight">follow up professionally</span> but don't be pushy.
        </p>
        <p>
            Remember that rejection is part of the process and often leads to better opportunities. Each application 
            is a learning experience that improves your chances for the next one. Stay persistent, stay positive, 
            and keep refining your approach based on feedback and results.
        </p>
        <p>
            <strong>Pro tip:</strong> Applications with personalized cover letters have a 40% higher acceptance rate 
            than generic ones. Take the extra time to research the company and role-specific requirements. Your future 
            self will thank you for the effort!
        </p>
    </div>
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
<?php include 'includes/footer.php'; ?>
</body>
</html>