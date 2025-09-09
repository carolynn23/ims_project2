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

    /* Main Content - Account for navbar height */
    .main-content {
      margin-left: 260px;
      margin-top: 70px; /* Account for fixed navbar */
      padding: 1rem;
      min-height: calc(100vh - 70px);
      transition: var(--transition);
      max-width: 1000px;
      margin-left: calc(260px + (100vw - 260px - 1000px) / 2);
    }

    /* Compact Header */
    .page-header {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
      border-radius: var(--border-radius);
      color: white;
      padding: 1rem 1.5rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .header-content {
      display: flex;
      align-items: center;
      gap: 1rem;
      flex: 1;
    }

    .header-icon {
      font-size: 1.5rem;
      opacity: 0.9;
    }

    .header-text h1 {
      font-size: 1.25rem;
      font-weight: 600;
      margin: 0;
      line-height: 1.3;
    }

    .header-text p {
      font-size: 0.875rem;
      opacity: 0.85;
      margin: 0;
      font-weight: 400;
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
      font-size: 0.8125rem;
      font-weight: 500;
      transition: var(--transition);
    }

    .back-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      color: white;
      transform: translateX(-2px);
    }

    /* Large Poster Section */
    .poster-section {
      background: var(--card-bg);
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow);
      border: 1px solid var(--border-color);
      text-align: center;
    }

    .poster-container {
      position: relative;
      display: inline-block;
      max-width: 100%;
    }

    .poster-image {
      width: 100%;
      max-width: 800px;
      height: auto;
      min-height: 400px;
      max-height: 600px;
      object-fit: contain;
      border-radius: var(--border-radius);
      box-shadow: var(--shadow-lg);
      border: 1px solid var(--border-color);
      background: #f8f9fa;
    }

    .poster-fallback {
      width: 100%;
      max-width: 800px;
      height: 400px;
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
      border-radius: var(--border-radius);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: white;
      text-align: center;
      box-shadow: var(--shadow-lg);
    }

    .poster-fallback h3 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .poster-fallback p {
      font-size: 1.125rem;
      opacity: 0.9;
      margin-bottom: 1rem;
    }

    .poster-fallback .company-logo {
      width: 80px;
      height: 80px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }

    /* Title and Meta Information */
    .title-section {
      background: var(--card-bg);
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow);
      border: 1px solid var(--border-color);
    }

    .internship-title {
      font-size: 2rem;
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 0.5rem;
      line-height: 1.3;
    }

    .company-info {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }

    .company-name {
      color: var(--primary-color);
      font-size: 1.125rem;
      font-weight: 600;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .company-name:hover {
      color: var(--primary-light);
    }

    .meta-tags {
      display: flex;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      color: var(--text-secondary);
      font-size: 0.9375rem;
      font-weight: 500;
    }

    .type-badge {
      padding: 0.375rem 0.75rem;
      border-radius: 12px;
      font-size: 0.8125rem;
      font-weight: 600;
      text-transform: capitalize;
    }

    .type-badge.in-person {
      background: rgba(105, 108, 255, 0.1);
      color: var(--primary-color);
      border: 1px solid rgba(105, 108, 255, 0.3);
    }

    .type-badge.hybrid {
      background: rgba(3, 195, 236, 0.1);
      color: var(--info-color);
      border: 1px solid rgba(3, 195, 236, 0.3);
    }

    .type-badge.virtual {
      background: rgba(113, 221, 55, 0.1);
      color: var(--success-color);
      border: 1px solid rgba(113, 221, 55, 0.3);
    }

    /* Action Buttons */
    .action-section {
      background: var(--card-bg);
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow);
      border: 1px solid var(--border-color);
    }

    .action-buttons {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: 1rem;
    }

    .btn-apply {
      background: var(--primary-color);
      color: white;
      border: none;
      padding: 1rem 2rem;
      border-radius: var(--border-radius);
      font-weight: 600;
      font-size: 1rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      transition: var(--transition);
      box-shadow: 0 4px 12px rgba(105, 108, 255, 0.3);
    }

    .btn-apply:hover {
      background: var(--primary-light);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(105, 108, 255, 0.4);
    }

    .btn-save {
      background: transparent;
      color: var(--warning-color);
      border: 2px solid var(--warning-color);
      padding: 1rem 1.5rem;
      border-radius: var(--border-radius);
      font-weight: 600;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .btn-save:hover {
      background: var(--warning-color);
      color: white;
      transform: translateY(-2px);
    }

    .btn-save.saved {
      background: var(--success-color);
      border-color: var(--success-color);
      color: white;
    }

    /* Key Information Grid */
    .info-section {
      background: var(--card-bg);
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow);
      border: 1px solid var(--border-color);
    }

    .section-title {
      font-size: 1.125rem;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
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
      transform: translateY(-1px);
    }

    .info-icon {
      width: 40px;
      height: 40px;
      border-radius: var(--border-radius);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.125rem;
      color: white;
      flex-shrink: 0;
    }

    .info-icon.primary { background: var(--primary-color); }
    .info-icon.success { background: var(--success-color); }
    .info-icon.warning { background: var(--warning-color); }
    .info-icon.info { background: var(--info-color); }

    .info-details h6 {
      margin: 0;
      font-size: 0.8125rem;
      color: var(--text-secondary);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.25rem;
    }

    .info-details p {
      margin: 0;
      font-weight: 600;
      color: var(--text-primary);
      font-size: 0.9375rem;
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
      font-weight: 600;
    }

    /* Content Sections */
    .content-section {
      background: var(--card-bg);
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow);
      border: 1px solid var(--border-color);
    }

    .content-text {
      color: var(--text-primary);
      line-height: 1.7;
      font-size: 1rem;
      white-space: pre-wrap;
      background: var(--hover-bg);
      padding: 1.5rem;
      border-radius: var(--border-radius);
      border-left: 4px solid var(--primary-color);
    }

    /* Company Info */
    .company-section {
      background: var(--card-bg);
      border-radius: var(--border-radius);
      padding: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow);
      border: 1px solid var(--border-color);
    }

    .company-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .company-logo {
      width: 60px;
      height: 60px;
      background: var(--primary-color);
      color: white;
      border-radius: var(--border-radius);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 1.5rem;
      flex-shrink: 0;
    }

    .company-details h6 {
      margin: 0;
      font-size: 1.125rem;
      font-weight: 600;
      color: var(--text-primary);
    }

    .company-details p {
      margin: 0;
      color: var(--text-secondary);
      font-size: 0.875rem;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
      .main-content {
        margin-left: 260px;
        max-width: none;
      }
    }

    @media (max-width: 992px) {
      .main-content {
        margin-left: 0;
        margin-top: 70px;
        padding: 0.75rem;
      }

      .internship-title {
        font-size: 1.75rem;
      }

      .company-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
      }

      .meta-tags {
        gap: 0.75rem;
      }

      .action-buttons {
        grid-template-columns: 1fr;
        gap: 0.75rem;
      }

      .info-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
      }
    }

    @media (max-width: 768px) {
      .page-header {
        padding: 0.75rem 1rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
      }

      .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
        width: 100%;
      }

      .poster-section {
        padding: 1rem;
      }

      .poster-image {
        min-height: 300px;
      }

      .poster-fallback {
        height: 300px;
      }

      .poster-fallback h3 {
        font-size: 1.5rem;
      }

      .internship-title {
        font-size: 1.5rem;
      }

      .action-buttons {
        gap: 0.5rem;
      }

      .btn-apply,
      .btn-save {
        padding: 0.75rem 1rem;
        font-size: 0.9375rem;
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

    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.02); }
    }
  </style>
</head>

<body>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="main-content">
  <!-- Compact Header -->
  <div class="page-header">
    <div class="header-content">
      <i class="bi bi-eye header-icon"></i>
      <div class="header-text">
        <h1>Internship Details</h1>
        <p>Review the position and apply if interested</p>
      </div>
    </div>
    <a href="student-dashboard.php" class="back-btn">
      <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>
  </div>

  <!-- Large Poster Section -->
  <div class="poster-section">
    <?php if (!empty($intern['poster'])): ?>
      <div class="poster-container">
        <img src="uploads/<?= htmlspecialchars($intern['poster']) ?>" 
             class="poster-image" 
             alt="<?= htmlspecialchars($intern['title']) ?> poster">
      </div>
    <?php else: ?>
      <div class="poster-fallback">
        <div class="company-logo">
          <?= strtoupper(substr($intern['company_name'], 0, 1)) ?>
        </div>
        <h3><?= htmlspecialchars($intern['title']) ?></h3>
        <p><?= htmlspecialchars($intern['company_name']) ?></p>
        <div class="type-badge <?= $intern['type'] ?>">
          <?= htmlspecialchars(ucfirst($intern['type'])) ?> Position
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Title and Company Information -->
  <div class="title-section">
    <h1 class="internship-title"><?= htmlspecialchars($intern['title']) ?></h1>
    
    <div class="company-info">
      <?php if (!empty($intern['website'])): ?>
        <a href="<?= htmlspecialchars($intern['website']) ?>" target="_blank" class="company-name">
          <i class="bi bi-building"></i>
          <?= htmlspecialchars($intern['company_name']) ?>
          <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
        </a>
      <?php else: ?>
        <span class="company-name">
          <i class="bi bi-building"></i>
          <?= htmlspecialchars($intern['company_name']) ?>
        </span>
      <?php endif; ?>
    </div>

    <div class="meta-tags">
      <span class="meta-item">
        <i class="bi bi-geo-alt"></i>
        <?= htmlspecialchars($intern['location']) ?>
      </span>
      <span class="meta-item">
        <i class="bi bi-clock"></i>
        <?= htmlspecialchars($intern['duration']) ?>
      </span>
      <span class="meta-item">
        <i class="bi bi-calendar"></i>
        Deadline: <?= date('M j, Y', strtotime($intern['deadline'])) ?>
      </span>
      <span class="type-badge <?= $intern['type'] ?>">
        <?= htmlspecialchars(ucfirst($intern['type'])) ?>
      </span>
    </div>
  </div>

  <!-- Action Buttons -->
  <div class="action-section">
    <div class="action-buttons">
      <a href="apply-internship.php?internship_id=<?= $internship_id ?>" class="btn-apply">
        <i class="bi bi-send"></i>
        Apply for This Position
      </a>
      <button class="btn-save" onclick="toggleSave()">
        <i class="bi bi-bookmark"></i>
        Save for Later
      </button>
    </div>
  </div>

  <!-- Key Information -->
  <div class="info-section">
    <h3 class="section-title">
      <i class="bi bi-info-circle text-primary"></i>
      Key Information
    </h3>
    
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
        <div class="info-icon primary">
          <i class="bi bi-clock"></i>
        </div>
        <div class="info-details">
          <h6>Duration</h6>
          <p><?= htmlspecialchars($intern['duration']) ?></p>
        </div>
      </div>

      <div class="info-item">
        <div class="info-icon warning">
          <i class="bi bi-calendar-event"></i>
        </div>
        <div class="info-details">
          <h6>Application Deadline</h6>
          <p><?= $deadline->format('M j, Y') ?></p>
        </div>
      </div>

      <div class="info-item">
        <div class="info-icon info">
          <i class="bi bi-briefcase"></i>
        </div>
        <div class="info-details">
          <h6>Work Type</h6>
          <p><?= htmlspecialchars(ucfirst($intern['type'])) ?></p>
        </div>
      </div>

      <div class="info-item">
        <div class="info-icon success">
          <i class="bi bi-geo-alt"></i>
        </div>
        <div class="info-details">
          <h6>Location</h6>
          <p><?= htmlspecialchars($intern['location']) ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Company Information -->
  <div class="company-section">
    <h3 class="section-title">
      <i class="bi bi-building text-primary"></i>
      About <?= htmlspecialchars($intern['company_name']) ?>
    </h3>
    
    <div class="company-header">
      <div class="company-logo">
        <?= strtoupper(substr($intern['company_name'], 0, 1)) ?>
      </div>
      <div class="company-details">
        <h6><?= htmlspecialchars($intern['company_name']) ?></h6>
        <p>Technology Company</p>
      </div>
    </div>

    <?php if (!empty($intern['website'])): ?>
    <a href="<?= htmlspecialchars($intern['website']) ?>" target="_blank" 
       class="btn btn-outline-primary">
      <i class="bi bi-globe me-2"></i>
      Visit Company Website
      <i class="bi bi-box-arrow-up-right ms-2"></i>
    </a>
    <?php endif; ?>
  </div>

  <!-- Description Section -->
  <div class="content-section">
    <h3 class="section-title">
      <i class="bi bi-file-text text-primary"></i>
      About This Internship
    </h3>
    <div class="content-text">
      <?= htmlspecialchars($intern['description']) ?>
    </div>
  </div>

  <!-- Requirements Section -->
  <div class="content-section">
    <h3 class="section-title">
      <i class="bi bi-list-check text-primary"></i>
      Requirements & Qualifications
    </h3>
    <div class="content-text">
      <?= htmlspecialchars($intern['requirements']) ?>
    </div>
  </div>

  <!-- Final Action Section -->
  <div class="action-section">
    <div class="action-buttons">
      <a href="apply-internship.php?internship_id=<?= $internship_id ?>" class="btn-apply">
        <i class="bi bi-send"></i>
        Submit Your Application
      </a>
      <button class="btn-save" onclick="toggleSave()">
        <i class="bi bi-bookmark"></i>
        Save This Internship
      </button>
    </div>
  </div>
</div>

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

  // Auto-scroll to urgent deadline warning
  const urgentWarning = document.querySelector('.deadline-warning');
  if (urgentWarning) {
    setTimeout(() => {
      urgentWarning.style.animation = 'pulse 2s ease-in-out 3';
    }, 1000);
  }
});

// Save/Unsave functionality
function toggleSave() {
  const saveButtons = document.querySelectorAll('.btn-save');
  saveButtons.forEach(button => {
    const icon = button.querySelector('i');
    const textNode = button.childNodes[button.childNodes.length - 1];
    
    if (icon.classList.contains('bi-bookmark')) {
      icon.className = 'bi bi-bookmark-fill';
      textNode.textContent = ' Saved!';
      button.classList.add('saved');
    } else {
      icon.className = 'bi bi-bookmark';
      textNode.textContent = ' Save for Later';
      button.classList.remove('saved');
    }
    
    // Add visual feedback
    button.style.transform = 'scale(0.95)';
    setTimeout(() => {
      button.style.transform = 'scale(1)';
    }, 150);
  });
}
</script>

</body>
</html>