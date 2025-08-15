<?php
session_start();
require_once 'config.php'; // Assumes this connects to DB

// Only students should access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Fetch all internships and join with employer info
$sql = "SELECT i.*, e.company_name 
        FROM internships i
        JOIN employers e ON i.employer_id = e.employer_id
        ORDER BY i.created_at DESC";

$result = $conn->query($sql);

// Count total internships for stats
$total_internships = $result ? $result->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Available Internships</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px 0;
    }

    .hero-section {
      text-align: center;
      padding: 40px 20px;
      color: white;
      margin-bottom: 30px;
    }

    .hero-section h1 {
      font-size: 3rem;
      font-weight: 700;
      margin-bottom: 10px;
      text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .hero-section p {
      font-size: 1.2rem;
      opacity: 0.9;
      font-weight: 300;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .stats-section {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 20px;
      text-align: center;
      color: white;
      transition: transform 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-5px);
    }

    .stat-number {
      font-size: 2rem;
      font-weight: 700;
    }

    .stat-label {
      opacity: 0.9;
      margin-top: 5px;
    }

    .filters-section {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 30px;
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      align-items: center;
    }

    .search-box {
      flex: 1;
      min-width: 250px;
      position: relative;
    }

    .search-box input {
      width: 100%;
      padding: 12px 40px 12px 16px;
      border: none;
      border-radius: 10px;
      background: white;
      font-size: 16px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .search-box i {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #666;
    }

    .filter-btn {
      padding: 10px 20px;
      background: rgba(255, 255, 255, 0.2);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 10px;
      color: white;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .filter-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }

    .internships-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }

    .internship-card {
      background: white;
      border-radius: 20px;
      padding: 0;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      overflow: hidden;
      position: relative;
      animation: fadeInUp 0.6s ease-out;
    }

    .internship-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #667eea, #764ba2);
    }

    .internship-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .card-header {
      padding: 25px 25px 15px;
      border-bottom: 1px solid #f0f4f8;
    }

    .company-logo {
      width: 50px;
      height: 50px;
      background: linear-gradient(135deg, #667eea, #764ba2);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 600;
      margin-bottom: 15px;
      font-size: 18px;
    }

    .internship-title {
      font-size: 1.4rem;
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 8px;
      line-height: 1.3;
    }

    .company-name {
      color: #667eea;
      font-weight: 500;
      font-size: 1.1rem;
    }

    .card-body {
      padding: 20px 25px;
    }

    .info-item {
      display: flex;
      align-items: center;
      margin-bottom: 12px;
      color: #4a5568;
      font-size: 0.95rem;
    }

    .info-item i {
      width: 20px;
      color: #667eea;
      margin-right: 12px;
    }

    .deadline-badge {
      display: inline-flex;
      align-items: center;
      background: #fef5e7;
      color: #d69e2e;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 500;
      margin-top: 5px;
      gap: 5px;
    }

    .deadline-badge.urgent {
      background: #fed7d7;
      color: #e53e3e;
    }

    .card-footer {
      padding: 20px 25px;
      background: #f8fafc;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .view-btn {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      padding: 12px 24px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .view-btn:hover {
      transform: translateX(3px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
      color: white;
    }

    .bookmark-btn {
      background: none;
      border: 2px solid #e2e8f0;
      color: #a0aec0;
      padding: 12px;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .bookmark-btn:hover {
      border-color: #667eea;
      color: #667eea;
    }

    .no-internships {
      text-align: center;
      padding: 60px 20px;
      background: white;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      animation: fadeInUp 0.6s ease-out;
    }

    .no-internships i {
      font-size: 4rem;
      color: #cbd5e0;
      margin-bottom: 20px;
    }

    .no-internships h3 {
      color: #4a5568;
      margin-bottom: 10px;
      font-size: 1.5rem;
    }

    .no-internships p {
      color: #718096;
      font-size: 1.1rem;
    }

    @media (max-width: 768px) {
      .hero-section h1 {
        font-size: 2rem;
      }
      
      .internships-grid {
        grid-template-columns: 1fr;
      }
      
      .filters-section {
        flex-direction: column;
        align-items: stretch;
      }
      
      .search-box {
        min-width: unset;
      }

      .stats-section {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .results-count {
      color: white;
      margin-bottom: 20px;
      font-size: 1.1rem;
      opacity: 0.9;
    }
  </style>
</head>
<body>
  <div class="hero-section">
    <h1><i class="fas fa-briefcase"></i> Internship Portal</h1>
    <p>Discover amazing opportunities to kickstart your career</p>
  </div>

  <div class="container">
    <!-- Stats Section -->
    <div class="stats-section">
      <div class="stat-card">
        <div class="stat-number"><?= $total_internships ?></div>
        <div class="stat-label">Available Positions</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">50+</div>
        <div class="stat-label">Partner Companies</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">95%</div>
        <div class="stat-label">Success Rate</div>
      </div>
      <div class="stat-card">
        <div class="stat-number">24/7</div>
        <div class="stat-label">Support Available</div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
      <div class="search-box">
        <input type="text" placeholder="Search internships by title or company..." id="searchInput">
        <i class="fas fa-search"></i>
      </div>
      <button class="filter-btn" onclick="filterAll()">
        <i class="fas fa-list"></i> All Fields
      </button>
      <button class="filter-btn" onclick="sortByRecent()">
        <i class="fas fa-clock"></i> Recent
      </button>
      <button class="filter-btn" onclick="filterByLocation()">
        <i class="fas fa-map-marker-alt"></i> Location
      </button>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
      <div class="results-count">
        <i class="fas fa-info-circle"></i> 
        Showing <span id="visible-count"><?= $total_internships ?></span> of <?= $total_internships ?> internships
      </div>

      <div class="internships-grid" id="internshipsGrid">
        <?php while ($row = $result->fetch_assoc()): ?>
          <div class="internship-card" data-title="<?= strtolower(htmlspecialchars($row['title'])) ?>" data-company="<?= strtolower(htmlspecialchars($row['company_name'])) ?>" data-location="<?= strtolower(htmlspecialchars($row['location'])) ?>">
            <div class="card-header">
              <div class="company-logo">
                <?= strtoupper(substr($row['company_name'], 0, 2)) ?>
              </div>
              <h3 class="internship-title"><?= htmlspecialchars($row['title']) ?></h3>
              <div class="company-name"><?= htmlspecialchars($row['company_name']) ?></div>
            </div>
            <div class="card-body">
              <div class="info-item">
                <i class="fas fa-map-marker-alt"></i>
                <span><?= htmlspecialchars($row['location']) ?></span>
              </div>
              <div class="info-item">
                <i class="fas fa-clock"></i>
                <span><?= htmlspecialchars($row['duration']) ?></span>
              </div>
              <div class="info-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Deadline: <?= date('M j, Y', strtotime($row['deadline'])) ?></span>
              </div>
              <?php
              $deadline = new DateTime($row['deadline']);
              $now = new DateTime();
              $diff = $now->diff($deadline);
              $days_left = $diff->days;
              $is_urgent = $days_left <= 7;
              ?>
              <div class="deadline-badge <?= $is_urgent ? 'urgent' : '' ?>">
                <i class="fas fa-<?= $is_urgent ? 'exclamation-triangle' : 'calendar' ?>"></i>
                <?= $days_left ?> days left
              </div>
            </div>
            <div class="card-footer">
              <a href="view-internship.php?id=<?= $row['internship_id'] ?>" class="view-btn">
                View Details <i class="fas fa-arrow-right"></i>
              </a>
              <button class="bookmark-btn" onclick="toggleBookmark(this, <?= $row['internship_id'] ?>)">
                <i class="far fa-bookmark"></i>
              </button>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="no-internships">
        <i class="fas fa-search"></i>
        <h3>No internships available</h3>
        <p>Check back later for new opportunities or contact your advisor for more information.</p>
      </div>
    <?php endif; ?>

    <!-- Hidden no results message for search -->
    <div class="no-internships" style="display: none;" id="noSearchResults">
      <i class="fas fa-search"></i>
      <h3>No internships found</h3>
      <p>Try adjusting your search criteria or check back later for new opportunities.</p>
    </div>
  </div>

  <script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
      const searchTerm = e.target.value.toLowerCase();
      const cards = document.querySelectorAll('.internship-card');
      let visibleCount = 0;

      cards.forEach(card => {
        const title = card.getAttribute('data-title');
        const company = card.getAttribute('data-company');
        const location = card.getAttribute('data-location');
        
        if (title.includes(searchTerm) || company.includes(searchTerm) || location.includes(searchTerm)) {
          card.style.display = 'block';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });

      updateResultsCount(visibleCount);
      toggleNoResults(visibleCount === 0);
    });

    // Filter functions
    function filterAll() {
      const cards = document.querySelectorAll('.internship-card');
      cards.forEach(card => card.style.display = 'block');
      updateResultsCount(cards.length);
      toggleNoResults(false);
      document.getElementById('searchInput').value = '';
    }

    function sortByRecent() {
      // This would typically require server-side sorting
      // For now, just show all and clear search
      filterAll();
    }

    function filterByLocation() {
      // Simple location grouping - you can enhance this
      const searchInput = document.getElementById('searchInput');
      searchInput.focus();
      searchInput.placeholder = 'Enter location to filter...';
    }

    // Bookmark functionality
    function toggleBookmark(button, internshipId) {
      const icon = button.querySelector('i');
      if (icon.classList.contains('far')) {
        icon.classList.remove('far');
        icon.classList.add('fas');
        button.style.color = '#667eea';
        button.style.borderColor = '#667eea';
        
        // Here you could make an AJAX call to save bookmark
        // saveBookmark(internshipId);
      } else {
        icon.classList.remove('fas');
        icon.classList.add('far');
        button.style.color = '#a0aec0';
        button.style.borderColor = '#e2e8f0';
        
        // Here you could make an AJAX call to remove bookmark
        // removeBookmark(internshipId);
      }
    }

    // Update results count
    function updateResultsCount(count) {
      const totalCount = <?= $total_internships ?>;
      document.getElementById('visible-count').textContent = count;
    }

    // Toggle no results message
    function toggleNoResults(show) {
      const grid = document.getElementById('internshipsGrid');
      const noResults = document.getElementById('noSearchResults');
      const resultsCount = document.querySelector('.results-count');
      
      if (show) {
        if (grid) grid.style.display = 'none';
        if (resultsCount) resultsCount.style.display = 'none';
        noResults.style.display = 'block';
      } else {
        if (grid) grid.style.display = 'grid';
        if (resultsCount) resultsCount.style.display = 'block';
        noResults.style.display = 'none';
      }
    }

    // Add stagger animation to cards on page load
    document.addEventListener('DOMContentLoaded', function() {
      const cards = document.querySelectorAll('.internship-card');
      cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
      });
    });

    // Optional: Load bookmarked internships from localStorage or server
    document.addEventListener('DOMContentLoaded', function() {
      // You could load saved bookmarks here
      // loadUserBookmarks();
    });
  </script>
</body>
</html>