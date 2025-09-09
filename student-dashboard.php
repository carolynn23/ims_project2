<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header('Location: login.php'); exit();
}

$user_id = (int)$_SESSION['user_id'];

// Get student info
$ss = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$ss->bind_param("i", $user_id);
$ss->execute();
$student_result = $ss->get_result()->fetch_assoc();
$student_id = (int)($student_result['student_id'] ?? 0);

$saved = [];
if ($student_id) {
  $sx = $conn->prepare("SELECT internship_id FROM saved_internships WHERE student_id=?");
  $sx->bind_param("i", $student_id);
  $sx->execute();
  $res = $sx->get_result();
  while ($r = $res->fetch_assoc()) $saved[(int)$r['internship_id']] = true;
}

// Handle feedback messages
$success_message = '';
$error_message = '';
$internship_name = htmlspecialchars($_GET['internship'] ?? '');

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'application_submitted':
            $success_message = "Application submitted successfully! Your application for \"$internship_name\" has been sent to the employer. You will receive notifications about any updates.";
            break;
        case 'application_withdrawn':
            $success_message = "Application for \"$internship_name\" has been withdrawn successfully.";
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'already_applied':
            $error_message = "You have already applied for \"$internship_name\". Check your applications page for status updates.";
            break;
        case 'application_failed':
            $error_message = "Failed to submit your application for \"$internship_name\". Please try again.";
            break;
    }
}

// filters
$q    = trim($_GET['q']   ?? '');
$loc  = trim($_GET['loc'] ?? '');
$type = trim($_GET['type']?? '');
$today = date('Y-m-d');

// build query
$sql = "
  SELECT i.internship_id, i.title, i.description, i.requirements, i.duration,
         i.location, i.type, i.deadline, i.poster, e.company_name
  FROM internships i
  JOIN employers e ON i.employer_id = e.employer_id
  WHERE i.deadline >= ?
";
$params = [$today]; $types = "s";

if ($q !== '')   { $sql .= " AND (i.title LIKE CONCAT('%',?,'%') OR e.company_name LIKE CONCAT('%',?,'%') OR i.description LIKE CONCAT('%',?,'%'))";
                   $params[]=$q; $params[]=$q; $params[]=$q; $types .="sss"; }
if ($loc !== '') { $sql .= " AND i.location LIKE CONCAT('%',?,'%')"; $params[]=$loc; $types.="s"; }
if ($type!=='')  { $sql .= " AND i.type = ?"; $params[]=$type; $types.="s"; }

$sql .= " ORDER BY i.created_at DESC LIMIT 50";

$list = $conn->prepare($sql);
if (!empty($params)) $list->bind_param($types, ...$params);
$list->execute();
$list = $list->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Dashboard - InternHub</title>
  
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

    /* Compact Search Section */
    .search-section {
      background: var(--card-bg);
      border-radius: var(--border-radius);
      padding: 1rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow);
      border: 1px solid var(--border-color);
    }

    .search-section h6 {
      color: var(--text-primary);
      font-weight: 600;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 1rem;
    }

    .form-control, .form-select {
      border: 1px solid var(--border-color);
      border-radius: var(--border-radius);
      padding: 0.5rem 0.75rem;
      font-size: 0.875rem;
      transition: var(--transition);
      background-color: var(--light-color);
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.1rem rgba(105, 108, 255, 0.15);
      background-color: white;
    }

    .form-label {
      font-weight: 500;
      color: var(--text-primary);
      margin-bottom: 0.25rem;
      font-size: 0.8125rem;
    }

    .btn-search {
      background: var(--primary-color);
      border: none;
      color: white;
      font-weight: 600;
      padding: 0.5rem 1rem;
      border-radius: var(--border-radius);
      transition: var(--transition);
      font-size: 0.8125rem;
    }

    .btn-search:hover {
      background: var(--primary-light);
      transform: translateY(-1px);
      color: white;
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
    }

    .results-count {
      font-size: 0.9375rem;
      color: var(--text-secondary);
    }

    .section-actions {
      display: flex;
      gap: 0.5rem;
    }

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

    /* Tabular Internship Display */
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

    .btn-save {
      background: rgba(255, 180, 0, 0.1);
      color: var(--warning-color);
      border: 1px solid rgba(255, 180, 0, 0.3);
    }

    .btn-save:hover {
      background: var(--warning-color);
      color: white;
    }

    .btn-save.saved {
      background: rgba(113, 221, 55, 0.1);
      color: var(--success-color);
      border-color: rgba(113, 221, 55, 0.3);
    }

    .btn-save.saved:hover {
      background: var(--success-color);
      color: white;
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

    .alert-warning {
      background-color: rgba(255, 180, 0, 0.1);
      color: var(--warning-color);
      border-left-color: var(--warning-color);
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

      .search-section {
        padding: 0.75rem;
      }
    }
  </style>
</head>

<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
  <!-- Success/Error Messages -->
  <?php if ($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i>
      <?= $success_message ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($error_message): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i>
      <?= $error_message ?>
      <a href="student-applications.php" class="alert-link ms-2">View Applications</a>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Welcome Header -->
  <div class="welcome-header">
    <div class="welcome-content">
      <i class="bi bi-search welcome-icon"></i>
      <div class="welcome-text">
        <h1>Discover Your Next Internship</h1>
        <p>Explore opportunities that match your career goals</p>
      </div>
    </div>
  </div>

  <!-- Stats Cards -->
  <div class="stats-container">
    <div class="stat-card">
      <div class="stat-icon primary">
        <i class="bi bi-briefcase"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?= $list->num_rows ?></div>
        <div class="stat-label">Available Positions</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon success">
        <i class="bi bi-bookmark-fill"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number"><?= count($saved) ?></div>
        <div class="stat-label">Saved Internships</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-icon info">
        <i class="bi bi-headset"></i>
      </div>
      <div class="stat-content">
        <div class="stat-number">24/7</div>
        <div class="stat-label">Support Available</div>
      </div>
    </div>
  </div>

  <!-- Search Section -->
  <div class="search-section">
    <h6><i class="bi bi-funnel"></i> Find Your Perfect Match</h6>
    <form class="row g-2" method="get" id="searchForm">
      <div class="col-md-4">
        <label class="form-label">Search Keywords</label>
        <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" 
               placeholder="Job title, company, skills...">
      </div>
      <div class="col-md-3">
        <label class="form-label">Location</label>
        <input class="form-control" name="loc" value="<?= htmlspecialchars($loc) ?>" 
               placeholder="City, state, remote">
      </div>
      <div class="col-md-3">
        <label class="form-label">Work Type</label>
        <select name="type" class="form-select">
          <option value="">All Types</option>
          <option value="in-person" <?= $type==='in-person'?'selected':'' ?>>In-Person</option>
          <option value="hybrid" <?= $type==='hybrid'?'selected':'' ?>>Hybrid</option>
          <option value="virtual" <?= $type==='virtual'?'selected':'' ?>>Remote</option>
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-search w-100" type="submit">
          <i class="bi bi-search"></i> Search
        </button>
      </div>
    </form>
  </div>

  <!-- Results Section -->
  <div class="section-header">
    <div>
      <h2 class="section-title">Available Internships</h2>
      <div class="results-count">
        <strong><?= $list->num_rows ?></strong> internship<?= $list->num_rows !== 1 ? 's' : '' ?> found
      </div>
    </div>
    <div class="section-actions">
      <a href="student-applications.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-list-check"></i> My Applications
      </a>
    </div>
  </div>

  <?php if ($list->num_rows > 0): ?>
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
        $internship_id = (int)$row['internship_id'];
        $is_saved = isset($saved[$internship_id]);
        $days_left = ceil((strtotime($row['deadline']) - time()) / (60 * 60 * 24));
        $badge_class = $days_left <= 3 ? 'danger' : ($days_left <= 7 ? 'warning' : 'success');
      ?>
        <div class="internship-row">
          <!-- Title & Company -->
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
            <?= htmlspecialchars($row['duration']) ?>
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
            <a href="view-internship.php?internship_id=<?= $internship_id ?>" 
               class="btn-action btn-view" title="View Details">
              <i class="bi bi-eye"></i>
            </a>
            
            <a href="apply-internship.php?internship_id=<?= $internship_id ?>" 
               class="btn-action btn-apply" title="Apply Now">
              <i class="bi bi-send"></i>
            </a>
            
            <form method="post" action="toggle-save.php" class="d-inline">
              <input type="hidden" name="internship_id" value="<?= $internship_id ?>">
              <input type="hidden" name="back" value="student-dashboard.php">
              <button class="btn-action btn-save <?= $is_saved ? 'saved' : '' ?>" 
                      type="submit" title="<?= $is_saved ? 'Remove from Saved' : 'Save Internship' ?>">
                <i class="bi bi-<?= $is_saved ? 'bookmark-fill' : 'bookmark' ?>"></i>
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
              <span><?= htmlspecialchars($row['duration']) ?></span>
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
    <div class="empty-state">
      <div class="empty-state-icon">
        <i class="bi bi-search"></i>
      </div>
      <h3 class="empty-state-title">No internships found</h3>
      <p class="empty-state-text">
        <?php if ($q || $loc || $type): ?>
          Try adjusting your search criteria to find more opportunities.
        <?php else: ?>
          There are no active internship positions at the moment. Check back later for new opportunities!
        <?php endif; ?>
      </p>
      <?php if ($q || $loc || $type): ?>
        <a href="student-dashboard.php" class="btn btn-outline-primary">
          <i class="bi bi-arrow-clockwise"></i> Clear Filters
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss success messages after 5 seconds
setTimeout(() => {
  const alerts = document.querySelectorAll('.alert-success');
  alerts.forEach(alert => {
    const bsAlert = new bootstrap.Alert(alert);
    bsAlert.close();
  });
}, 5000);

// Clean URL after displaying messages
if (window.location.search.includes('success=') || window.location.search.includes('error=')) {
  setTimeout(() => {
    const url = new URL(window.location);
    url.searchParams.delete('success');
    url.searchParams.delete('error');
    url.searchParams.delete('internship');
    window.history.replaceState({}, '', url);
  }, 100);
}
</script>
<?php include 'includes/footer.php'; ?>
</body>
</html>