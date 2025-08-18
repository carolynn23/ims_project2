<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header('Location: login.php'); exit();
}

$user_id = (int)$_SESSION['user_id'];

// which are saved?
$ss = $conn->prepare("SELECT student_id FROM students WHERE user_id=?");
$ss->bind_param("i",$user_id);
$ss->execute();
$student_id = (int)($ss->get_result()->fetch_assoc()['student_id'] ?? 0);

$saved = [];
if ($student_id) {
  $sx = $conn->prepare("SELECT internship_id FROM saved_internships WHERE student_id=?");
  $sx->bind_param("i",$student_id);
  $sx->execute();
  $res = $sx->get_result();
  while ($r = $res->fetch_assoc()) $saved[(int)$r['internship_id']] = true;
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

$sql .= " ORDER BY i.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$list = $stmt->get_result();
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

    /* Main Content Area */
    .main-content {
      margin-left: 260px;
      padding: 2rem;
      min-height: 100vh;
      transition: var(--transition);
    }

    /* Page Header */
    .page-header {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
      border-radius: var(--border-radius-lg);
      padding: 2rem;
      margin-bottom: 2rem;
      color: white;
      position: relative;
      overflow: hidden;
      box-shadow: var(--shadow-lg);
    }

    .page-header::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 100px;
      height: 100px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      animation: float 6s ease-in-out infinite;
    }

    .page-header::after {
      content: '';
      position: absolute;
      bottom: -30%;
      left: -5%;
      width: 80px;
      height: 80px;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 50%;
      animation: float 8s ease-in-out infinite reverse;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-15px) rotate(180deg); }
    }

    .page-header-content {
      position: relative;
      z-index: 2;
    }

    .page-title {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .page-subtitle {
      font-size: 1rem;
      opacity: 0.9;
      font-weight: 400;
    }

    .stats-row {
      margin-top: 1.5rem;
      position: relative;
      z-index: 2;
    }

    .stat-item {
      text-align: center;
    }

    .stat-number {
      font-size: 1.5rem;
      font-weight: 700;
      display: block;
    }

    .stat-label {
      font-size: 0.875rem;
      opacity: 0.8;
    }

    /* Search Section */
    .search-section {
      background: var(--card-bg);
      border-radius: var(--border-radius-lg);
      padding: 1.5rem;
      margin-bottom: 2rem;
      box-shadow: var(--shadow-sm);
      border: 1px solid var(--border-color);
    }

    .search-section h5 {
      color: var(--text-primary);
      font-weight: 600;
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .form-control, .form-select {
      border: 1px solid var(--border-color);
      border-radius: var(--border-radius);
      padding: 0.75rem 1rem;
      font-size: 0.9375rem;
      transition: var(--transition);
      background-color: var(--light-color);
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(105, 108, 255, 0.15);
      background-color: white;
    }

    .btn-search {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
      color: white;
      border: none;
      padding: 0.75rem 1.5rem;
      border-radius: var(--border-radius);
      font-weight: 600;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-search:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(105, 108, 255, 0.3);
      color: white;
    }

    /* Results Section */
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
      color: var(--text-secondary);
      font-size: 0.9375rem;
    }

    .view-toggles {
      display: flex;
      gap: 0.5rem;
    }

    .view-toggle {
      padding: 0.5rem;
      border: 1px solid var(--border-color);
      background: white;
      border-radius: var(--border-radius);
      color: var(--text-secondary);
      transition: var(--transition);
    }

    .view-toggle.active,
    .view-toggle:hover {
      background: var(--primary-color);
      color: white;
      border-color: var(--primary-color);
    }

    /* Internship Cards */
    .internship-card {
      background: var(--card-bg);
      border-radius: var(--border-radius-lg);
      border: 1px solid var(--border-color);
      transition: var(--transition);
      height: 100%;
      overflow: hidden;
      position: relative;
    }

    .internship-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
      border-color: var(--primary-color);
    }

    .card-image {
      position: relative;
      height: 160px;
      overflow: hidden;
    }

    .card-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: var(--transition);
    }

    .internship-card:hover .card-image img {
      transform: scale(1.05);
    }

    .save-badge {
      position: absolute;
      top: 0.75rem;
      right: 0.75rem;
      background: rgba(255, 255, 255, 0.95);
      border-radius: 50%;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.125rem;
      backdrop-filter: blur(10px);
      transition: var(--transition);
      z-index: 2;
    }

    .save-badge.saved {
      background: var(--warning-color);
      color: white;
    }

    .type-badge {
      position: absolute;
      bottom: 0.75rem;
      left: 0.75rem;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .type-badge.in-person {
      background: var(--success-color);
      color: white;
    }

    .type-badge.hybrid {
      background: var(--info-color);
      color: white;
    }

    .type-badge.virtual {
      background: var(--warning-color);
      color: white;
    }

    .card-content {
      padding: 1.5rem;
      display: flex;
      flex-direction: column;
      height: calc(100% - 160px);
    }

    .card-title {
      font-size: 1.125rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: var(--dark-color);
      line-height: 1.4;
    }

    .card-company {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: var(--text-secondary);
      font-size: 0.875rem;
      margin-bottom: 1rem;
    }

    .card-description {
      color: var(--text-primary);
      font-size: 0.9375rem;
      line-height: 1.5;
      margin-bottom: 1rem;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      flex-grow: 1;
    }

    .card-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1rem;
      padding-top: 1rem;
      border-top: 1px solid var(--border-color);
    }

    .deadline {
      color: var(--text-secondary);
      font-size: 0.875rem;
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }

    .card-actions {
      display: flex;
      gap: 0.75rem;
    }

    .btn-outline-custom {
      border: 1px solid var(--border-color);
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

    /* Empty State */
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

    /* Responsive Design */
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

    /* Loading Animation */
    .loading {
      opacity: 0.6;
      pointer-events: none;
    }

    .loading::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 20px;
      height: 20px;
      margin: -10px 0 0 -10px;
      border: 2px solid transparent;
      border-top: 2px solid var(--primary-color);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>

<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
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
      <div class="view-toggles">
        <button class="view-toggle active" data-view="grid">
          <i class="bi bi-grid-3x3-gap"></i>
        </button>
        <button class="view-toggle" data-view="list">
          <i class="bi bi-list"></i>
        </button>
      </div>
    </div>

    <?php if ($list->num_rows === 0): ?>
      <div class="empty-state">
        <div class="empty-state-icon">
          <i class="bi bi-search"></i>
        </div>
        <h3 class="empty-state-title">No internships found</h3>
        <p class="empty-state-text">Try adjusting your search filters or explore all available opportunities</p>
      </div>
    <?php else: ?>
      <div class="row g-4" id="internshipGrid">
        <?php while ($row = $list->fetch_assoc()): 
          $internship_id = (int)$row['internship_id'];
          $is_saved = isset($saved[$internship_id]);
        ?>
          <div class="col-md-6 col-lg-4">
            <div class="internship-card">
              <div class="card-image">
                <?php if (!empty($row['poster'])): ?>
                  <img src="uploads/<?= rawurlencode($row['poster']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
                <?php else: ?>
                  <img src="https://placehold.co/600x400/696cff/ffffff?text=<?= urlencode($row['company_name']) ?>" alt="<?= htmlspecialchars($row['title']) ?>">
                <?php endif; ?>
                
                <div class="save-badge <?= $is_saved ? 'saved' : '' ?>">
                  <?= $is_saved ? '★' : '☆' ?>
                </div>
                
                <div class="type-badge <?= $row['type'] ?>">
                  <?= htmlspecialchars(ucfirst($row['type'])) ?>
                </div>
              </div>

              <div class="card-content">
                <h3 class="card-title"><?= htmlspecialchars($row['title']) ?></h3>
                
                <div class="card-company">
                  <i class="bi bi-building"></i>
                  <span><?= htmlspecialchars($row['company_name']) ?></span>
                  <span>•</span>
                  <i class="bi bi-geo-alt"></i>
                  <span><?= htmlspecialchars($row['location']) ?></span>
                </div>

                <p class="card-description"><?= htmlspecialchars($row['description']) ?></p>

                <div class="card-meta">
                  <div class="deadline">
                    <i class="bi bi-calendar-event"></i>
                    <span>Due: <?= date('M j, Y', strtotime($row['deadline'])) ?></span>
                  </div>
                </div>

                <div class="card-actions">
                  <a href="view-internship.php?internship_id=<?= $internship_id ?>" 
                     class="btn-outline-custom">
                    <i class="bi bi-eye"></i> View Details
                  </a>
                  
                  <form method="post" action="toggle-save.php" style="flex: 1;">
                    <input type="hidden" name="internship_id" value="<?= $internship_id ?>">
                    <input type="hidden" name="back" value="student-dashboard.php">
                    <button class="btn-outline-custom btn-save <?= $is_saved ? 'saved' : '' ?>" type="submit">
                      <i class="bi bi-<?= $is_saved ? 'bookmark-fill' : 'bookmark' ?>"></i>
                      <?= $is_saved ? 'Saved' : 'Save' ?>
                    </button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // View toggle functionality
  const viewToggles = document.querySelectorAll('.view-toggle');
  const grid = document.getElementById('internshipGrid');
  
  viewToggles.forEach(toggle => {
    toggle.addEventListener('click', function() {
      viewToggles.forEach(t => t.classList.remove('active'));
      this.classList.add('active');
      
      const view = this.dataset.view;
      if (view === 'list') {
        grid.className = 'row g-3';
        grid.querySelectorAll('.col-md-6').forEach(col => {
          col.className = 'col-12';
        });
      } else {
        grid.className = 'row g-4';
        grid.querySelectorAll('.col-12').forEach(col => {
          col.className = 'col-md-6 col-lg-4';
        });
      }
    });
  });

  // Enhanced form submission
  const searchForm = document.getElementById('searchForm');
  const searchBtn = searchForm.querySelector('.btn-search');
  
  searchForm.addEventListener('submit', function() {
    searchBtn.classList.add('loading');
    searchBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Searching...';
  });

  // Save button animations
  const saveForms = document.querySelectorAll('form[action="toggle-save.php"]');
  saveForms.forEach(form => {
    form.addEventListener('submit', function(e) {
      const button = this.querySelector('.btn-save');
      button.style.transform = 'scale(0.95)';
      setTimeout(() => {
        button.style.transform = 'scale(1)';
      }, 150);
    });
  });

  // Card hover effects
  const cards = document.querySelectorAll('.internship-card');
  cards.forEach(card => {
    card.addEventListener('mouseenter', function() {
      this.style.borderColor = 'var(--primary-color)';
    });
    
    card.addEventListener('mouseleave', function() {
      this.style.borderColor = 'var(--border-color)';
    });
  });

  // Auto-focus search input if empty results
  <?php if ($list->num_rows === 0 && empty($q)): ?>
  document.querySelector('input[name="q"]').focus();
  <?php endif; ?>
});
</script>

</body>
</html>