<?php
// Centralized bootstrap delegating to secure_config for PDO/mysqli and security
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

require_once __DIR__ . '/secure_config.php';
?>
