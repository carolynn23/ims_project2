<?php
session_start();
require_once 'config.php';

// Verify employer login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get employer data
$stmt = $conn->prepare("SELECT employer_id, company_name FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$company_name = $employer['company_name'];

// Handle Accept/Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['application_id'], $_POST['action'])) {
    $application_id = $_POST['application_id'];
    $action = $_POST['action'];

    if (in_array($action, ['accepted', 'rejected'])) {
        $update = $conn->prepare("UPDATE applications SET status = ? WHERE application_id = ?");
        $update->bind_param("si", $action, $application_id);
        $update->execute();
    }
}

// Fetch all applications to employer's internships
$sql = "
    SELECT 
        a.application_id, a.cover_letter, a.status, a.applied_at, a.resume,
        s.full_name, s.email, s.department, s.program, s.gpa,
        i.title AS internship_title
    FROM applications a
    JOIN students s ON a.student_id = s.student_id
    JOIN internships i ON a.internship_id = i.internship_id
    WHERE i.employer_id = ?
    ORDER BY a.applied_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$applications = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Applications - <?= htmlspecialchars($company_name) ?></title>
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
            grid-template-columns: 2fr 1fr 1fr 120px 100px 80px 150px;
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
            grid-template-columns: 2fr 1fr 1fr 120px 100px 80px 150px;
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

        .application-position {
            color: var(--primary-color);
            font-size: 0.75rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .student-name {
            color: var(--text-primary);
            font-size: 0.9375rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .student-meta {
            color: var(--text-secondary);
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .program-text {
            color: var(--text-primary);
            font-size: 0.8125rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .date-text {
            font-size: 0.8125rem;
            color: var(--text-primary);
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

        .btn-accept {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(113, 221, 55, 0.3);
        }

        .btn-accept:hover {
            background: var(--success-color);
            color: white;
        }

        .btn-reject {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(255, 62, 29, 0.3);
        }

        .btn-reject:hover {
            background: var(--danger-color);
            color: white;
        }

        .no-action {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-style: italic;
        }

        /* Cover Letter Modal Trigger */
        .cover-letter-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            font-size: 0.75rem;
            text-decoration: underline;
            cursor: pointer;
            padding: 0;
        }

        .cover-letter-btn:hover {
            color: var(--primary-light);
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
        .management-tips {
            background: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-top: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .tips-title {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .tip-card {
            background: var(--hover-bg);
            border-radius: var(--border-radius);
            padding: 1rem;
            border-left: 3px solid var(--primary-color);
        }

        .tip-card h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9375rem;
        }

        .tip-card p {
            color: var(--text-secondary);
            font-size: 0.8125rem;
            line-height: 1.5;
            margin: 0;
        }

        .highlight {
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
                grid-template-columns: 2fr 1fr 1fr 100px 80px 60px 120px;
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

            .application-position {
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

            .tips-grid {
                grid-template-columns: 1fr;
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
            <i class="bi bi-person-lines-fill welcome-icon"></i>
            <div class="welcome-text">
                <h1>All Applications</h1>
                <p>Review and manage student applications for your internships</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <?php 
    $applications->data_seek(0); // Reset pointer for stats calculation
    $stats = ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'total' => 0];
    while ($row = $applications->fetch_assoc()) {
        $stats[$row['status']]++;
        $stats['total']++;
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
                <div class="stat-label">Pending Review</div>
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
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $stats['total'] ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
        </div>
    </div>

    <!-- Applications Section -->
    <div class="section-header">
        <h2 class="section-title">
            <i class="bi bi-list-check"></i>
            Application Management
        </h2>
        <div class="section-actions">
            <a href="employer-dashboard.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-house"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <?php if ($stats['total'] > 0): ?>
        <div class="applications-container">
            <div class="applications-header">
                <div>Student & Position</div>
                <div>Program</div>
                <div>Email</div>
                <div>Applied Date</div>
                <div>Resume</div>
                <div>Status</div>
                <div>Actions</div>
            </div>
            
            <?php while ($row = $applications->fetch_assoc()): ?>
                <div class="application-row">
                    <!-- Student & Position -->
                    <div>
                        <div class="application-title"><?= htmlspecialchars($row['full_name']) ?></div>
                        <div class="application-position">
                            <i class="bi bi-briefcase"></i>
                            <?= htmlspecialchars($row['internship_title']) ?>
                        </div>
                    </div>
                    
                    <!-- Program -->
                    <div class="program-text d-none d-lg-flex">
                        <i class="bi bi-mortarboard"></i>
                        <?= htmlspecialchars($row['program']) ?>
                        <?php if (!empty($row['department'])): ?>
                            <br><small><?= htmlspecialchars($row['department']) ?></small>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Email -->
                    <div class="d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Email</span>
                        </div>
                        <a href="mailto:<?= htmlspecialchars($row['email']) ?>" 
                           style="color: var(--primary-color); text-decoration: none; font-size: 0.8125rem;">
                            <?= htmlspecialchars($row['email']) ?>
                        </a>
                    </div>
                    
                    <!-- Applied Date -->
                    <div class="date-text d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Applied</span>
                        </div>
                        <?= date('M j, Y', strtotime($row['applied_at'])) ?>
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
                    
                    <!-- Status -->
                    <div class="d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Status</span>
                        </div>
                        <span class="status-badge <?= $row['status'] ?>">
                            <?= htmlspecialchars(ucfirst($row['status'])) ?>
                        </span>
                    </div>
                    
                    <!-- Actions -->
                    <div class="action-buttons">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Actions</span>
                        </div>
                        <?php if ($row['status'] === 'pending'): ?>
                            <form method="POST" style="display: contents;">
                                <input type="hidden" name="application_id" value="<?= $row['application_id'] ?>">
                                <button name="action" value="accepted" class="btn-action btn-accept" 
                                        title="Accept Application"
                                        onclick="return confirm('Accept this application?')">
                                    <i class="bi bi-check"></i>
                                </button>
                                <button name="action" value="rejected" class="btn-action btn-reject" 
                                        title="Reject Application"
                                        onclick="return confirm('Reject this application?')">
                                    <i class="bi bi-x"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="no-action">Processed</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Mobile-only sections -->
                    <div class="d-lg-none mt-2">
                        <div class="row-section">
                            <span class="section-label">Program</span>
                            <span><i class="bi bi-mortarboard"></i> <?= htmlspecialchars($row['program']) ?></span>
                        </div>
                        <div class="row-section">
                            <span class="section-label">Email</span>
                            <a href="mailto:<?= htmlspecialchars($row['email']) ?>" 
                               style="color: var(--primary-color); text-decoration: none;">
                                <?= htmlspecialchars($row['email']) ?>
                            </a>
                        </div>
                        <div class="row-section">
                            <span class="section-label">Applied</span>
                            <span><?= date('M j, Y', strtotime($row['applied_at'])) ?></span>
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
                        <div class="row-section">
                            <span class="section-label">Status</span>
                            <span class="status-badge <?= $row['status'] ?>">
                                <?= htmlspecialchars(ucfirst($row['status'])) ?>
                            </span>
                        </div>
                        <?php if (!empty($row['cover_letter'])): ?>
                        <div class="row-section">
                            <span class="section-label">Cover Letter</span>
                            <button class="cover-letter-btn" onclick="showCoverLetter('<?= htmlspecialchars(addslashes($row['cover_letter'])) ?>')">
                                View Letter
                            </button>
                        </div>
                        <?php endif; ?>
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
                You haven't received any applications yet. Make sure your internship postings are active and visible to attract qualified candidates.
            </p>
            <a href="post-internship.php" class="btn btn-outline-primary">
                <i class="bi bi-plus-circle"></i> Post New Internship
            </a>
        </div>
    <?php endif; ?>

    <!-- Application Management Tips -->
    <div class="management-tips">
        <div class="tips-title">
            <i class="bi bi-lightbulb text-warning"></i>
            Application Review Best Practices
        </div>
        
        <p style="color: var(--text-secondary); margin-bottom: 1rem;">
            Effective application management helps you identify the best candidates while maintaining a positive employer brand. 
            <span class="highlight">Respond promptly</span> to applications and provide <span class="highlight">constructive feedback</span> 
            when possible. Remember that every interaction shapes your company's reputation among students.
        </p>

        <div class="tips-grid">
            <div class="tip-card">
                <h6><i class="bi bi-clock me-2"></i>Timely Response</h6>
                <p>Aim to review applications within 48-72 hours. Quick responses show professionalism and respect for candidates' time.</p>
            </div>
            
            <div class="tip-card">
                <h6><i class="bi bi-person-check me-2"></i>Fair Evaluation</h6>
                <p>Create consistent criteria for evaluation. Consider academic performance, relevant skills, and cultural fit equally.</p>
            </div>
            
            <div class="tip-card">
                <h6><i class="bi bi-chat-dots me-2"></i>Clear Communication</h6>
                <p>Send personalized messages when possible. Explain next steps clearly and provide timeline expectations.</p>
            </div>
            
            <div class="tip-card">
                <h6><i class="bi bi-shield-check me-2"></i>Professional Standards</h6>
                <p>Maintain confidentiality of all application materials. Treat all candidates with respect regardless of decision outcome.</p>
            </div>
        </div>

        <p style="margin-top: 1rem; color: var(--text-secondary); font-size: 0.875rem;">
            <strong>Industry insight:</strong> Companies that provide feedback to rejected candidates see 50% higher application rates 
            for future positions. Building a positive candidate experience attracts top talent and strengthens your employer brand.
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Cover letter modal functionality
function showCoverLetter(coverLetter) {
    alert("Cover Letter:\n\n" + coverLetter);
    // In a real implementation, you'd show this in a proper modal
}

// Form submission confirmation
document.addEventListener('DOMContentLoaded', function() {
    const actionButtons = document.querySelectorAll('button[name="action"]');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const action = this.value;
            const studentName = this.closest('.application-row').querySelector('.application-title').textContent;
            const actionText = action === 'accepted' ? 'accept' : 'reject';
            
            if (!confirm(`Are you sure you want to ${actionText} the application from ${studentName}?`)) {
                e.preventDefault();
            }
        });
    });
});
</script>
</body>
</html>