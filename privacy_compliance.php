<?php
/**
 * Data Privacy & GDPR Compliance Utilities
 * Handles user data privacy rights, consent management, and data retention
 */

class PrivacyCompliance {
    private $conn;
    private $security;
    
    public function __construct($database_connection, $security_instance) {
        $this->conn = $database_connection;
        $this->security = $security_instance;
    }
    
    /**
     * Record user consent
     */
    public function recordConsent($user_id, $consent_types = [], $ip_address = null) {
        $ip_address = $ip_address ?: $this->getRealIP();
        
        try {
            // Record consent in audit table
            $stmt = $this->conn->prepare("
                INSERT INTO privacy_consent_log 
                (user_id, consent_types, ip_address, user_agent, consent_date) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $consent_json = json_encode($consent_types);
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt->bind_param("isss", $user_id, $consent_json, $ip_address, $user_agent);
            $stmt->execute();
            
            // Update user tables with consent flags
            $this->updateUserConsent($user_id, $consent_types);
            
            $this->security->logSecurityEvent(
                'PRIVACY_CONSENT_RECORDED',
                "Types: " . implode(', ', $consent_types),
                $user_id,
                'INFO'
            );
            
            return true;
            
        } catch (Exception $e) {
            $this->security->logSecurityEvent(
                'PRIVACY_CONSENT_ERROR',
                "Error recording consent: " . $e->getMessage(),
                $user_id,
                'HIGH'
            );
            return false;
        }
    }
    
    /**
     * Update user consent in relevant tables
     */
    private function updateUserConsent($user_id, $consent_types) {
        // Get user role to determine which table to update
        $role_stmt = $this->conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $role_stmt->bind_param("i", $user_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result()->fetch_assoc();
        
        if (!$role_result) return false;
        
        $role = $role_result['role'];
        $table_map = [
            'student' => 'students',
            'employer' => 'employers',
            'alumni' => 'alumni'
        ];
        
        if (!isset($table_map[$role])) return false;
        
        $table = $table_map[$role];
        
        $updates = [];
        $values = [];
        
        if (in_array('data_processing', $consent_types)) {
            $updates[] = 'data_processing_consent = TRUE';
            $updates[] = 'consent_date = NOW()';
        }
        
        if (in_array('marketing', $consent_types)) {
            $updates[] = 'marketing_consent = TRUE';
        }
        
        if (in_array('data_retention', $consent_types)) {
            $updates[] = 'data_retention_date = DATE_ADD(NOW(), INTERVAL 2 YEAR)';
        }
        
        if (!empty($updates)) {
            $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $update_stmt = $this->conn->prepare($sql);
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
        }
    }
    
    /**
     * Handle data subject access request (Right to Access)
     */
    public function handleDataAccessRequest($user_id, $email, $request_type = 'export') {
        try {
            // Verify user identity
            $verify_stmt = $this->conn->prepare("
                SELECT user_id, role FROM users WHERE user_id = ? AND email = ? AND status = 'active'
            ");
            $verify_stmt->bind_param("is", $user_id, $email);
            $verify_stmt->execute();
            $user = $verify_stmt->get_result()->fetch_assoc();
            
            if (!$user) {
                throw new Exception("User verification failed");
            }
            
            // Log the request
            $this->security->logSecurityEvent(
                'DATA_ACCESS_REQUEST',
                "Type: $request_type, Email: $email",
                $user_id,
                'INFO'
            );
            
            // Generate request token
            $request_token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Store request
            $request_stmt = $this->conn->prepare("
                INSERT INTO data_requests 
                (user_id, request_type, request_token, expires_at, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $request_stmt->bind_param("isss", $user_id, $request_type, $request_token, $expires_at);
            $request_stmt->execute();
            
            // Send verification email (you'd implement email sending)
            $this->sendDataRequestEmail($email, $request_token, $request_type);
            
            return [
                'success' => true,
                'message' => 'Data request submitted. Please check your email for verification.',
                'request_token' => $request_token
            ];
            
        } catch (Exception $e) {
            $this->security->logSecurityEvent(
                'DATA_ACCESS_REQUEST_ERROR',
                "Error: " . $e->getMessage() . ", Email: $email",
                $user_id,
                'HIGH'
            );
            
            return [
                'success' => false,
                'message' => 'Unable to process data request. Please try again later.'
            ];
        }
    }
    
    /**
     * Process verified data request
     */
    public function processDataRequest($request_token) {
        try {
            // Verify and get request details
            $request_stmt = $this->conn->prepare("
                SELECT dr.*, u.email, u.role 
                FROM data_requests dr
                JOIN users u ON dr.user_id = u.user_id
                WHERE dr.request_token = ? AND dr.expires_at > NOW() AND dr.status = 'pending'
            ");
            $request_stmt->bind_param("s", $request_token);
            $request_stmt->execute();
            $request = $request_stmt->get_result()->fetch_assoc();
            
            if (!$request) {
                throw new Exception("Invalid or expired request token");
            }
            
            $user_id = $request['user_id'];
            $request_type = $request['request_type'];
            
            // Mark request as processing
            $update_stmt = $this->conn->prepare("
                UPDATE data_requests SET status = 'processing', processed_at = NOW() 
                WHERE request_token = ?
            ");
            $update_stmt->bind_param("s", $request_token);
            $update_stmt->execute();
            
            $result = null;
            
            switch ($request_type) {
                case 'export':
                    $result = $this->exportUserData($user_id, $request['role']);
                    break;
                case 'delete':
                    $result = $this->deleteUserData($user_id, $request['role']);
                    break;
                case 'anonymize':
                    $result = $this->anonymizeUserData($user_id, $request['role']);
                    break;
                default:
                    throw new Exception("Unsupported request type");
            }
            
            // Update request status
            $final_status = $result['success'] ? 'completed' : 'failed';
            $complete_stmt = $this->conn->prepare("
                UPDATE data_requests 
                SET status = ?, completed_at = NOW(), result_data = ? 
                WHERE request_token = ?
            ");
            $result_json = json_encode($result);
            $complete_stmt->bind_param("sss", $final_status, $result_json, $request_token);
            $complete_stmt->execute();
            
            $this->security->logSecurityEvent(
                'DATA_REQUEST_PROCESSED',
                "Type: $request_type, Status: $final_status",
                $user_id,
                'INFO'
            );
            
            return $result;
            
        } catch (Exception $e) {
            $this->security->logSecurityEvent(
                'DATA_REQUEST_PROCESS_ERROR',
                "Error: " . $e->getMessage() . ", Token: " . substr($request_token, 0, 8) . "...",
                null,
                'HIGH'
            );
            
            return [
                'success' => false,
                'message' => 'Error processing data request: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Export all user data
     */
    private function exportUserData($user_id, $role) {
        $data = [];
        
        try {
            // Basic user information
            $user_stmt = $this->conn->prepare("
                SELECT username, email, role, status, created_at, updated_at 
                FROM users WHERE user_id = ?
            ");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $data['user_account'] = $user_stmt->get_result()->fetch_assoc();
            
            // Role-specific data
            switch ($role) {
                case 'student':
                    $data = array_merge($data, $this->exportStudentData($user_id));
                    break;
                case 'employer':
                    $data = array_merge($data, $this->exportEmployerData($user_id));
                    break;
                case 'alumni':
                    $data = array_merge($data, $this->exportAlumniData($user_id));
                    break;
            }
            
            // Common data for all users
            $data['security_logs'] = $this->exportSecurityLogs($user_id);
            $data['file_uploads'] = $this->exportFileUploads($user_id);
            $data['consent_history'] = $this->exportConsentHistory($user_id);
            
            // Create export file
            $export_filename = "user_data_export_{$user_id}_" . date('Y-m-d_H-i-s') . ".json";
            $export_path = __DIR__ . "/../exports/" . $export_filename;
            
            // Ensure exports directory exists
            if (!is_dir(dirname($export_path))) {
                mkdir(dirname($export_path), 0755, true);
            }
            
            file_put_contents($export_path, json_encode($data, JSON_PRETTY_PRINT));
            
            return [
                'success' => true,
                'message' => 'Data exported successfully',
                'export_file' => $export_filename,
                'records_count' => $this->countRecords($data)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Export student-specific data
     */
    private function exportStudentData($user_id) {
        $data = [];
        
        // Student profile
        $profile_stmt = $this->conn->prepare("SELECT * FROM students WHERE user_id = ?");
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        $data['student_profile'] = $profile_stmt->get_result()->fetch_assoc();
        
        // Applications
        $apps_stmt = $this->conn->prepare("
            SELECT a.*, i.title as internship_title, e.company_name 
            FROM applications a
            JOIN internships i ON a.internship_id = i.internship_id
            JOIN employers e ON i.employer_id = e.employer_id
            JOIN students s ON a.student_id = s.student_id
            WHERE s.user_id = ?
        ");
        $apps_stmt->bind_param("i", $user_id);
        $apps_stmt->execute();
        $data['applications'] = $apps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Saved internships
        $saved_stmt = $this->conn->prepare("
            SELECT si.*, i.title as internship_title, e.company_name 
            FROM saved_internships si
            JOIN internships i ON si.internship_id = i.internship_id
            JOIN employers e ON i.employer_id = e.employer_id
            JOIN students s ON si.student_id = s.student_id
            WHERE s.user_id = ?
        ");
        $saved_stmt->bind_param("i", $user_id);
        $saved_stmt->execute();
        $data['saved_internships'] = $saved_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        return $data;
    }
    
    /**
     * Export employer-specific data
     */
    private function exportEmployerData($user_id) {
        $data = [];
        
        // Employer profile
        $profile_stmt = $this->conn->prepare("SELECT * FROM employers WHERE user_id = ?");
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        $data['employer_profile'] = $profile_stmt->get_result()->fetch_assoc();
        
        // Posted internships
        $internships_stmt = $this->conn->prepare("
            SELECT i.* FROM internships i
            JOIN employers e ON i.employer_id = e.employer_id
            WHERE e.user_id = ?
        ");
        $internships_stmt->bind_param("i", $user_id);
        $internships_stmt->execute();
        $data['posted_internships'] = $internships_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        return $data;
    }
    
    /**
     * Export alumni-specific data
     */
    private function exportAlumniData($user_id) {
        $data = [];
        
        // Alumni profile
        $profile_stmt = $this->conn->prepare("SELECT * FROM alumni WHERE user_id = ?");
        $profile_stmt->bind_param("i", $user_id);
        $profile_stmt->execute();
        $data['alumni_profile'] = $profile_stmt->get_result()->fetch_assoc();
        
        return $data;
    }
    
    /**
     * Export security logs for user
     */
    private function exportSecurityLogs($user_id) {
        $logs_stmt = $this->conn->prepare("
            SELECT event_type, details, severity, ip_address, created_at 
            FROM security_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1000
        ");
        $logs_stmt->bind_param("i", $user_id);
        $logs_stmt->execute();
        return $logs_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Export file uploads for user
     */
    private function exportFileUploads($user_id) {
        $files_stmt = $this->conn->prepare("
            SELECT original_name, file_type, file_size, upload_date, file_category 
            FROM file_uploads 
            WHERE user_id = ? AND deleted_at IS NULL
        ");
        $files_stmt->bind_param("i", $user_id);
        $files_stmt->execute();
        return $files_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Export consent history for user
     */
    private function exportConsentHistory($user_id) {
        $consent_stmt = $this->conn->prepare("
            SELECT consent_types, ip_address, consent_date 
            FROM privacy_consent_log 
            WHERE user_id = ? 
            ORDER BY consent_date DESC
        ");
        $consent_stmt->bind_param("i", $user_id);
        $consent_stmt->execute();
        return $consent_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Delete user data (Right to be Forgotten)
     */
    private function deleteUserData($user_id, $role) {
        try {
            $this->conn->begin_transaction();
            
            // Delete role-specific data
            switch ($role) {
                case 'student':
                    $this->deleteStudentData($user_id);
                    break;
                case 'employer':
                    $this->deleteEmployerData($user_id);
                    break;
                case 'alumni':
                    $this->deleteAlumniData($user_id);
                    break;
            }
            
            // Delete common user data
            $this->deleteCommonUserData($user_id);
            
            // Mark user as deleted instead of hard delete (for referential integrity)
            $delete_user_stmt = $this->conn->prepare("
                UPDATE users SET 
                    email = CONCAT('deleted_', user_id, '@deleted.local'),
                    username = CONCAT('deleted_', user_id),
                    status = 'deleted',
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $delete_user_stmt->bind_param("i", $user_id);
            $delete_user_stmt->execute();
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'User data deleted successfully'
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Data deletion failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Anonymize user data
     */
    private function anonymizeUserData($user_id, $role) {
        try {
            $this->conn->begin_transaction();
            
            $anonymous_email = "anonymous_{$user_id}@anonymized.local";
            $anonymous_name = "Anonymous User";
            $anonymous_text = "[ANONYMIZED]";
            
            // Anonymize role-specific data
            switch ($role) {
                case 'student':
                    $anon_stmt = $this->conn->prepare("
                        UPDATE students SET 
                            full_name = ?,
                            email = ?,
                            skills = ?,
                            preferences = ?
                        WHERE user_id = ?
                    ");
                    $anon_stmt->bind_param("ssssi", $anonymous_name, $anonymous_email, 
                                         $anonymous_text, $anonymous_text, $user_id);
                    $anon_stmt->execute();
                    break;
            }
            
            // Anonymize user account
            $user_stmt = $this->conn->prepare("
                UPDATE users SET 
                    email = ?,
                    username = CONCAT('anon_', user_id),
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $user_stmt->bind_param("si", $anonymous_email, $user_id);
            $user_stmt->execute();
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'User data anonymized successfully'
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            return [
                'success' => false,
                'message' => 'Data anonymization failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up expired data based on retention policies
     */
    public function cleanupExpiredData() {
        $cleaned = [];
        
        try {
            // Clean expired data requests
            $expired_requests = $this->conn->prepare("
                DELETE FROM data_requests 
                WHERE expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $expired_requests->execute();
            $cleaned['expired_requests'] = $this->conn->affected_rows;
            
            // Clean old security logs (keep for 1 year)
            $old_logs = $this->conn->prepare("
                DELETE FROM security_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
            ");
            $old_logs->execute();
            $cleaned['old_security_logs'] = $this->conn->affected_rows;
            
            // Clean expired sessions
            $expired_sessions = $this->conn->prepare("
                DELETE FROM user_sessions 
                WHERE expires_at < NOW() OR is_active = FALSE
            ");
            $expired_sessions->execute();
            $cleaned['expired_sessions'] = $this->conn->affected_rows;
            
            // Identify users past data retention date
            $retention_check = $this->conn->prepare("
                SELECT user_id, 'students' as table_name FROM students 
                WHERE data_retention_date IS NOT NULL AND data_retention_date < NOW()
                UNION
                SELECT user_id, 'employers' as table_name FROM employers 
                WHERE data_retention_date IS NOT NULL AND data_retention_date < NOW()
                UNION
                SELECT user_id, 'alumni' as table_name FROM alumni 
                WHERE data_retention_date IS NOT NULL AND data_retention_date < NOW()
            ");
            $retention_check->execute();
            $retention_expired = $retention_check->get_result();
            
            $retention_count = 0;
            while ($expired = $retention_expired->fetch_assoc()) {
                // Send notification to user about data retention expiry
                $this->notifyDataRetentionExpiry($expired['user_id']);
                $retention_count++;
            }
            
            $cleaned['retention_notifications'] = $retention_count;
            
            return $cleaned;
            
        } catch (Exception $e) {
            $this->security->logSecurityEvent(
                'DATA_CLEANUP_ERROR',
                "Error during cleanup: " . $e->getMessage(),
                null,
                'HIGH'
            );
            return false;
        }
    }
    
    /**
     * Get user's current privacy settings
     */
    public function getPrivacySettings($user_id) {
        $role_stmt = $this->conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $role_stmt->bind_param("i", $user_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result()->fetch_assoc();
        
        if (!$role_result) return false;
        
        $table_map = [
            'student' => 'students',
            'employer' => 'employers',
            'alumni' => 'alumni'
        ];
        
        $table = $table_map[$role_result['role']] ?? null;
        if (!$table) return false;
        
        $settings_stmt = $this->conn->prepare("
            SELECT data_processing_consent, marketing_consent, consent_date, data_retention_date 
            FROM $table WHERE user_id = ?
        ");
        $settings_stmt->bind_param("i", $user_id);
        $settings_stmt->execute();
        
        return $settings_stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Helper methods
     */
    private function getRealIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function sendDataRequestEmail($email, $token, $type) {
        // Implement email sending logic here
        // You would send an email with a verification link containing the token
        return true;
    }
    
    private function countRecords($data) {
        $count = 0;
        foreach ($data as $section) {
            if (is_array($section)) {
                $count += count($section);
            }
        }
        return $count;
    }
    
    private function deleteStudentData($user_id) {
        // Delete student-specific data while preserving referential integrity
        $tables_to_clear = [
            "UPDATE applications SET student_id = NULL WHERE student_id = (SELECT student_id FROM students WHERE user_id = ?)",
            "DELETE FROM saved_internships WHERE student_id = (SELECT student_id FROM students WHERE user_id = ?)",
            "DELETE FROM students WHERE user_id = ?"
        ];
        
        foreach ($tables_to_clear as $sql) {
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
    }
    
    private function deleteEmployerData($user_id) {
        // Similar logic for employer data
        $stmt = $this->conn->prepare("DELETE FROM employers WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    private function deleteAlumniData($user_id) {
        // Similar logic for alumni data
        $stmt = $this->conn->prepare("DELETE FROM alumni WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
    
    private function deleteCommonUserData($user_id) {
        // Delete files
        $files_stmt = $this->conn->prepare("
            SELECT filename, file_category FROM file_uploads WHERE user_id = ?
        ");
        $files_stmt->bind_param("i", $user_id);
        $files_stmt->execute();
        $files = $files_stmt->get_result();
        
        // Securely delete physical files
        while ($file = $files->fetch_assoc()) {
            $filepath = __DIR__ . "/../uploads/" . $file['file_category'] . "/" . $file['filename'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
        
        // Delete database records
        $delete_tables = [
            'file_uploads',
            'privacy_consent_log',
            'user_sessions',
            'data_requests'
        ];
        
        foreach ($delete_tables as $table) {
            $stmt = $this->conn->prepare("DELETE FROM $table WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
        }
    }
    
    private function notifyDataRetentionExpiry($user_id) {
        // Send notification about data retention policy expiry
        // This would typically send an email to the user
        return true;
    }
}

/*
Additional database tables needed for privacy compliance:

CREATE TABLE privacy_consent_log (
    consent_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    consent_types JSON NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    consent_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_consent_date (consent_date)
);

CREATE TABLE data_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    request_type ENUM('export', 'delete', 'anonymize') NOT NULL,
    request_token VARCHAR(64) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    processed_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    result_data JSON,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_token (request_token),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);

CREATE TABLE blocked_ips (
    block_id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    blocked_by INT NOT NULL,
    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (blocked_by) REFERENCES users(user_id),
    UNIQUE KEY unique_ip (ip_address),
    INDEX idx_blocked_at (blocked_at),
    INDEX idx_is_active (is_active)
);
*/
?>