<?php
/**
 * Dynamic Admin User Creator
 * Automatically detects your table structure and uses only existing columns
 */

require_once 'config.php';

// Admin credentials - CHANGE THESE!
$admin_email = 'admin@internhub.com';
$admin_password = 'Admin123!'; 
$admin_username = 'Administrator';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Admin Creator - InternHub</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        h1 { color: #333; text-align: center; margin-bottom: 30px; }
        .success { background: #d4edda; border: 2px solid #28a745; color: #155724; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .warning { background: #fff3cd; border: 2px solid #ffc107; color: #856404; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .error { background: #f8d7da; border: 2px solid #dc3545; color: #721c24; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .info { background: #e3f2fd; border: 2px solid #2196f3; color: #0d47a1; padding: 20px; border-radius: 10px; margin: 20px 0; }
        .credentials { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 8px; margin: 15px 0; font-family: 'Courier New', monospace; }
        .btn { display: inline-block; padding: 12px 24px; margin: 5px; text-decoration: none; border-radius: 25px; font-weight: bold; transition: all 0.3s; color: white; }
        .btn-primary { background: #007bff; }
        .btn-success { background: #28a745; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; color: #495057; }
        .status-active { background: #28a745; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .text-center { text-align: center; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; color: #e91e63; }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🛡️ Smart Admin Creator</h1>";

try {
    // Step 1: Analyze the users table structure
    echo "<div class='info'>";
    echo "<h4>🔍 Analyzing Database Structure...</h4>";
    
    $table_info = $conn->query("SHOW COLUMNS FROM users");
    $available_columns = [];
    $column_details = [];
    
    while ($column = $table_info->fetch_assoc()) {
        $available_columns[] = $column['Field'];
        $column_details[] = $column;
    }
    
    echo "<p><strong>Found columns:</strong> " . implode(', ', $available_columns) . "</p>";
    echo "</div>";
    
    // Step 2: Check required columns
    $required_columns = ['username', 'email', 'password_hash', 'role'];
    $missing_required = [];
    
    foreach ($required_columns as $req_col) {
        if (!in_array($req_col, $available_columns)) {
            $missing_required[] = $req_col;
        }
    }
    
    if (!empty($missing_required)) {
        echo "<div class='error'>";
        echo "<h3>❌ Missing Required Columns</h3>";
        echo "<p>Your users table is missing these required columns:</p>";
        echo "<ul>";
        foreach ($missing_required as $col) {
            echo "<li><code>$col</code></li>";
        }
        echo "</ul>";
        echo "<p>Please update your database schema first.</p>";
        echo "</div>";
        exit;
    }
    
    // Step 3: Build dynamic INSERT query
    $insert_columns = ['username', 'email', 'password_hash', 'role'];
    $insert_values = [$admin_username, $admin_email, password_hash($admin_password, PASSWORD_DEFAULT), 'admin'];
    $placeholders = ['?', '?', '?', '?'];
    $param_types = 'ssss';
    
    // Add optional columns if they exist
    if (in_array('status', $available_columns)) {
        $insert_columns[] = 'status';
        $insert_values[] = 'active';
        $placeholders[] = '?';
        $param_types .= 's';
    }
    
    if (in_array('created_at', $available_columns)) {
        $insert_columns[] = 'created_at';
        $insert_values[] = date('Y-m-d H:i:s');
        $placeholders[] = '?';
        $param_types .= 's';
    }
    
    // Step 4: Check if admin already exists
    $check_stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR role = 'admin'");
    $check_stmt->bind_param("s", $admin_email);
    $check_stmt->execute();
    $existing_user = $check_stmt->get_result()->fetch_assoc();
    
    if ($existing_user) {
        echo "<div class='warning'>";
        echo "<h3>⚠️ Admin User Already Exists!</h3>";
        echo "<p>Found existing admin user:</p>";
        echo "<table>";
        foreach ($existing_user as $key => $value) {
            if (!in_array($key, ['password_hash'])) { // Don't show password hash
                echo "<tr><th>$key</th><td>$value</td></tr>";
            }
        }
        echo "</table>";
        
        echo "<div class='info'>";
        echo "<h4>🔄 Want to Update Password?</h4>";
        echo "<p>If you need to reset the password, uncomment the update section in this script and refresh the page.</p>";
        echo "</div>";
        echo "</div>";
        
        // Password reset option (uncomment to use)
        /*
        $new_password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE users SET password_hash = ?, username = ? WHERE email = ?");
        $update_stmt->bind_param("sss", $new_password_hash, $admin_username, $admin_email);
        if ($update_stmt->execute()) {
            echo "<div class='success'>";
            echo "<h3>✅ Password Updated Successfully!</h3>";
            echo "<div class='credentials'>";
            echo "<strong>Email:</strong> $admin_email<br>";
            echo "<strong>Username:</strong> $admin_username<br>";
            echo "<strong>New Password:</strong> $admin_password";
            echo "</div>";
            echo "</div>";
        }
        */
        
    } else {
        // Step 5: Create the admin user
        $sql = "INSERT INTO users (" . implode(', ', $insert_columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        echo "<div class='info'>";
        echo "<h4>📝 Creating Admin User...</h4>";
        echo "<p><strong>SQL:</strong> <code>$sql</code></p>";
        echo "<p><strong>Columns:</strong> " . implode(', ', $insert_columns) . "</p>";
        echo "</div>";
        
        $insert_stmt = $conn->prepare($sql);
        $insert_stmt->bind_param($param_types, ...$insert_values);
        
        if ($insert_stmt->execute()) {
            $admin_user_id = $conn->insert_id;
            
            echo "<div class='success'>";
            echo "<h3>🎉 Admin User Created Successfully!</h3>";
            echo "<p><strong>User ID:</strong> $admin_user_id</p>";
            
            echo "<div class='credentials'>";
            echo "<h4>🔐 Login Credentials:</h4>";
            echo "<strong>Email:</strong> $admin_email<br>";
            echo "<strong>Username:</strong> $admin_username<br>";
            echo "<strong>Password:</strong> $admin_password<br>";
            echo "<strong>Role:</strong> admin";
            if (in_array('status', $available_columns)) {
                echo "<br><strong>Status:</strong> active";
            }
            echo "</div>";
            
            echo "<div class='text-center' style='margin: 20px 0;'>";
            echo "<h4>🚀 Ready to Test!</h4>";
            echo "<a href='admin/admin-dashboard.php' target='_blank' class='btn btn-primary'>🏠 Admin Dashboard</a>";
            echo "<a href='admin/manage-users.php' target='_blank' class='btn btn-success'>👥 Manage Users</a>";
            echo "<a href='secure_login.php' target='_blank' class='btn btn-warning'>🔐 Main Login</a>";
            echo "</div>";
            echo "</div>";
            
        } else {
            echo "<div class='error'>";
            echo "<h3>❌ Error Creating Admin User</h3>";
            echo "<p><strong>MySQL Error:</strong> " . $conn->error . "</p>";
            echo "</div>";
        }
    }
    
    // Step 6: Show current admin users
    echo "<div class='info'>";
    echo "<h4>👑 Current Admin Users</h4>";
    
    // Build dynamic SELECT query
    $select_columns = array_intersect(['user_id', 'username', 'email', 'role', 'status', 'created_at'], $available_columns);
    $select_sql = "SELECT " . implode(', ', $select_columns) . " FROM users WHERE role = 'admin' ORDER BY user_id DESC";
    
    $admin_list = $conn->query($select_sql);
    if ($admin_list && $admin_list->num_rows > 0) {
        echo "<table>";
        echo "<tr>";
        foreach ($select_columns as $col) {
            echo "<th>" . ucfirst(str_replace('_', ' ', $col)) . "</th>";
        }
        echo "</tr>";
        
        while ($admin = $admin_list->fetch_assoc()) {
            echo "<tr>";
            foreach ($select_columns as $col) {
                if ($col === 'status') {
                    echo "<td><span class='status-{$admin[$col]}'>" . strtoupper($admin[$col]) . "</span></td>";
                } elseif ($col === 'created_at' && $admin[$col]) {
                    echo "<td>" . date('M j, Y g:i A', strtotime($admin[$col])) . "</td>";
                } else {
                    echo "<td>{$admin[$col]}</td>";
                }
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='text-center' style='color: #666;'>No admin users found.</p>";
    }
    echo "</div>";
    
    // Step 7: Show complete table structure
    echo "<div class='info'>";
    echo "<h4>📊 Complete Users Table Structure</h4>";
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($column_details as $column) {
        echo "<tr>";
        echo "<td><strong>{$column['Field']}</strong></td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Database Error</h3>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection in <code>config.php</code></p>";
    echo "</div>";
}

// Security warnings
echo "<div style='background: linear-gradient(135deg, #ff4757, #ff6b7d); color: white; padding: 25px; margin: 30px 0; border-radius: 15px; text-align: center;'>";
echo "<h3>🔥 CRITICAL SECURITY NOTES</h3>";
echo "<div style='text-align: left; max-width: 600px; margin: 0 auto;'>";
echo "<ul style='list-style-type: none; padding: 0;'>";
echo "<li>🗑️ <strong>DELETE THIS SCRIPT</strong> immediately after use!</li>";
echo "<li>🔒 <strong>CHANGE THE PASSWORD</strong> after first login!</li>";
echo "<li>💪 <strong>USE STRONG PASSWORDS</strong> in production!</li>";
echo "<li>🚫 <strong>NEVER COMMIT</strong> this file to git!</li>";
echo "</ul>";
echo "</div>";
echo "</div>";

echo "</div>
</body>
</html>";

$conn->close();
?>