<?php
// includes/role-menu.php

if (session_status() === PHP_SESSION_NONE) session_start();

function is_active_page(string $file): bool {
  $current = basename($_SERVER['PHP_SELF'] ?? '');
  return strcasecmp($current, $file) === 0;
}

/**
 * Define the menu for each role.
 * All links assume files are in the base directory.
 * Adjust the filenames below to match your actual pages.
 */
$ROLE_MENUS = [
  'student' => [
    ['label' => 'Dashboard',       'icon' => '🏠', 'file' => 'student-dashboard.php'],
    ['label' => 'My Applications', 'icon' => '📄', 'file' => 'student-applications.php'],
    ['label' => 'Notifications',   'icon' => '🔔', 'file' => 'student-notifications.php'],
    ['label' => 'Profile',         'icon' => '👤', 'file' => 'student-profile.php'],
    ['label'=>'Saved','icon'=>'⭐','file'=>'saved.php'],

  ],

  'employer' => [
    ['label' => 'Dashboard',        'icon' => '🏢', 'file' => 'employer-dashboard.php'],
    ['label' => 'Post Internship',  'icon' => '📢', 'file' => 'post-internship.php'],
    ['label' => 'All Applications', 'icon' => '📋', 'file' => 'view-all-applications.php'],
    ['label' => 'Notifications',    'icon' => '🔔', 'file' => 'notifications.php'],
    ['label' => 'Feedback',         'icon' => '💬', 'file' => 'feedback.php'],
    ['label' => 'Edit Profile',     'icon' => '✏️', 'file' => 'edit-employer-profile.php'],
  ],

  'alumni' => [
    ['label' => 'Dashboard',     'icon' => '🎓', 'file' => 'alumni-dashboard.php'],
    ['label' => 'Mentorship',    'icon' => '🤝', 'file' => 'mentorship.php'],
    ['label' => 'Notifications', 'icon' => '🔔', 'file' => 'notifications-alumni.php'],
    ['label' => 'Profile',       'icon' => '👤', 'file' => 'profile-alumni.php'],
  ],
];

// Show to everyone (at bottom of sidebar)
$GLOBAL_MENU = [
  ['label' => 'Logout', 'icon' => '🚪', 'file' => 'logout.php', 'class' => 'text-danger'],
];
