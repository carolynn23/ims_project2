<?php
/**
 * Secure File Upload Handler
 * Handles all file uploads with comprehensive security measures
 * 
 * Usage:
 * $handler = new SecureFileHandler();
 * $result = $handler->handleUpload($_FILES['file'], 'resume');
 */

class SecureFileHandler {
    private $security;
    private $conn;
    private $allowed_types = [
        'resume' => [
            'mime_types' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ],
            'extensions' => ['pdf', 'doc', 'docx'],
            'max_size' => 5 * 1024 * 1024, // 5MB
            'scan_content' => true
        ],
        'image' => [
            'mime_types' => [
                'image/jpeg',
                'image/png', 
                'image/gif',
                'image/webp'
            ],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 2 * 1024 * 1024, // 2MB
            'scan_content' => true,
            'resize' => true
        ],
        'poster' => [
            'mime_types' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp'
            ],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max_size' => 10 * 1024 * 1024, // 10MB
            'scan_content' => true,
            'resize' => true
        ]
    ];
    
    private $quarantine_dir;
    private $upload_base_dir;
    
    public function __construct($security_instance, $database_connection) {
        $this->security = $security_instance;
        $this->conn = $database_connection;
        $this->upload_base_dir = __DIR__ . '/../uploads/';
        $this->quarantine_dir = __DIR__ . '/../quarantine/';
        
        $this->initializeDirectories();
    }
    
    /**
     * Handle file upload with comprehensive security
     */
    public function handleUpload($file, $type = 'resume', $options = []) {
        try {
            // Basic validation
            $validation_result = $this->validateUpload($file, $type);
            if (!$validation_result['success']) {
                return $validation_result;
            }
            
            // Virus scan (if available)
            $scan_result = $this->scanFile($file['tmp_name']);
            if (!$scan_result['safe']) {
                $this->quarantineFile($file['tmp_name'], $scan_result['reason']);
                $this->logSecurityEvent(
                    'MALICIOUS_FILE_UPLOAD',
                    "File: {$file['name']}, Reason: {$scan_result['reason']}",
                    'HIGH'
                );
                return [
                    'success' => false,
                    'errors' => ['File appears to contain malicious content and has been quarantined.']
                ];
            }
            
            // Generate secure filename
            $secure_filename = $this->generateSecureFilename($file['name'], $type);
            
            // Determine upload directory
            $upload_dir = $this->getUploadDirectory($type);
            $full_path = $upload_dir . $secure_filename;
            
            // Process file based on type
            $process_result = $this->processFile($file, $full_path, $type, $options);
            if (!$process_result['success']) {
                return $process_result;
            }
            
            // Store file metadata
            $metadata = $this->storeFileMetadata($secure_filename, $file, $type);
            
            $this->logSecurityEvent(
                'FILE_UPLOAD_SUCCESS',
                "File: $secure_filename, Type: $type, Size: {$file['size']}",
                'INFO'
            );
            
            return [
                'success' => true,
                'filename' => $secure_filename,
                'metadata' => $metadata,
                'path' => $full_path
            ];
            
        } catch (Exception $e) {
            $this->logSecurityEvent(
                'FILE_UPLOAD_ERROR',
                "Error: {$e->getMessage()}, File: {$file['name']}",
                'HIGH'
            );
            
            return [
                'success' => false,
                'errors' => ['An error occurred during file upload. Please try again.']
            ];
        }
    }
    
    /**
     * Validate file upload
     */
    private function validateUpload($file, $type) {
        $errors = [];
        
        // Check if file was uploaded
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            
            $errors[] = $error_messages[$file['error']] ?? 'Unknown upload error';
            return ['success' => false, 'errors' => $errors];
        }
        
        // Check file type configuration
        if (!isset($this->allowed_types[$type])) {
            $errors[] = 'Invalid file type specified';
            return ['success' => false, 'errors' => $errors];
        }
        
        $config = $this->allowed_types[$type];
        
        // Check file size
        if ($file['size'] > $config['max_size']) {
            $max_size_mb = round($config['max_size'] / 1024 / 1024, 1);
            $errors[] = "File size exceeds maximum allowed size of {$max_size_mb}MB";
        }
        
        // Check file is actually uploaded
        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid file upload';
            return ['success' => false, 'errors' => $errors];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $config['mime_types'])) {
            $errors[] = 'File type not allowed';
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $config['extensions'])) {
            $errors[] = 'File extension not allowed';
        }
        
        // Additional security checks
        $security_result = $this->performSecurityChecks($file);
        if (!$security_result['safe']) {
            $errors = array_merge($errors, $security_result['errors']);
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        return ['success' => true];
    }
    
    /**
     * Perform additional security checks
     */
    private function performSecurityChecks($file) {
        $errors = [];
        $filename = $file['name'];
        $content = file_get_contents($file['tmp_name']);
        
        // Check for executable extensions in filename
        $dangerous_extensions = [
            'php', 'php3', 'php4', 'php5', 'phtml', 'exe', 'bat', 'cmd', 
            'com', 'scr', 'vbs', 'js', 'jar', 'sh', 'py', 'pl', 'rb'
        ];
        
        foreach ($dangerous_extensions as $ext) {
            if (stripos($filename, ".$ext") !== false) {
                $errors[] = 'Filename contains potentially dangerous extension';
                break;
            }
        }
        
        // Check for PHP code in file content
        if (strpos($content, '<?php') !== false || 
            strpos($content, '<?=') !== false || 
            strpos($content, '<script') !== false) {
            $errors[] = 'File contains potentially malicious code';
        }
        
        // Check for null bytes (path traversal attempt)
        if (strpos($filename, "\0") !== false) {
            $errors[] = 'Filename contains null bytes';
        }
        
        // Check for path traversal attempts
        if (strpos($filename, '../') !== false || 
            strpos($filename, '..\\') !== false) {
            $errors[] = 'Filename contains path traversal attempt';
        }
        
        // Check filename length
        if (strlen($filename) > 255) {
            $errors[] = 'Filename too long';
        }
        
        return [
            'safe' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Scan file for viruses (basic implementation)
     */
    private function scanFile($filepath) {
        // Basic content-based detection
        $content = file_get_contents($filepath, false, null, 0, 8192); // First 8KB
        
        // Check for known malicious signatures
        $malicious_signatures = [
            'eval(',
            'base64_decode',
            'exec(',
            'system(',
            'shell_exec',
            'passthru',
            'file_get_contents("http',
            'curl_exec',
            'fsockopen'
        ];
        
        foreach ($malicious_signatures as $signature) {
            if (stripos($content, $signature) !== false) {
                return [
                    'safe' => false,
                    'reason' => "Malicious signature detected: $signature"
                ];
            }
        }
        
        // If ClamAV is available (optional)
        if (function_exists('cl_scanfile')) {
            $scan_result = cl_scanfile($filepath);
            if ($scan_result !== CL_CLEAN) {
                return [
                    'safe' => false,
                    'reason' => 'Virus detected by ClamAV'
                ];
            }
        }
        
        return ['safe' => true, 'reason' => 'Clean'];
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFilename($original_name, $type) {
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $timestamp = time();
        $random = bin2hex(random_bytes(8));
        $user_id = $_SESSION['user_id'] ?? 'guest';
        
        return "{$type}_{$timestamp}_{$user_id}_{$random}.{$extension}";
    }
    
    /**
     * Get upload directory for file type
     */
    private function getUploadDirectory($type) {
        $type_dirs = [
            'resume' => 'documents/',
            'image' => 'images/',
            'poster' => 'posters/'
        ];
        
        $dir = $this->upload_base_dir . ($type_dirs[$type] ?? 'misc/');
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            
            // Create .htaccess to prevent direct access
            $htaccess_content = "Order deny,allow\nDeny from all\n";
            if ($type === 'image' || $type === 'poster') {
                $htaccess_content = "Order allow,deny\nAllow from all\n";
            }
            file_put_contents($dir . '.htaccess', $htaccess_content);
        }
        
        return $dir;
    }
    
    /**
     * Process file based on type
     */
    private function processFile($file, $destination, $type, $options) {
        $config = $this->allowed_types[$type];
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return [
                'success' => false,
                'errors' => ['Failed to move uploaded file']
            ];
        }
        
        // Set secure file permissions
        chmod($destination, 0644);
        
        // Process image files
        if (($type === 'image' || $type === 'poster') && !empty($config['resize'])) {
            $resize_result = $this->processImage($destination, $options);
            if (!$resize_result['success']) {
                unlink($destination); // Clean up on failure
                return $resize_result;
            }
        }
        
        return ['success' => true];
    }
    
    /**
     * Process and optimize images
     */
    private function processImage($filepath, $options = []) {
        try {
            $max_width = $options['max_width'] ?? 1200;
            $max_height = $options['max_height'] ?? 800;
            $quality = $options['quality'] ?? 85;
            
            $imageinfo = getimagesize($filepath);
            if (!$imageinfo) {
                return ['success' => false, 'errors' => ['Invalid image file']];
            }
            
            $width = $imageinfo[0];
            $height = $imageinfo[1];
            $type = $imageinfo[2];
            
            // Only resize if image is larger than max dimensions
            if ($width <= $max_width && $height <= $max_height) {
                return ['success' => true];
            }
            
            // Calculate new dimensions
            $ratio = min($max_width / $width, $max_height / $height);
            $new_width = intval($width * $ratio);
            $new_height = intval($height * $ratio);
            
            // Create image resource
            switch ($type) {
                case IMAGETYPE_JPEG:
                    $source = imagecreatefromjpeg($filepath);
                    break;
                case IMAGETYPE_PNG:
                    $source = imagecreatefrompng($filepath);
                    break;
                case IMAGETYPE_GIF:
                    $source = imagecreatefromgif($filepath);
                    break;
                case IMAGETYPE_WEBP:
                    $source = imagecreatefromwebp($filepath);
                    break;
                default:
                    return ['success' => false, 'errors' => ['Unsupported image type']];
            }
            
            if (!$source) {
                return ['success' => false, 'errors' => ['Failed to create image resource']];
            }
            
            // Create new image
            $destination_image = imagecreatetruecolor($new_width, $new_height);
            
            // Preserve transparency for PNG and GIF
            if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
                imagealphablending($destination_image, false);
                imagesavealpha($destination_image, true);
                $transparent = imagecolorallocatealpha($destination_image, 255, 255, 255, 127);
                imagefill($destination_image, 0, 0, $transparent);
            }
            
            // Resize image
            imagecopyresampled($destination_image, $source, 0, 0, 0, 0, 
                              $new_width, $new_height, $width, $height);
            
            // Save resized image
            switch ($type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($destination_image, $filepath, $quality);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($destination_image, $filepath, 9);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($destination_image, $filepath);
                    break;
                case IMAGETYPE_WEBP:
                    imagewebp($destination_image, $filepath, $quality);
                    break;
            }
            
            // Clean up
            imagedestroy($source);
            imagedestroy($destination_image);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => ['Image processing failed: ' . $e->getMessage()]
            ];
        }
    }
    
    /**
     * Store file metadata in database
     */
    private function storeFileMetadata($filename, $file, $type) {
        $stmt = $this->conn->prepare("
            INSERT INTO file_uploads 
            (user_id, filename, original_name, file_type, mime_type, file_size, upload_date, file_category)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        
        $user_id = $_SESSION['user_id'] ?? null;
        $mime_type = mime_content_type($this->getUploadDirectory($type) . $filename);
        
        $stmt->bind_param("issssis", 
            $user_id, 
            $filename, 
            $file['name'], 
            $type, 
            $mime_type, 
            $file['size'], 
            $type
        );
        
        $stmt->execute();
        
        return [
            'upload_id' => $this->conn->insert_id,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'mime_type' => $mime_type
        ];
    }
    
    /**
     * Quarantine suspicious file
     */
    private function quarantineFile($filepath, $reason) {
        $quarantine_filename = 'quarantine_' . time() . '_' . bin2hex(random_bytes(8));
        $quarantine_path = $this->quarantine_dir . $quarantine_filename;
        
        if (move_uploaded_file($filepath, $quarantine_path)) {
            // Log quarantine action
            $stmt = $this->conn->prepare("
                INSERT INTO quarantined_files 
                (filename, original_path, reason, quarantined_at, user_id)
                VALUES (?, ?, ?, NOW(), ?)
            ");
            
            $user_id = $_SESSION['user_id'] ?? null;
            $stmt->bind_param("sssi", $quarantine_filename, $filepath, $reason, $user_id);
            $stmt->execute();
        }
    }
    
    /**
     * Delete file securely
     */
    public function deleteFile($filename, $type) {
        try {
            $upload_dir = $this->getUploadDirectory($type);
            $filepath = $upload_dir . $filename;
            
            if (!file_exists($filepath)) {
                return ['success' => false, 'error' => 'File not found'];
            }
            
            // Verify user has permission to delete this file
            $stmt = $this->conn->prepare("
                SELECT user_id FROM file_uploads 
                WHERE filename = ? AND file_category = ?
            ");
            $stmt->bind_param("ss", $filename, $type);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (!$result || ($result['user_id'] != $_SESSION['user_id'] && $_SESSION['role'] !== 'admin')) {
                $this->logSecurityEvent(
                    'UNAUTHORIZED_FILE_DELETE',
                    "Attempted to delete: $filename",
                    'WARNING'
                );
                return ['success' => false, 'error' => 'Permission denied'];
            }
            
            // Secure delete (overwrite with random data)
            $this->secureDelete($filepath);
            
            // Update database
            $delete_stmt = $this->conn->prepare("
                UPDATE file_uploads 
                SET deleted_at = NOW() 
                WHERE filename = ?
            ");
            $delete_stmt->bind_param("s", $filename);
            $delete_stmt->execute();
            
            $this->logSecurityEvent(
                'FILE_DELETED',
                "File: $filename, Type: $type",
                'INFO'
            );
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Secure file deletion (overwrite before delete)
     */
    private function secureDelete($filepath) {
        if (file_exists($filepath)) {
            $filesize = filesize($filepath);
            
            // Overwrite with random data
            $handle = fopen($filepath, 'r+');
            if ($handle) {
                fseek($handle, 0);
                fwrite($handle, random_bytes($filesize));
                fclose($handle);
            }
            
            // Delete file
            unlink($filepath);
        }
    }
    
    /**
     * Initialize required directories
     */
    private function initializeDirectories() {
        $directories = [
            $this->upload_base_dir,
            $this->upload_base_dir . 'documents/',
            $this->upload_base_dir . 'images/',
            $this->upload_base_dir . 'posters/',
            $this->quarantine_dir
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                
                // Create .htaccess for security
                $htaccess_content = "Order deny,allow\nDeny from all\n";
                if (strpos($dir, 'images') !== false || strpos($dir, 'posters') !== false) {
                    $htaccess_content = "Order allow,deny\nAllow from all\n";
                }
                file_put_contents($dir . '.htaccess', $htaccess_content);
            }
        }
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($event_type, $details, $severity = 'INFO') {
        if ($this->security) {
            $this->security->logSecurityEvent(
                $event_type,
                $details,
                $_SESSION['user_id'] ?? null,
                $severity
            );
        }
    }
    
    /**
     * Get file info
     */
    public function getFileInfo($filename) {
        $stmt = $this->conn->prepare("
            SELECT * FROM file_uploads 
            WHERE filename = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param("s", $filename);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Clean old files (run via cron)
     */
    public function cleanupOldFiles($days = 30) {
        // Clean quarantined files older than specified days
        $stmt = $this->conn->prepare("
            SELECT filename FROM quarantined_files 
            WHERE quarantined_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->bind_param("i", $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $filepath = $this->quarantine_dir . $row['filename'];
            if (file_exists($filepath)) {
                $this->secureDelete($filepath);
            }
        }
        
        // Clean database records
        $cleanup_stmt = $this->conn->prepare("
            DELETE FROM quarantined_files 
            WHERE quarantined_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $cleanup_stmt->bind_param("i", $days);
        $cleanup_stmt->execute();
    }
}

// Additional database table needed for file tracking
/*
CREATE TABLE file_uploads (
    upload_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_category VARCHAR(50) NOT NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_filename (filename),
    INDEX idx_file_category (file_category)
);

CREATE TABLE quarantined_files (
    quarantine_id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_path TEXT,
    reason TEXT NOT NULL,
    quarantined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_id INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_quarantined_at (quarantined_at)
);
*/
?>