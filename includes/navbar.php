<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$displayName = $_SESSION['display_name'] ?? 'User';
$userRole = $_SESSION['role'] ?? 'user';
?>

<!-- Modern Sneat Style Navbar -->
<nav class="modern-navbar">
  <div class="navbar-container">
    <!-- Left Section -->
    <div class="navbar-left">
      <!-- Mobile Menu Toggle -->
      <button class="mobile-menu-toggle d-lg-none" id="mobileMenuToggle">
        <i class="bi bi-list"></i>
      </button>
      
      <!-- Brand/Logo -->
      <div class="navbar-brand">
        <div class="brand-icon">
          <i class="bi bi-mortarboard"></i>
        </div>
        <div class="brand-text">
          <h5 class="brand-title">InternHub</h5>
          <small class="brand-subtitle"><?= ucfirst($userRole) ?> Portal</small>
        </div>
      </div>
    </div>

    <!-- Right Section -->
    <div class="navbar-right">
      <!-- Quick Actions -->
      <div class="quick-actions">
        <!-- Messages -->
        <div class="action-item">
          <button class="action-btn">
            <i class="bi bi-chat-dots"></i>
            <span class="notification-badge bg-success">2</span>
          </button>
        </div>

        <!-- Settings -->
        <div class="action-item">
          <button class="action-btn">
            <i class="bi bi-gear"></i>
          </button>
        </div>
      </div>

      <!-- User Profile -->
      <div class="user-profile dropdown">
        <button class="profile-btn" data-bs-toggle="dropdown" aria-expanded="false">
          <div class="profile-avatar">
            <img src="https://i.pravatar.cc/40?seed=<?= urlencode($displayName) ?>" alt="<?= htmlspecialchars($displayName) ?>">
            <div class="status-indicator"></div>
          </div>
          <div class="profile-info">
            <div class="profile-name"><?= htmlspecialchars($displayName) ?></div>
            <div class="profile-role"><?= ucfirst($userRole) ?></div>
          </div>
          <i class="bi bi-chevron-down profile-arrow"></i>
        </button>
        
        <div class="dropdown-menu profile-dropdown">
          <div class="profile-header">
            <div class="profile-avatar-large">
              <img src="https://i.pravatar.cc/60?seed=<?= urlencode($displayName) ?>" alt="<?= htmlspecialchars($displayName) ?>">
              <div class="status-indicator-large"></div>
            </div>
            <div class="profile-details">
              <h6 class="profile-name-large"><?= htmlspecialchars($displayName) ?></h6>
              <span class="profile-email"><?= $_SESSION['email'] ?? 'user@example.com' ?></span>
              <span class="profile-role-badge"><?= ucfirst($userRole) ?></span>
            </div>
          </div>
          
          <div class="profile-menu">
            <a href="#" class="profile-menu-item">
              <i class="bi bi-person"></i>
              <span>My Profile</span>
            </a>
            <a href="#" class="profile-menu-item">
              <i class="bi bi-gear"></i>
              <span>Account Settings</span>
            </a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="profile-menu-item text-danger">
              <i class="bi bi-box-arrow-right"></i>
              <span>Sign Out</span>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</nav>

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

/* Main Navbar */
.modern-navbar {
  background: var(--card-bg);
  border-bottom: 1px solid var(--border-color);
  box-shadow: var(--shadow-sm);
  position: fixed;
  top: 0;
  right: 0;
  left: 260px;
  height: 70px;
  z-index: 999;
  transition: var(--transition);
}

.navbar-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 100%;
  padding: 0 1.5rem;
  max-width: 100%;
}

/* Left Section */
.navbar-left {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.mobile-menu-toggle {
  background: none;
  border: none;
  font-size: 1.25rem;
  color: var(--text-primary);
  padding: 0.5rem;
  border-radius: var(--border-radius);
  transition: var(--transition);
}

.mobile-menu-toggle:hover {
  background: var(--hover-bg);
  color: var(--primary-color);
}

.navbar-brand {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
}

.brand-icon {
  width: 36px;
  height: 36px;
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
  color: white;
  border-radius: var(--border-radius);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.125rem;
}

.brand-title {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--dark-color);
  margin: 0;
  line-height: 1.2;
}

.brand-subtitle {
  color: var(--text-secondary);
  font-size: 0.75rem;
  font-weight: 500;
}

/* Right Section */
.navbar-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

/* Quick Actions */
.quick-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.action-item {
  position: relative;
}

.action-btn {
  width: 40px;
  height: 40px;
  background: var(--hover-bg);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-primary);
  font-size: 1.125rem;
  transition: var(--transition);
  position: relative;
}

.action-btn:hover {
  background: white;
  border-color: var(--primary-color);
  color: var(--primary-color);
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.notification-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  background: var(--danger-color);
  color: white;
  border-radius: 10px;
  font-size: 0.6875rem;
  font-weight: 600;
  min-width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid white;
}

.notification-badge.bg-success {
  background: var(--success-color);
}

/* User Profile */
.user-profile {
  position: relative;
}

.profile-btn {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background: none;
  border: none;
  padding: 0.5rem;
  border-radius: var(--border-radius-lg);
  transition: var(--transition);
  text-decoration: none;
  color: inherit;
}

.profile-btn:hover {
  background: var(--hover-bg);
}

.profile-avatar {
  position: relative;
  width: 40px;
  height: 40px;
}

.profile-avatar img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid var(--border-color);
}

.status-indicator {
  position: absolute;
  bottom: 2px;
  right: 2px;
  width: 10px;
  height: 10px;
  background: var(--success-color);
  border: 2px solid white;
  border-radius: 50%;
}

.profile-info {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.profile-name {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
  line-height: 1.2;
}

.profile-role {
  font-size: 0.75rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.profile-arrow {
  color: var(--text-muted);
  font-size: 0.75rem;
  transition: var(--transition);
}

.profile-btn[aria-expanded="true"] .profile-arrow {
  transform: rotate(180deg);
}

/* Dropdown Menus */
.dropdown-menu {
  border: 1px solid var(--border-color);
  box-shadow: var(--shadow-lg);
  border-radius: var(--border-radius-lg);
  padding: 0;
  margin-top: 0.5rem;
  min-width: 280px;
}

/* Profile Dropdown */
.profile-dropdown {
  width: 280px;
}

.profile-header {
  padding: 1.5rem 1.25rem;
  background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
  color: white;
  position: relative;
  overflow: hidden;
}

.profile-header::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -20%;
  width: 100px;
  height: 100px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  animation: float 6s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-10px); }
}

.profile-avatar-large {
  position: relative;
  width: 60px;
  height: 60px;
  margin-bottom: 1rem;
  z-index: 2;
}

.profile-avatar-large img {
  width: 100%;
  height: 100%;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid rgba(255, 255, 255, 0.3);
}

.status-indicator-large {
  position: absolute;
  bottom: 4px;
  right: 4px;
  width: 14px;
  height: 14px;
  background: var(--success-color);
  border: 3px solid white;
  border-radius: 50%;
}

.profile-details {
  position: relative;
  z-index: 2;
}

.profile-name-large {
  font-size: 1.125rem;
  font-weight: 600;
  margin: 0 0 0.25rem 0;
}

.profile-email {
  display: block;
  font-size: 0.8125rem;
  opacity: 0.9;
  margin-bottom: 0.5rem;
}

.profile-role-badge {
  display: inline-block;
  background: rgba(255, 255, 255, 0.2);
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 500;
  backdrop-filter: blur(10px);
}

.profile-menu {
  padding: 0.5rem 0;
}

.profile-menu-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.25rem;
  color: var(--text-primary);
  text-decoration: none;
  transition: var(--transition);
  font-size: 0.9375rem;
}

.profile-menu-item:hover {
  background: var(--hover-bg);
  color: var(--primary-color);
}

.profile-menu-item.text-danger {
  color: var(--danger-color);
}

.profile-menu-item.text-danger:hover {
  background: rgba(255, 62, 29, 0.1);
  color: var(--danger-color);
}

.dropdown-divider {
  height: 1px;
  background: var(--border-color);
  margin: 0.5rem 0;
}

/* Responsive Design */
@media (max-width: 992px) {
  .modern-navbar {
    left: 0;
  }
  
  .profile-info {
    display: none;
  }
}

@media (max-width: 768px) {
  .navbar-container {
    padding: 0 1rem;
  }
  
  .quick-actions {
    gap: 0.25rem;
  }
  
  .action-btn {
    width: 36px;
    height: 36px;
    font-size: 1rem;
  }
  
  .dropdown-menu {
    min-width: 260px;
  }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Mobile menu toggle
  const mobileMenuToggle = document.getElementById('mobileMenuToggle');
  if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener('click', function() {
      const sidebar = document.querySelector('.sidebar');
      if (sidebar) {
        sidebar.classList.toggle('show');
      }
    });
  }
  
  // Auto-hide dropdowns when clicking outside
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
      const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
      openDropdowns.forEach(dropdown => {
        dropdown.classList.remove('show');
      });
    }
  });
});
</script>