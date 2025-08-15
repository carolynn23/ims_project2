<?php
// includes/sidebar.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/role-menu.php';

$role = $_SESSION['role'] ?? 'guest';
$menus = $ROLE_MENUS[$role] ?? [];
?>
<aside class="bg-dark text-white vh-100 p-3"
       style="width:250px;position:fixed;top:0;left:0;z-index:1030;">
  <div class="d-flex flex-column h-100">
    <h4 class="mb-4 text-white"><?= htmlspecialchars(ucfirst($role)) ?></h4>

    <?php foreach ($menus as $item): ?>
      <?php
        $active = is_active_page($item['file']);
        $activeStyle = $active ? 'background:#0d6efd;padding:8px 12px;border-radius:8px;' : '';
      ?>
      <a href="<?= htmlspecialchars($item['file']) ?>"
         class="d-block mb-2 text-white"
         style="text-decoration:none;<?= $activeStyle ?>">
        <?= $item['icon'] ?> <?= htmlspecialchars($item['label']) ?>
      </a>
    <?php endforeach; ?>

    <div class="mt-auto">
      <?php foreach ($GLOBAL_MENU as $item): ?>
        <a href="<?= htmlspecialchars($item['file']) ?>"
           class="d-block mt-3 <?= $item['class'] ?? '' ?>"
           style="text-decoration:none;color:#fff;">
          <?= $item['icon'] ?> <?= htmlspecialchars($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</aside>
