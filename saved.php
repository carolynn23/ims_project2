<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit(); }

$u = (int)$_SESSION['user_id'];
// get student_id
$s = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$s->bind_param("i",$u);
$s->execute();
$student_id = (int)($s->get_result()->fetch_assoc()['student_id'] ?? 0);

$sql = "
  SELECT i.internship_id, i.title, i.description, i.poster, i.location, i.type, i.deadline, i.duration, e.company_name
  FROM saved_internships s
  JOIN internships i ON s.internship_id = i.internship_id
  JOIN employers e   ON i.employer_id   = e.employer_id
  WHERE s.student_id = ?
  ORDER BY s.saved_at DESC
";
$q = $conn->prepare($sql);
$q->bind_param("i",$student_id);
$q->execute();
$list = $q->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Internships - Student Portal</title>
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

        /* Tabular Internships Display */
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
            grid-template-columns: 2fr 1fr 120px 100px 80px 200px;
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
            grid-template-columns: 2fr 1fr 120px 100px 80px 200px;
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
            line-height: 1.3;
        }

        .internship-company {
            color: var(--primary-color);
            font-size: 0.75rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .internship-location {
            color: var(--text-primary);
            font-size: 0.8125rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
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

        .type-badge.in-person {
            background: rgba(105, 108, 255, 0.1);
            color: var(--primary-color);
        }

        .type-badge.hybrid {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
        }

        .type-badge.virtual {
            background: rgba(113, 221, 55, 0.1);
            color: var(--success-color);
        }

        /* Duration Badge */
        .duration-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.6875rem;
            font-weight: 600;
            background: rgba(255, 180, 0, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(255, 180, 0, 0.3);
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

        .btn-view {
            background: rgba(3, 195, 236, 0.1);
            color: var(--info-color);
            border: 1px solid rgba(3, 195, 236, 0.3);
        }

        .btn-view:hover {
            background: var(--info-color);
            color: white;
        }

        .btn-apply {
            background: rgba(105, 108, 255, 0.1);
            color: var(--primary-color);
            border: 1px solid rgba(105, 108, 255, 0.3);
        }

        .btn-apply:hover {
            background: var(--primary-color);
            color: white;
        }

        .btn-unsave {
            background: rgba(255, 62, 29, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(255, 62, 29, 0.3);
        }

        .btn-unsave:hover {
            background: var(--danger-color);
            color: white;
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

        /* Responsive Design */
        @media (max-width: 1200px) {
            .internships-header,
            .internship-row {
                grid-template-columns: 2fr 1fr 100px 80px 60px 150px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                margin-top: 70px;
                padding: 0.75rem;
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

            .internship-company {
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
            <i class="bi bi-bookmark-heart welcome-icon"></i>
            <div class="welcome-text">
                <h1>Saved Internships</h1>
                <p>Your curated list of interesting opportunities</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <?php 
    $total_saved = $list->num_rows;
    $list->data_seek(0); // Reset pointer for stats calculation
    $urgent_count = 0;
    $remote_count = 0;
    $onsite_count = 0;
    while ($row = $list->fetch_assoc()) {
        $days_left = ceil((strtotime($row['deadline']) - time()) / (60 * 60 * 24));
        if ($days_left <= 7) $urgent_count++;
        if (strtolower($row['type']) === 'virtual') $remote_count++;
        if (strtolower($row['type']) === 'in-person') $onsite_count++;
    }
    $list->data_seek(0); // Reset pointer again for display
    ?>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="bi bi-bookmark-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $total_saved ?></div>
                <div class="stat-label">Total Saved</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="bi bi-clock-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $urgent_count ?></div>
                <div class="stat-label">Urgent Deadlines</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="bi bi-house-fill"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $remote_count ?></div>
                <div class="stat-label">Remote Positions</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="bi bi-building"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?= $onsite_count ?></div>
                <div class="stat-label">On-site Positions</div>
            </div>
        </div>
    </div>

    <!-- Internships Section -->
    <div class="section-header">
        <h2 class="section-title">
            <i class="bi bi-heart-fill text-danger"></i>
            Your Saved Opportunities
        </h2>
        <div class="section-actions">
            <a href="student-dashboard.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-search"></i> Find More
            </a>
        </div>
    </div>

    <?php if ($total_saved > 0): ?>
        <div class="internships-container">
            <div class="internships-header">
                <div>Position & Company</div>
                <div>Location</div>
                <div>Duration</div>
                <div>Deadline</div>
                <div>Type</div>
                <div>Actions</div>
            </div>
            
            <?php while ($row = $list->fetch_assoc()): 
                $days_left = ceil((strtotime($row['deadline']) - time()) / (60 * 60 * 24));
                $badge_class = $days_left <= 3 ? 'danger' : ($days_left <= 7 ? 'warning' : 'success');
            ?>
                <div class="internship-row">
                    <!-- Position & Company -->
                    <div>
                        <div class="internship-title"><?= htmlspecialchars($row['title']) ?></div>
                        <div class="internship-company">
                            <i class="bi bi-building"></i>
                            <?= htmlspecialchars($row['company_name']) ?>
                        </div>
                    </div>
                    
                    <!-- Location -->
                    <div class="internship-location d-none d-lg-flex">
                        <i class="bi bi-geo-alt"></i>
                        <?= htmlspecialchars($row['location']) ?>
                    </div>
                    
                    <!-- Duration -->
                    <div class="d-none d-lg-block">
                        <div class="row-section d-lg-none">
                            <span class="section-label">Duration</span>
                        </div>
                        <?php if (!empty($row['duration'])): ?>
                            <span class="duration-badge"><?= htmlspecialchars($row['duration']) ?></span>
                        <?php else: ?>
                            <span class="text-muted" style="font-size: 0.75rem;">Not specified</span>
                        <?php endif; ?>
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
                        <a href="view-internship.php?internship_id=<?= (int)$row['internship_id'] ?>" 
                           class="btn-action btn-view" title="View Details">
                            <i class="bi bi-eye"></i>
                        </a>
                        
                        <a href="apply-internship.php?internship_id=<?= (int)$row['internship_id'] ?>" 
                           class="btn-action btn-apply" title="Apply Now">
                            <i class="bi bi-send"></i>
                        </a>
                        
                        <form method="post" action="toggle-save.php" class="d-inline">
                            <input type="hidden" name="internship_id" value="<?= (int)$row['internship_id'] ?>">
                            <input type="hidden" name="back" value="saved-internships.php">
                            <button class="btn-action btn-unsave" type="submit" 
                                    title="Remove from Saved"
                                    onclick="return confirm('Remove this internship from your saved list?')">
                                <i class="bi bi-bookmark-x"></i>
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
                            <span class="section-label">Duration</span>
                            <?php if (!empty($row['duration'])): ?>
                                <span class="duration-badge"><?= htmlspecialchars($row['duration']) ?></span>
                            <?php else: ?>
                                <span class="text-muted" style="font-size: 0.75rem;">Not specified</span>
                            <?php endif; ?>
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
                <i class="bi bi-bookmark"></i>
            </div>
            <h3 class="empty-state-title">No Saved Internships Yet</h3>
            <p class="empty-state-text">
                Start building your personalized collection by saving internships that interest you. 
                Click the bookmark icon on any internship listing to add it here.
            </p>
            <a href="student-dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-search"></i> Browse Internships
            </a>
        </div>
    <?php endif; ?>

    <!-- Dummy Content Section -->
    <div class="dummy-content">
        <h5>
            <i class="bi bi-stars text-warning"></i>
            Smart Bookmarking Tips
        </h5>
        <p>
            Your saved internships list is more than just a wishlist—it's your strategic career planning tool. 
            <span class="highlight">Organize by priority</span> and set personal deadlines to review each opportunity. 
            <span class="highlight">Research thoroughly</span> before applying, and don't let great opportunities slip away 
            due to procrastination. <span class="highlight">Quality over quantity</span>—it's better to have 10 well-researched 
            positions than 50 random bookmarks.
        </p>

        <div class="tips-grid">
            <div class="tip-card">
                <h6><i class="bi bi-lightbulb me-2"></i>Regular Review</h6>
                <p>Check your saved list weekly. Remove positions that no longer interest you and prioritize those with approaching deadlines.</p>
            </div>
            
            <div class="tip-card">
                <h6><i class="bi bi-graph-up me-2"></i>Track Trends</h6>
                <p>Notice patterns in your saved positions. This helps identify your true career interests and target companies.</p>
            </div>
            
            <div class="tip-card">
                <h6><i class="bi bi-calendar-check me-2"></i>Set Reminders</h6>
                <p>Create calendar alerts for application deadlines. Most students apply in the last few days, so early applications stand out.</p>
            </div>
            
            <div class="tip-card">
                <h6><i class="bi bi-people me-2"></i>Network Smart</h6>
                <p>Research employees at saved companies on LinkedIn. A warm introduction can significantly boost your application success rate.</p>
            </div>
        </div>

        <p>
            <strong>Remember:</strong> The average successful student applies to 15-20 carefully selected positions rather than 
            50+ random applications. Your saved list should reflect this quality-focused approach. Each bookmark should represent 
            a genuine interest and strategic career move.
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'includes/footer.php'; ?>
</body>
</html>
