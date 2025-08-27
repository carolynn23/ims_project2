<?php
/**
 * Security Monitoring Dashboard
 * Administrative interface for monitoring security events and system health
 */

define('SECURE_ACCESS', true);
session_start();
require_once 'secure_config.php';
require_once 'middleware/auth_middleware.php';

// Require admin authentication
AuthMiddleware::requireAuth('admin');
AuthMiddleware::requirePermission('security_logs');

$user_id = $_SESSION['user_id'];

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'ajax') {
    AuthMiddleware::validateAjaxRequest();
    AuthMiddleware::setAjaxSecurityHeaders();
    
    $ajax_action = sanitize($_GET['ajax_action'] ?? '', 'alphanumeric');
    
    switch ($ajax_action) {
        case 'get_security_stats':
            echo json_encode(getSecurityStats($conn));
            break;
            
        case 'get_recent_events':
            $severity = sanitize($_GET['severity'] ?? '', 'alphanumeric');
            $limit = min(100, (int)($_GET['limit'] ?? 50));
            echo json_encode(getRecentSecurityEvents($conn, $severity, $limit));
            break;
            
        case 'get_failed_logins':
            echo json_encode(getFailedLoginAnalysis($conn));
            break;
            
        case 'get_user_activity':
            $days = min(30, (int)($_GET['days'] ?? 7));
            echo json_encode(getUserActivityStats($conn, $days));
            break;
            
        case 'block_ip':
            $ip = sanitize($_POST['ip'] ?? '', 'string');
            echo json_encode(blockIPAddress($ip, $conn, $user_id));
            break;
            
        case 'clear_rate_limits':
            echo json_encode(clearRateLimits($conn, $user_id));
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

/**
 * Get security statistics
 */
function getSecurityStats($conn) {
    try {
        $stats = [];
        
        // Security events in last 24 hours by severity
        $event_stmt = $conn->prepare("
            SELECT severity, COUNT(*) as count
            FROM security_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY severity
        ");
        $event_stmt->execute();
        $events = $event_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $stats['events_24h'] = [
            'INFO' => 0, 'WARNING' => 0, 'HIGH' => 0, 'CRITICAL' => 0
        ];
        
        foreach ($events as $event) {
            $stats['events_24h'][$event['severity']] = (int)$event['count'];
        }
        
        // Failed login attempts in last hour
        $failed_stmt = $conn->prepare("
            SELECT COUNT(*) as failed_logins
            FROM login_attempts 
            WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) 
            AND success = FALSE
        ");
        $failed_stmt->execute();
        $stats['failed_logins_1h'] = (int)$failed_stmt->get_result()->fetch_assoc()['failed_logins'];
        
        // Active sessions
        $sessions_stmt = $conn->prepare("
            SELECT COUNT(*) as active_sessions
            FROM user_sessions 
            WHERE expires_at > NOW() AND is_active = TRUE
        ");
        $sessions_stmt->execute();
        $stats['active_sessions'] = (int)$sessions_stmt->get_result()->fetch_assoc()['active_sessions'];
        
        // Rate limited IPs
        $rate_limit_stmt = $conn->prepare("
            SELECT COUNT(DISTINCT identifier) as blocked_ips
            FROM rate_limits 
            WHERE created_at > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 HOUR))
        ");
        $rate_limit_stmt->execute();
        $stats['blocked_ips'] = (int)$rate_limit_stmt->get_result()->fetch_assoc()['blocked_ips'];
        
        // System health indicators
        $stats['system_health'] = [
            'disk_usage' => getDiskUsage(),
            'memory_usage' => getMemoryUsage(),
            'database_connections' => getDatabaseConnections($conn)
        ];
        
        return ['success' => true, 'stats' => $stats];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to load statistics'];
    }
}

/**
 * Get recent security events
 */
function getRecentSecurityEvents($conn, $severity_filter = '', $limit = 50) {
    try {
        $query = "
            SELECT sl.log_id, sl.user_id, sl.event_type, sl.details, 
                   sl.severity, sl.ip_address, sl.created_at,
                   u.email, u.role
            FROM security_logs sl
            LEFT JOIN users u ON sl.user_id = u.user_id
            WHERE 1=1
        ";
        
        $params = [];
        $types = "";
        
        if (!empty($severity_filter) && in_array($severity_filter, ['INFO', 'WARNING', 'HIGH', 'CRITICAL'])) {
            $query .= " AND sl.severity = ?";
            $params[] = $severity_filter;
            $types .= "s";
        }
        
        $query .= " ORDER BY sl.created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        return [
            'success' => true, 
            'events' => array_map('formatSecurityEvent', $events)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to load events'];
    }
}

/**
 * Get failed login analysis
 */
function getFailedLoginAnalysis($conn) {
    try {
        // Top IPs with failed attempts
        $ip_stmt = $conn->prepare("
            SELECT ip_address, COUNT(*) as attempts, 
                   MAX(attempted_at) as last_attempt
            FROM login_attempts 
            WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
            AND success = FALSE
            GROUP BY ip_address
            ORDER BY attempts DESC
            LIMIT 10
        ");
        $ip_stmt->execute();
        $top_ips = $ip_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Failed attempts over time (last 24 hours)
        $timeline_stmt = $conn->prepare("
            SELECT DATE_FORMAT(attempted_at, '%Y-%m-%d %H:00:00') as hour,
                   COUNT(*) as attempts
            FROM login_attempts 
            WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
            AND success = FALSE
            GROUP BY hour
            ORDER BY hour
        ");
        $timeline_stmt->execute();
        $timeline = $timeline_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        return [
            'success' => true,
            'top_ips' => $top_ips,
            'timeline' => $timeline
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to load failed login analysis'];
    }
}

/**
 * Get user activity statistics
 */
function getUserActivityStats($conn, $days = 7) {
    try {
        // Daily active users
        $activity_stmt = $conn->prepare("
            SELECT DATE(created_at) as date, COUNT(DISTINCT user_id) as active_users
            FROM security_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            AND event_type = 'LOGIN_SUCCESS'
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $activity_stmt->bind_param("i", $days);
        $activity_stmt->execute();
        $daily_activity = $activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // User roles breakdown
        $roles_stmt = $conn->prepare("
            SELECT u.role, COUNT(*) as count
            FROM users u
            WHERE u.status = 'active'
            GROUP BY u.role
        ");
        $roles_stmt->execute();
        $user_roles = $roles_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        return [
            'success' => true,
            'daily_activity' => $daily_activity,
            'user_roles' => $user_roles
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to load user activity'];
    }
}

/**
 * Block IP address
 */
function blockIPAddress($ip, $conn, $admin_user_id) {
    try {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'error' => 'Invalid IP address'];
        }
        
        // Add to blocked IPs (you may need to create this table)
        $block_stmt = $conn->prepare("
            INSERT INTO blocked_ips (ip_address, blocked_by, blocked_at, reason)
            VALUES (?, ?, NOW(), 'Manual admin block')
        ");
        $block_stmt->bind_param("si", $ip, $admin_user_id);
        $block_stmt->execute();
        
        // Log the action
        log_security_event('IP_BLOCKED', "IP: $ip blocked by admin", $admin_user_id, 'WARNING');
        
        return ['success' => true, 'message' => 'IP address blocked successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to block IP address'];
    }
}

/**
 * Clear rate limits
 */
function clearRateLimits($conn, $admin_user_id) {
    try {
        $clear_stmt = $conn->prepare("DELETE FROM rate_limits WHERE created_at < UNIX_TIMESTAMP(NOW())");
        $clear_stmt->execute();
        $cleared_count = $clear_stmt->affected_rows;
        
        log_security_event('RATE_LIMITS_CLEARED', "Cleared $cleared_count rate limit entries", $admin_user_id, 'INFO');
        
        return ['success' => true, 'message' => "$cleared_count rate limit entries cleared"];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Failed to clear rate limits'];
    }
}

/**
 * Format security event for display
 */
function formatSecurityEvent($event) {
    return [
        'id' => $event['log_id'],
        'timestamp' => $event['created_at'],
        'event_type' => $event['event_type'],
        'severity' => $event['severity'],
        'user_email' => $event['email'] ?: 'Anonymous',
        'user_role' => $event['role'] ?: 'N/A',
        'ip_address' => $event['ip_address'],
        'details' => formatEventDetails($event['event_type'], $event['details']),
        'severity_class' => getSeverityClass($event['severity'])
    ];
}

/**
 * Format event details safely
 */
function formatEventDetails($event_type, $details) {
    $safe_details = htmlspecialchars($details);
    
    // Truncate long details
    if (strlen($safe_details) > 100) {
        $safe_details = substr($safe_details, 0, 100) . '...';
    }
    
    return $safe_details;
}

/**
 * Get CSS class for severity
 */
function getSeverityClass($severity) {
    $classes = [
        'INFO' => 'text-info',
        'WARNING' => 'text-warning',
        'HIGH' => 'text-danger',
        'CRITICAL' => 'text-danger fw-bold'
    ];
    
    return $classes[$severity] ?? 'text-secondary';
}

/**
 * Get system resource usage
 */
function getDiskUsage() {
    $bytes = disk_free_space(".");
    $total = disk_total_space(".");
    return $total > 0 ? round((($total - $bytes) / $total) * 100, 1) : 0;
}

function getMemoryUsage() {
    return round(memory_get_usage() / 1024 / 1024, 1); // MB
}

function getDatabaseConnections($conn) {
    try {
        $result = $conn->query("SHOW STATUS LIKE 'Threads_connected'");
        $row = $result->fetch_assoc();
        return (int)$row['Value'];
    } catch (Exception $e) {
        return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard - <?= htmlspecialchars(APP_NAME) ?></title>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Styles -->
    <style>
        :root {
            --primary-color: #696cff;
            --success-color: #71dd37;
            --warning-color: #ffb400;
            --danger-color: #ff3e1d;
            --info-color: #03c3ec;
            --dark-color: #233446;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .security-header {
            background: linear-gradient(135deg, var(--danger-color) 0%, #c93e1d 100%);
            border-radius: 12px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .security-header::before {
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
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card.critical {
            border-left-color: var(--danger-color);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .stat-card.success {
            border-left-color: var(--success-color);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .card-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .event-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem;
            border-bottom: 1px solid #f8f9fa;
            transition: background-color 0.2s ease;
        }
        
        .event-item:hover {
            background-color: #f8f9fa;
        }
        
        .event-severity {
            width: 8px;
            height: 60px;
            border-radius: 4px;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .severity-info { background-color: var(--info-color); }
        .severity-warning { background-color: var(--warning-color); }
        .severity-high { background-color: var(--danger-color); }
        .severity-critical { background-color: #8b0000; animation: pulse 2s infinite; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .event-content {
            flex-grow: 1;
        }
        
        .event-type {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }
        
        .event-details {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .event-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: #adb5bd;
        }
        
        .filter-pills {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .filter-pill {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            border: 1px solid #dee2e6;
            background: white;
            color: #6c757d;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .filter-pill:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .filter-pill.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .btn-security {
            background: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
        }
        
        .btn-security:hover {
            background: #e03516;
            border-color: #e03516;
            color: white;
        }
        
        .real-time-indicator {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--success-color);
            font-size: 0.875rem;
        }
        
        .real-time-dot {
            width: 8px;
            height: 8px;
            background: var(--success-color);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .loading-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }
        
        .ip-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .ip-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .ip-info strong {
            color: var(--dark-color);
        }
        
        .system-health {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .health-metric {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .health-value {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .health-label {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>

<body>
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <div class="main-content">
        <!-- Security Header -->
        <div class="security-header">
            <div style="position: relative; z-index: 2;">
                <h1 class="mb-2">🛡️ Security Monitoring Dashboard</h1>
                <p class="mb-3">Real-time security monitoring and threat detection</p>
                <div class="real-time-indicator">
                    <span class="real-time-dot"></span>
                    <span>Live monitoring active</span>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-number" id="criticalEvents">--</div>
                <div class="stat-label">Critical Events (24h)</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-number" id="failedLogins">--</div>
                <div class="stat-label">Failed Logins (1h)</div>
            </div>
            <div class="stat-card success">
                <div class="stat-number" id="activeSessions">--</div>
                <div class="stat-label">Active Sessions</div>
            </div>
            <div class="stat-card critical">
                <div class="stat-number" id="blockedIPs">--</div>
                <div class="stat-label">Rate Limited IPs</div>
            </div>
        </div>

        <!-- Main Dashboard Grid -->
        <div class="row">
            <!-- Security Events Feed -->
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Security Events Feed
                        </h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshEvents()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                    
                    <!-- Severity Filters -->
                    <div class="filter-pills">
                        <a href="#" class="filter-pill active" data-severity="">All Events</a>
                        <a href="#" class="filter-pill" data-severity="CRITICAL">Critical</a>
                        <a href="#" class="filter-pill" data-severity="HIGH">High</a>
                        <a href="#" class="filter-pill" data-severity="WARNING">Warning</a>
                        <a href="#" class="filter-pill" data-severity="INFO">Info</a>
                    </div>
                    
                    <!-- Events List -->
                    <div id="securityEvents">
                        <div class="loading-placeholder">
                            <div class="spinner-border text-primary me-2"></div>
                            Loading security events...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Side Panel -->
            <div class="col-lg-4">
                <!-- Failed Login Analysis -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-shield-x me-2"></i>
                            Failed Login Analysis
                        </h6>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="failedLoginsChart"></canvas>
                    </div>
                    
                    <div class="ip-list" id="suspiciousIPs">
                        <div class="loading-placeholder">
                            <div class="spinner-border spinner-border-sm text-secondary me-2"></div>
                            Loading IP analysis...
                        </div>
                    </div>
                </div>

                <!-- System Health -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-cpu me-2"></i>
                            System Health
                        </h6>
                    </div>
                    
                    <div class="system-health" id="systemHealth">
                        <div class="health-metric">
                            <div class="health-value" id="diskUsage">--</div>
                            <div class="health-label">Disk Usage</div>
                        </div>
                        <div class="health-metric">
                            <div class="health-value" id="memoryUsage">--</div>
                            <div class="health-label">Memory (MB)</div>
                        </div>
                        <div class="health-metric">
                            <div class="health-value" id="dbConnections">--</div>
                            <div class="health-label">DB Connections</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-lightning me-2"></i>
                            Quick Actions
                        </h6>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="btn btn-security btn-sm" onclick="clearRateLimits()">
                            <i class="bi bi-unlock"></i> Clear Rate Limits
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="exportSecurityReport()">
                            <i class="bi bi-download"></i> Export Report
                        </button>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label">Block IP Address:</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="blockIPInput" 
                                   placeholder="192.168.1.100" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                            <button class="btn btn-security" onclick="blockIP()">
                                <i class="bi bi-ban"></i> Block
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Activity Chart -->
        <div class="dashboard-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-graph-up me-2"></i>
                    User Activity Trends
                </h5>
            </div>
            <div class="chart-container">
                <canvas id="userActivityChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Dashboard JavaScript
        let currentSeverityFilter = '';
        let refreshInterval;
        
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
            setupEventFilters();
            startRealTimeUpdates();
        });
        
        /**
         * Initialize dashboard
         */
        function initializeDashboard() {
            loadSecurityStats();
            loadSecurityEvents();
            loadFailedLoginAnalysis();
            loadUserActivity();
        }
        
        /**
         * Load security statistics
         */
        function loadSecurityStats() {
            fetch('?action=ajax&ajax_action=get_security_stats', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateStatsDisplay(data.stats);
                }
            })
            .catch(error => console.error('Failed to load stats:', error));
        }
        
        /**
         * Update statistics display
         */
        function updateStatsDisplay(stats) {
            document.getElementById('criticalEvents').textContent = 
                stats.events_24h.CRITICAL + stats.events_24h.HIGH;
            document.getElementById('failedLogins').textContent = stats.failed_logins_1h;
            document.getElementById('activeSessions').textContent = stats.active_sessions;
            document.getElementById('blockedIPs').textContent = stats.blocked_ips;
            
            // Update system health
            if (stats.system_health) {
                document.getElementById('diskUsage').textContent = stats.system_health.disk_usage + '%';
                document.getElementById('memoryUsage').textContent = stats.system_health.memory_usage;
                document.getElementById('dbConnections').textContent = stats.system_health.database_connections;
            }
        }
        
        /**
         * Load security events
         */
        function loadSecurityEvents() {
            const url = `?action=ajax&ajax_action=get_recent_events&severity=${currentSeverityFilter}&limit=50`;
            
            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySecurityEvents(data.events);
                }
            })
            .catch(error => console.error('Failed to load events:', error));
        }
        
        /**
         * Display security events
         */
        function displaySecurityEvents(events) {
            const container = document.getElementById('securityEvents');
            
            if (events.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-shield-check" style="font-size: 2rem;"></i>
                        <p class="mt-2">No security events found</p>
                    </div>
                `;
                return;
            }
            
            const eventsHtml = events.map(event => `
                <div class="event-item">
                    <div class="event-severity severity-${event.severity.toLowerCase()}"></div>
                    <div class="event-content">
                        <div class="event-type">${escapeHtml(event.event_type)}</div>
                        <div class="event-details">${event.details}</div>
                        <div class="event-meta">
                            <span><i class="bi bi-person"></i> ${event.user_email}</span>
                            <span><i class="bi bi-shield"></i> ${event.user_role}</span>
                            <span><i class="bi bi-geo-alt"></i> ${event.ip_address}</span>
                            <span><i class="bi bi-clock"></i> ${formatTimestamp(event.timestamp)}</span>
                        </div>
                    </div>
                </div>
            `).join('');
            
            container.innerHTML = eventsHtml;
        }
        
        /**
         * Setup event filters
         */
        function setupEventFilters() {
            document.querySelectorAll('.filter-pill').forEach(pill => {
                pill.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Update active state
                    document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update filter and reload
                    currentSeverityFilter = this.dataset.severity || '';
                    loadSecurityEvents();
                });
            });
        }
        
        /**
         * Load failed login analysis
         */
        function loadFailedLoginAnalysis() {
            fetch('?action=ajax&ajax_action=get_failed_logins', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySuspiciousIPs(data.top_ips);
                    createFailedLoginsChart(data.timeline);
                }
            })
            .catch(error => console.error('Failed to load failed login analysis:', error));
        }
        
        /**
         * Display suspicious IPs
         */
        function displaySuspiciousIPs(ips) {
            const container = document.getElementById('suspiciousIPs');
            
            if (ips.length === 0) {
                container.innerHTML = '<div class="text-center py-3 text-muted">No suspicious activity</div>';
                return;
            }
            
            const ipsHtml = ips.map(ip => `
                <div class="ip-item">
                    <div class="ip-info">
                        <strong>${escapeHtml(ip.ip_address)}</strong>
                        <small class="d-block text-muted">${ip.attempts} attempts</small>
                    </div>
                    <button class="btn btn-sm btn-outline-danger" onclick="blockSpecificIP('${escapeHtml(ip.ip_address)}')">
                        <i class="bi bi-ban"></i>
                    </button>
                </div>
            `).join('');
            
            container.innerHTML = ipsHtml;
        }
        
        /**
         * Create failed logins chart
         */
        function createFailedLoginsChart(timeline) {
            const ctx = document.getElementById('failedLoginsChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: timeline.map(t => new Date(t.hour).toLocaleTimeString([], {hour: '2-digit'})),
                    datasets: [{
                        label: 'Failed Attempts',
                        data: timeline.map(t => t.attempts),
                        borderColor: '#ff3e1d',
                        backgroundColor: 'rgba(255, 62, 29, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
        
        /**
         * Load user activity
         */
        function loadUserActivity() {
            fetch('?action=ajax&ajax_action=get_user_activity&days=7', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    createUserActivityChart(data.daily_activity);
                }
            })
            .catch(error => console.error('Failed to load user activity:', error));
        }
        
        /**
         * Create user activity chart
         */
        function createUserActivityChart(activity) {
            const ctx = document.getElementById('userActivityChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: activity.map(a => new Date(a.date).toLocaleDateString()),
                    datasets: [{
                        label: 'Active Users',
                        data: activity.map(a => a.active_users),
                        backgroundColor: '#696cff',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
        
        /**
         * Start real-time updates
         */
        function startRealTimeUpdates() {
            refreshInterval = setInterval(() => {
                loadSecurityStats();
                loadSecurityEvents();
            }, 30000); // Update every 30 seconds
        }
        
        /**
         * Refresh events manually
         */
        function refreshEvents() {
            loadSecurityEvents();
        }
        
        /**
         * Block IP address
         */
        function blockIP() {
            const ip = document.getElementById('blockIPInput').value.trim();
            blockSpecificIP(ip);
        }
        
        /**
         * Block specific IP address
         */
        function blockSpecificIP(ip) {
            if (!ip || !isValidIP(ip)) {
                alert('Please enter a valid IP address');
                return;
            }
            
            if (!confirm(`Are you sure you want to block IP address ${ip}?`)) {
                return;
            }
            
            const formData = new FormData();
            formData.append('ip', ip);
            
            fetch('?action=ajax&ajax_action=block_ip', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    document.getElementById('blockIPInput').value = '';
                    loadFailedLoginAnalysis(); // Refresh the suspicious IPs list
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Block IP error:', error);
                alert('Failed to block IP address');
            });
        }
        
        /**
         * Clear rate limits
         */
        function clearRateLimits() {
            if (!confirm('Are you sure you want to clear all rate limits? This will allow blocked IPs to access the system again.')) {
                return;
            }
            
            fetch('?action=ajax&ajax_action=clear_rate_limits', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    loadSecurityStats(); // Refresh stats
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Clear rate limits error:', error);
                alert('Failed to clear rate limits');
            });
        }
        
        /**
         * Export security report
         */
        function exportSecurityReport() {
            window.open('export-security-report.php', '_blank');
        }
        
        /**
         * Utility functions
         */
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatTimestamp(timestamp) {
            return new Date(timestamp).toLocaleString();
        }
        
        function isValidIP(ip) {
            const ipRegex = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            return ipRegex.test(ip);
        }
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>
