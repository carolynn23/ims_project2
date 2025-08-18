<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') { header('Location: login.php'); exit(); }

$internship_id = (int)($_GET['internship_id'] ?? 0);
if ($internship_id <= 0) { echo "Invalid internship."; exit(); }

$sql = "
  SELECT i.*, e.company_name, e.website
  FROM internships i
  JOIN employers e ON i.employer_id = e.employer_id
  WHERE i.internship_id = ?
";
$st = $conn->prepare($sql);
$st->bind_param("i", $internship_id);
$st->execute();
$intern = $st->get_result()->fetch_assoc();
if (!$intern) { echo "Not found."; exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($intern['title']) ?> — InternHub</title>
  
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

    /* Breadcrumb */
    .breadcrumb-container {
      margin-bottom: 1.5rem;
    }

    .breadcrumb {
      background: transparent;
      padding: 0;
      margin: 0;
    }

    .breadcrumb-item {
      color: var(--text-secondary);
    }

    .breadcrumb-item.active {
      color: var(--text-primary);
      font-weight: 500;
    }

    .breadcrumb-item + .breadcrumb-item::before {
      content: "›";
      color: var(--text-muted);
    }

    .breadcrumb-item a {
      color: var(--primary-color);
      text-decoration: none;
      transition: var(--transition);
    }

    .breadcrumb-item a:hover {
      color: var(--primary-light);
    }

    /* Hero Section */
    .hero-section {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
      border-radius: var(--border-radius-lg);
      padding: 2rem;
      margin-bottom: 2rem;
      color: white;
      position: relative;
      overflow: hidden;
      box-shadow: var(--shadow-lg);
    }

    .hero-section::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -10%;
      width: 150px;
      height: 150px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 50%;
      animation: float 8s ease-in-out infinite;
    }

    .hero-section::after {
      content: '';
      position: absolute;
      bottom: -30%;
      left: -5%;
      width: 100px;
      height: 100px;
      background: rgba(255, 255, 255, 0.08);
      border-radius: 50%;
      animation: float 6s ease-in-out infinite reverse;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(180deg); }
    }

    .hero-content {
      position: relative;
      z-index: 2;
    }

    .back-btn {
      background: rgba(255, 255, 255, 0.2);
      border: 1px solid rgba(255, 255, 255, 0.3);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: var(--border-radius);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
      font-weight: 500;
      transition: var(--transition);
      backdrop-filter: blur(10px);
      margin-bottom: 1rem;
    }

    .back-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      color: white;
      transform: translateX(-2px);
    }

    .hero-title {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .hero-subtitle {
      display: flex;
      align-items: center;
      gap: 1rem;
      font-size: 1.125rem;
      opacity: 0.9;
      margin-bottom: 1.5rem;
    }

    .company-link {
      color: white;
      text-decoration: none;
      border-bottom: 1px dotted rgba(255, 255, 255, 0.5);
      transition: var(--transition);
    }

    .company-link:hover {
      color: white;
      border-bottom-color: white;
    }

    /* Main Content Grid */
    .content-grid {
      display: grid;
      grid-template-columns: 1fr 400px;
      gap: 2rem;
      margin-bottom: 2rem;
    }

    /* Poster Section */
    .poster-card {
      background: var(--card-bg);
      border-radius: var(--border-radius-lg);
      padding: 1.5rem;
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-sm);
      height: fit-content;
      position: sticky;
      top: 2rem;
    }

    .poster-image {
      width: 100%;
      height: 300px;
      object-fit: cover;
      border-radius: var(--border-radius);
      margin-bottom: 1.5rem;
      box-shadow: var(--shadow-md);
    }

    /* Info Cards */
    .info-card {
      background: var(--card-bg);
      border-radius: var(--border-radius-lg);
      padding: 1.5rem;
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-sm);
      margin-bottom: 1.5rem;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .info-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1rem;
      background: var(--hover-bg);
      border-radius: var(--border-radius);
      border: 1px solid var(--border-color);
      transition: var(--transition);
    }

    .info-item:hover {
      border-color: var(--primary-color);
      background: white;
    }

    .info-icon {
      width: 40px;
      height: 40px;
      background: var(--primary-color);
      color: white;
      border-radius: var(--border-radius);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.125rem;
      flex-shrink: 0;
    }

    .info-details h6 {
      margin: 0;
      font-size: 0.875rem;
      color: var(--text-secondary);
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .info-details p {
      margin: 0;
      font-weight: 600;
      color: var(--text-primary);
    }

    /* Action Buttons */
    .action-buttons {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .btn-apply {
      background: linear-gradient(135deg, var(--success-color) 0%, #5cb85c 100%);
      color: white;
      border: none;
      padding: 0.875rem 2rem;
      border-radius: var(--border-radius);
      font-weight: 600;
      font-size: 1rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: var(--transition);
      flex: 1;
      justify-content: center;
      box-shadow: 0 4px 12px rgba(113, 221, 55, 0.3);
    }

    .btn-apply:hover {
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(113, 221, 55, 0.4);
    }

    .btn-save {
      background: white;
      color: var(--warning-color);
      border: 2px solid var(--warning-color);
      padding: 0.875rem 1.5rem;
      border-radius: var(--border-radius);
      font-weight: 600;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }

    .btn-save:hover {
      background: var(--warning-color);
      color: white;
      transform: translateY(-2px);
    }

    /* Content Sections */
    .content-section {
      background: var(--card-bg);
      border-radius: var(--border-radius-lg);
      padding: 2rem;
      border: 1px solid var(--border-color);
      box-shadow: var(--shadow-sm);
      margin-bottom: 2rem;
    }

    .section-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--border-color);
    }

    .section-icon {
      width: 36px;
      height: 36px;
      background: var(--primary-color);
      color: white;
      border-radius: var(--border-radius);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .section-title {
      font-size: 1.375rem;
      font-weight: 600;
      color: var(--dark-color);
      margin: 0;
    }

    .section-content {
      color: var(--text-primary);
      line-height: 1.7;
      font-size: 1rem;
    }

    .section-content pre {
      white-space: pre-wrap;
      font-family: inherit;
      margin: 0;
      background: var(--hover-bg);
      padding: 1.5rem;
      border-radius: var(--border-radius);
      border-left: 4px solid var(--primary-color);
    }

    /* Type Badge */
    .type-badge {
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: inline-block;
    }

    .type-badge.in-person {
      background: rgba(113, 221, 55, 0.1);
      color: var(--success-color);
      border: 1px solid rgba(113, 221, 55, 0.3);
    }

    .type-badge.hybrid {
      background: rgba(3, 195, 236, 0.1);
      color: var(--info-color);
      border: 1px solid rgba(3, 195, 236, 0.3);
    }

    .type-badge.virtual {
      background: rgba(255, 180, 0, 0.1);
      color: var(--warning-color);
      border: 1px solid rgba(255, 180, 0, 0.3);
    }

    /* Deadline Warning */
    .deadline-warning {
      background: rgba(255, 62, 29, 0.1);
      color: var(--danger-color);
      padding: 1rem;
      border-radius: var(--border-radius);
      border-left: 4px solid var(--danger-color);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    /* Responsive Design */
    @media (max-width: 992px) {
      .main-content {
        margin-left: 0;
        padding: 1rem;
      }

      .content-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }

      .poster-card {
        position: static;
      }

      .hero-title {
        font-size: 2rem;
      }

      .action-buttons {
        flex-direction: column;
      }

      .info-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 768px) {
      .hero-section {
        padding: 1.5rem;
      }

      .hero-title {
        font-size: 1.75rem;
      }

      .hero-subtitle {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
      }

      .content-section {
        padding: 1.5rem;
      }
    }

    /* Loading States */
    .btn-loading {
      opacity: 0.7;
      pointer-events: none;
    }

    .btn-loading::after {
      content: '';
      position: absolute;
      width: 16px;
      height: 16px;
      margin: auto;
      border: 2px solid transparent;
      border-top-color: currentColor;
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
  <!-- Breadcrumb -->
  <div class="breadcrumb-container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
          <a href="student-dashboard.php">
            <i class="bi bi-house"></i> Dashboard
          </a>
        </li>
        <li class="breadcrumb-item">
          <a href="student-dashboard.php">Internships</a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">
          <?= htmlspecialchars($intern['title']) ?>
        </li>
      </ol>
    </nav>
  </div>

  <!-- Hero Section -->
  <div class="hero-section">
    <div class="hero-content">
      <a href="student-dashboard.php" class="back-btn">
        <i class="bi bi-arrow-left"></i> Back to Internships
      </a>
      
      <h1 class="hero-title"><?= htmlspecialchars($intern['title']) ?></h1>
      
      <div class="hero-subtitle">
        <span>
          <i class="bi bi-building"></i>
          <?php if (!empty($intern['website'])): ?>
            <a href="<?= htmlspecialchars($intern['website']) ?>" target="_blank" class="company-link">
              <?= htmlspecialchars($intern['company_name']) ?>
            </a>
          <?php else: ?>
            <?= htmlspecialchars($intern['company_name']) ?>
          <?php endif; ?>
        </span>
        <span>•</span>
        <span>
          <i class="bi bi-geo-alt"></i>
          <?= htmlspecialchars($intern['location']) ?>
        </span>
        <span class="type-badge <?= $intern['type'] ?>">
          <?= htmlspecialchars(ucfirst($intern['type'])) ?>
        </span>
      </div>

      <!-- Quick Actions -->
      <div class="action-buttons">
        <a href="apply-internship.php?internship_id=<?= $internship_id ?>" class="btn-apply">
          <i class="bi bi-send"></i>
          Apply Now
        </a>
        <button class="btn-save" onclick="toggleSave()">
          <i class="bi bi-bookmark"></i>
          Save for Later
        </button>
      </div>
    </div>
  </div>

  <!-- Main Content Grid -->
  <div class="content-grid">
    <!-- Main Content -->
    <div class="main-details">
      <!-- Key Information -->
      <div class="info-card">
        <h5 class="mb-3">
          <i class="bi bi-info-circle text-primary me-2"></i>
          Key Information
        </h5>
        
        <?php 
        $deadline = new DateTime($intern['deadline']);
        $today = new DateTime();
        $days_left = $today->diff($deadline)->days;
        $is_urgent = $days_left <= 7;
        ?>
        
        <?php if ($is_urgent): ?>
        <div class="deadline-warning">
          <i class="bi bi-exclamation-triangle"></i>
          <strong>Urgent:</strong> Only <?= $days_left ?> day<?= $days_left !== 1 ? 's' : '' ?> left to apply!
        </div>
        <?php endif; ?>

        <div class="info-grid">
          <div class="info-item">
            <div class="info-icon">
              <i class="bi bi-clock"></i>
            </div>
            <div class="info-details">
              <h6>Duration</h6>
              <p><?= htmlspecialchars($intern['duration']) ?></p>
            </div>
          </div>

          <div class="info-item">
            <div class="info-icon">
              <i class="bi bi-calendar-event"></i>
            </div>
            <div class="info-details">
              <h6>Application Deadline</h6>
              <p><?= $deadline->format('M j, Y') ?></p>
            </div>
          </div>

          <div class="info-item">
            <div class="info-icon">
              <i class="bi bi-briefcase"></i>
            </div>
            <div class="info-details">
              <h6>Work Type</h6>
              <p><?= htmlspecialchars(ucfirst($intern['type'])) ?></p>
            </div>
          </div>

          <div class="info-item">
            <div class="info-icon">
              <i class="bi bi-geo-alt"></i>
            </div>
            <div class="info-details">
              <h6>Location</h6>
              <p><?= htmlspecialchars($intern['location']) ?></p>
            </div>
          </div>
        </div>

        <?php if (!empty($intern['website'])): ?>
        <div class="mt-3">
          <a href="<?= htmlspecialchars($intern['website']) ?>" target="_blank" class="btn btn-outline-primary">
            <i class="bi bi-globe"></i>
            Visit Company Website
          </a>
        </div>
        <?php endif; ?>
      </div>

      <!-- Description Section -->
      <div class="content-section">
        <div class="section-header">
          <div class="section-icon">
            <i class="bi bi-file-text"></i>
          </div>
          <h3 class="section-title">About This Internship</h3>
        </div>
        <div class="section-content">
          <pre><?= htmlspecialchars($intern['description']) ?></pre>
        </div>
      </div>

      <!-- Requirements Section -->
      <div class="content-section">
        <div class="section-header">
          <div class="section-icon">
            <i class="bi bi-list-check"></i>
          </div>
          <h3 class="section-title">Requirements & Qualifications</h3>
        </div>
        <div class="section-content">
          <pre><?= htmlspecialchars($intern['requirements']) ?></pre>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar-content">
      <!-- Poster Card -->
      <div class="poster-card">
        <h6 class="mb-3">
          <i class="bi bi-image text-primary me-2"></i>
          Internship Poster
        </h6>
        <?php if (!empty($intern['poster'])): ?>
          <img src="uploads/<?= htmlspecialchars($intern['poster']) ?>" 
               class="poster-image" 
               alt="<?= htmlspecialchars($intern['title']) ?> poster">
        <?php else: ?>
          <img src="https://placehold.co/400x300/696cff/ffffff?text=<?= urlencode($intern['company_name']) ?>" 
               class="poster-image" 
               alt="<?= htmlspecialchars($intern['title']) ?> poster">
        <?php endif; ?>
        
        <div class="d-grid gap-2">
          <a href="apply-internship.php?internship_id=<?= $internship_id ?>" class="btn btn-primary">
            <i class="bi bi-send me-2"></i>
            Apply for This Position
          </a>
          <button class="btn btn-outline-warning" onclick="toggleSave()">
            <i class="bi bi-bookmark me-2"></i>
            Save Internship
          </button>
        </div>
      </div>

      <!-- Company Info Card -->
      <div class="info-card">
        <h6 class="mb-3">
          <i class="bi bi-building text-primary me-2"></i>
          About <?= htmlspecialchars($intern['company_name']) ?>
        </h6>
        
        <div class="d-flex align-items-center mb-3">
          <div class="flex-shrink-0">
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                 style="width: 48px; height: 48px; font-weight: 600;">
              <?= strtoupper(substr($intern['company_name'], 0, 1)) ?>
            </div>
          </div>
          <div class="flex-grow-1 ms-3">
            <h6 class="mb-1"><?= htmlspecialchars($intern['company_name']) ?></h6>
            <small class="text-muted">Technology Company</small>
          </div>
        </div>

        <?php if (!empty($intern['website'])): ?>
        <a href="<?= htmlspecialchars($intern['website']) ?>" target="_blank" 
           class="btn btn-outline-primary btn-sm w-100">
          <i class="bi bi-globe me-2"></i>
          Company Website
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Apply button click handling
  const applyButtons = document.querySelectorAll('a[href*="apply-internship.php"]');
  applyButtons.forEach(button => {
    button.addEventListener('click', function(e) {
      this.classList.add('btn-loading');
      this.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Processing...';
    });
  });

  // Smooth scroll for internal links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });

  // Add loading state to external links
  document.querySelectorAll('a[target="_blank"]').forEach(link => {
    link.addEventListener('click', function() {
      const icon = this.querySelector('i');
      if (icon) {
        icon.className = 'bi bi-arrow-up-right';
      }
    });
  });
});

// Save/Unsave functionality
function toggleSave() {
  const saveButtons = document.querySelectorAll('.btn-save');
  saveButtons.forEach(button => {
    const icon = button.querySelector('i');
    const text = button.querySelector('span') || button.childNodes[2];
    
    if (icon.classList.contains('bi-bookmark')) {
      icon.className = 'bi bi-bookmark-fill';
      if (text) text.textContent = ' Saved!';
      button.classList.remove('btn-outline-warning');
      button.classList.add('btn-warning', 'text-white');
    } else {
      icon.className = 'bi bi-bookmark';
      if (text) text.textContent = ' Save for Later';
      button.classList.remove('btn-warning', 'text-white');
      button.classList.add('btn-outline-warning');
    }
    
    // Add visual feedback
    button.style.transform = 'scale(0.95)';
    setTimeout(() => {
      button.style.transform = 'scale(1)';
    }, 150);
  });
}

// Auto-scroll to urgent deadline warning
document.addEventListener('DOMContentLoaded', function() {
  const urgentWarning = document.querySelector('.deadline-warning');
  if (urgentWarning) {
    setTimeout(() => {
      urgentWarning.style.animation = 'pulse 2s ease-in-out 3';
    }, 1000);
  }
});
</script>

<style>
@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.02); }
}
</style>

</body>
</html>