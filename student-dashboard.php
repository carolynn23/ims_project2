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
            $success_message = "🎉 Application submitted successfully! Your application for \"$internship_name\" has been sent to the employer. You will receive notifications about any updates.";
            break;
        case 'application_withdrawn':
            $success_message = "✅ Application for \"$internship_name\" has been withdrawn successfully.";
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'already_applied':
            $error_message = "⚠️ You have already applied for \"$internship_name\". Check your applications page for status updates.";
            break;
        case 'application_failed':
            $error_message = "❌ Failed to submit your application for \"$internship_name\". Please try again.";
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

    .main-content {
      margin-left: 260px;
      padding: 2rem;
      min-height: 100vh;
      transition: var(--transition);
    }

    .page-header {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
      border-radius: var(--border-radius-lg);
      color: white;
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: var(--shadow-lg);
    }

    .page-title {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .page-subtitle {
      font-size: 1.1rem;
      opacity: 0.9;
      margin-bottom: 2rem;
    }

    .stats-row {
      margin-top: 1.5rem;
    }

    .stat-item {
      text-align: center;
    }

    .stat-number {
      display: block;
      font-size: 2rem;
      font-weight: 700;
      color: white;
    }

    .stat-label {
      font-size: 0.9rem;
      opacity: 0.8;
    }

    .search-section {
      background: var(--card-bg);
      border-radius: var(--border-radius-lg);
      padding: 2rem;
      margin-bottom: 2rem;
      box-shadow: var(--shadow-sm);
      border: 1px solid var(--border-color);
    }

    .search-section h5 {
      color: var(--text-primary);
      font-weight: 600;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .form-control, .form-select {
      border: 2px solid var(--border-color);
      border-radius: var(--border-radius);
      padding: 0.75rem 1rem;
      font-size: 0.95rem;
      transition: var(--transition);
      background-color: var(--light-color);
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.15);
      background-color: white;
    }

    .form-label {
      font-weight: 500;
      color: var(--text-primary);
      margin-bottom: 0.5rem;
    }

    .btn-search {
      background: var(--primary-color);
      border: none;
      color: white;
      font-weight: 600;
      padding: 0.75rem 1.5rem;
      border-radius: var(--border-radius);
      transition: var(--transition);
    }

    .btn-search:hover {
      background: var(--primary-light);
      transform: translateY(-1px);
      color: white;
    }

    .results-section {
      margin-bottom: 2rem;
    }

    .results-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .results-count {
      font-size: 1.1rem;
      color: var(--text-primary);
    }

    .card {
      background: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: var(--border-radius-lg);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
      margin-bottom: 1.5rem;
      overflow: hidden;
    }

    .card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
      border-color: var(--primary-color);
    }

    .card-body {
      padding: 1.5rem;
    }

    .card-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 0.5rem;
      line-height: 1.4;
    }

    .card-company {
      color: var(--primary-color);
      font-weight: 500;
      margin-bottom: 0.75rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .card-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-bottom: 1rem;
      color: var(--text-secondary);
      font-size: 0.9rem;
    }

    .card-meta-item {
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }

    .card-description {
      color: var(--text-secondary);
      font-size: 0.95rem;
      line-height: 1.5;
      margin-bottom: 1.5rem;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .card-actions {
      display: flex;
      gap: 0.75rem;
      justify-content: space-between;
      align-items: center;
    }

    .btn-outline-custom {
      border: 2px solid var(--border-color);
      color: var(--text-primary);
      background: transparent;
      padding: 0.5rem 1rem;
      border-radius: var(--border-radius);
      font-weight: 500;
      transition: var(--transition);
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      flex: 1;
    }

    .btn-outline-custom:hover {
      background: var(--primary-color);
      border-color: var(--primary-color);
      color: white;
      transform: translateY(-1px);
    }

    .btn-save {
      background: var(--warning-color);
      color: white;
      border: none;
    }

    .btn-save:hover {
      background: #e6a200;
      color: white;
    }

    .btn-save.saved {
      background: var(--secondary-color);
    }

    .btn-save.saved:hover {
      background: #7480911a;
      color: var(--secondary-color);
    }

    .empty-state {
      text-align: center;
      padding: 3rem 2rem;
      background: var(--card-bg);
      border-radius: var(--border-radius-lg);
      border: 1px solid var(--border-color);
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
    }

    .alert {
      border: none;
      border-radius: 10px;
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      font-weight: 500;
      border-left: 4px solid;
    }

    .alert-success {
      background-color: rgba(113, 221, 55, 0.1);
      color: #047857;
      border-left-color: #10b981;
    }

    .alert-danger {
      background-color: rgba(239, 68, 68, 0.1);
      color: #dc2626;
      border-left-color: #ef4444;
    }

    .alert-warning {
      background-color: rgba(245, 158, 11, 0.1);
      color: #d97706;
      border-left-color: #f59e0b;
    }

    .alert-info {
      background-color: rgba(59, 130, 246, 0.1);
      color: #1d4ed8;
      border-left-color: #3b82f6;
    }

    /* Auto-dismiss animation */
    .alert.auto-dismiss {
      animation: fadeInOut 5s ease-in-out forwards;
    }

    @keyframes fadeInOut {
      0% { opacity: 0; transform: translateY(-10px); }
      10%, 90% { opacity: 1; transform: translateY(0); }
      100% { opacity: 0; transform: translateY(-10px); }
    }

    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        padding: 1rem;
      }

      .page-header {
        padding: 1.5rem;
      }

      .page-title {
        font-size: 1.5rem;
      }

      .search-section {
        padding: 1rem;
      }

      .results-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }

      .card-actions {
        flex-direction: column;
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
    <div class="alert alert-success alert-dismissible fade show auto-dismiss" role="alert">
      <i class="bi bi-check-circle me-2"></i>
      <?= $success_message ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if ($error_message): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      <i class="bi bi-exclamation-triangle me-2"></i>
      <?= $error_message ?>
      <a href="student-applications.php" class="alert-link ms-2">View Applications</a>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Page Header -->
  <div class="page-header">
    <div class="page-header-content">
      <h1 class="page-title">🎯 Discover Your Next Internship</h1>
      <p class="page-subtitle">Explore opportunities that match your career goals and aspirations</p>
      
      <div class="row stats-row">
        <div class="col-4">
          <div class="stat-item">
            <span class="stat-number"><?= $list->num_rows ?></span>
            <span class="stat-label">Available Positions</span>
          </div>
        </div>
        <div class="col-4">
          <div class="stat-item">
            <span class="stat-number"><?= count($saved) ?></span>
            <span class="stat-label">Saved Internships</span>
          </div>
        </div>
        <div class="col-4">
          <div class="stat-item">
            <span class="stat-number">24/7</span>
            <span class="stat-label">Support Available</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search Section -->
  <div class="search-section">
    <h5><i class="bi bi-search"></i> Find Your Perfect Match</h5>
    <form class="row g-3" method="get" id="searchForm">
      <div class="col-md-5">
        <label class="form-label">Search Keywords</label>
        <input class="form-control" name="q" value="<?= htmlspecialchars($q) ?>" 
               placeholder="Job title, company, or skills...">
      </div>
      <div class="col-md-3">
        <label class="form-label">Location</label>
        <input class="form-control" name="loc" value="<?= htmlspecialchars($loc) ?>" 
               placeholder="City, state, or remote">
      </div>
      <div class="col-md-2">
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
  <div class="results-section">
    <div class="results-header">
      <div class="results-count">
        <strong><?= $list->num_rows ?></strong> internship<?= $list->num_rows !== 1 ? 's' : '' ?> found
      </div>
      <div class="results-actions">
        <a href="student-applications.php" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-list-check"></i> My Applications
        </a>
      </div>
    </div>

    <?php if ($list->num_rows > 0): ?>
      <?php while ($row = $list->fetch_assoc()): 
        $internship_id = (int)$row['internship_id'];
        $is_saved = isset($saved[$internship_id]);
      ?>
        <div class="card">
          <div class="card-body">
            <h5 class="card-title"><?= htmlspecialchars($row['title']) ?></h5>
            
            <div class="card-company">
              <i class="bi bi-building"></i>
              <?= htmlspecialchars($row['company_name']) ?>
            </div>
            
            <div class="card-meta">
              <span class="card-meta-item">
                <i class="bi bi-geo-alt"></i>
                <?= htmlspecialchars($row['location']) ?>
              </span>
              <span class="card-meta-item">
                <i class="bi bi-clock"></i>
                <?= htmlspecialchars($row['duration']) ?>
              </span>
              <span class="card-meta-item">
                <i class="bi bi-calendar"></i>
                Deadline: <?= date('M j, Y', strtotime($row['deadline'])) ?>
              </span>
              <span class="card-meta-item">
                <i class="bi bi-tag"></i>
                <?= htmlspecialchars(ucfirst($row['type'])) ?>
              </span>
            </div>
            
            <p class="card-description">
              <?= htmlspecialchars($row['description']) ?>
            </p>
            
            <div class="card-actions">
              <a href="view-internship.php?internship_id=<?= $internship_id ?>" 
                 class="btn btn-outline-custom">
                <i class="bi bi-eye"></i> View Details
              </a>
              
              <a href="apply-internship.php?internship_id=<?= $internship_id ?>" 
                 class="btn btn-outline-custom">
                <i class="bi bi-send"></i> Apply Now
              </a>
              
              <form method="post" action="toggle-save.php" class="d-inline">
                <input type="hidden" name="internship_id" value="<?= $internship_id ?>">
                <input type="hidden" name="back" value="student-dashboard.php">
                <button class="btn btn-save <?= $is_saved ? 'saved' : '' ?>" type="submit">
                  <i class="bi bi-<?= $is_saved ? 'bookmark-fill' : 'bookmark' ?>"></i>
                  <?= $is_saved ? 'Saved' : 'Save' ?>
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
      
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-state-icon">🔍</div>
        <h3 class="empty-state-title">No internships found</h3>
        <p class="empty-state-text">
          <?php if ($q || $loc || $type): ?>
            Try adjusting your search criteria to find more opportunities.
          <?php else: ?>
            There are no active internship positions at the moment. Check back later for new opportunities!
          <?php endif; ?>
        </p>
        <?php if ($q || $loc || $type): ?>
          <a href="student-dashboard.php" class="btn btn-outline-primary mt-3">
            <i class="bi bi-arrow-clockwise"></i> Clear Filters
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-dismiss success messages after 5 seconds
setTimeout(() => {
  const autoDismissAlerts = document.querySelectorAll('.alert.auto-dismiss');
  autoDismissAlerts.forEach(alert => {
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
</body>
</html>