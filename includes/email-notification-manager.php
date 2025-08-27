<?php
// Enhanced Email Notification System
class EmailNotificationManager {
    private $conn;
    private $smtp_config;
    
    public function __construct($connection, $smtp_config = null) {
        $this->conn = $connection;
        $this->smtp_config = $smtp_config ?: [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'your-email@gmail.com',
            'password' => 'your-app-password',
            'encryption' => 'tls'
        ];
    }
    
    /**
     * Send application notification to employer
     */
    public function sendApplicationNotification($internship_id, $student_id) {
        // Get internship and employer details
        $stmt = $this->conn->prepare("
            SELECT i.title, i.description, e.company_name, u.email as employer_email, u.username as employer_name,
                   s.full_name as student_name, s.email as student_email, s.program, s.department
            FROM internships i
            JOIN employers e ON i.employer_id = e.employer_id
            JOIN users u ON e.user_id = u.user_id
            JOIN students s ON s.student_id = ?
            WHERE i.internship_id = ?
        ");
        $stmt->bind_param("ii", $student_id, $internship_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        
        if ($data) {
            $template = $this->getEmailTemplate('new_application', [
                'employer_name' => $data['employer_name'],
                'student_name' => $data['student_name'],
                'student_email' => $data['student_email'],
                'student_program' => $data['program'],
                'student_department' => $data['department'],
                'internship_title' => $data['title'],
                'company_name' => $data['company_name'],
                'view_application_url' => $this->getBaseUrl() . "/view-applications.php?internship_id=" . $internship_id
            ]);
            
            return $this->sendEmail($data['employer_email'], 'New Internship Application', $template);
        }
        return false;
    }
    
    /**
     * Send application status update to student
     */
    public function sendApplicationStatusUpdate($application_id, $status) {
        $stmt = $this->conn->prepare("
            SELECT s.full_name, s.email, i.title, e.company_name
            FROM applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN internships i ON a.internship_id = i.internship_id
            JOIN employers e ON i.employer_id = e.employer_id
            WHERE a.application_id = ?
        ");
        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        
        if ($data) {
            $template_name = $status === 'accepted' ? 'application_accepted' : 'application_rejected';
            $template = $this->getEmailTemplate($template_name, [
                'student_name' => $data['full_name'],
                'internship_title' => $data['title'],
                'company_name' => $data['company_name'],
                'dashboard_url' => $this->getBaseUrl() . "/student-applications.php"
            ]);
            
            $subject = $status === 'accepted' ? 
                "Congratulations! Your internship application has been accepted" :
                "Update on your internship application";
                
            return $this->sendEmail($data['email'], $subject, $template);
        }
        return false;
    }
    
    /**
     * Send deadline reminder emails
     */
    public function sendDeadlineReminders() {
        // Get internships with deadlines in 3 days and 1 day
        $stmt = $this->conn->prepare("
            SELECT DISTINCT i.internship_id, i.title, i.deadline, e.company_name,
                   s.student_id, s.full_name, s.email
            FROM internships i
            JOIN employers e ON i.employer_id = e.employer_id
            JOIN saved_internships si ON i.internship_id = si.internship_id
            JOIN students s ON si.student_id = s.student_id
            LEFT JOIN applications a ON i.internship_id = a.internship_id AND s.student_id = a.student_id
            WHERE a.application_id IS NULL
            AND (DATEDIFF(i.deadline, CURDATE()) IN (3, 1))
            AND i.deadline >= CURDATE()
        ");
        $stmt->execute();
        $reminders = $stmt->get_result();
        
        while ($reminder = $reminders->fetch_assoc()) {
            $days_left = (new DateTime($reminder['deadline']))->diff(new DateTime())->days;
            
            $template = $this->getEmailTemplate('deadline_reminder', [
                'student_name' => $reminder['full_name'],
                'internship_title' => $reminder['title'],
                'company_name' => $reminder['company_name'],
                'days_left' => $days_left,
                'deadline' => date('F j, Y', strtotime($reminder['deadline'])),
                'apply_url' => $this->getBaseUrl() . "/apply-internship.php?internship_id=" . $reminder['internship_id']
            ]);
            
            $subject = $days_left === 1 ? 
                "⏰ Last chance! Application deadline tomorrow" :
                "🔔 Application deadline in {$days_left} days";
            
            $this->sendEmail($reminder['email'], $subject, $template);
        }
    }
    
    /**
     * Send mentorship request notification
     */
    public function sendMentorshipRequest($request_id) {
        $stmt = $this->conn->prepare("
            SELECT a.full_name as alumni_name, ua.email as alumni_email,
                   s.full_name as student_name, s.program, s.department, s.level
            FROM mentorship_requests mr
            JOIN alumni a ON mr.alumni_id = a.alumni_id
            JOIN users ua ON a.user_id = ua.user_id
            JOIN students s ON mr.student_id = s.student_id
            WHERE mr.request_id = ?
        ");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        
        if ($data) {
            $template = $this->getEmailTemplate('mentorship_request', [
                'alumni_name' => $data['alumni_name'],
                'student_name' => $data['student_name'],
                'student_program' => $data['program'],
                'student_department' => $data['department'],
                'student_level' => $data['level'],
                'respond_url' => $this->getBaseUrl() . "/mentor-students.php"
            ]);
            
            return $this->sendEmail($data['alumni_email'], 'New Mentorship Request', $template);
        }
        return false;
    }
    
    /**
     * Get email template with variables replaced
     */
    private function getEmailTemplate($template_name, $variables) {
        $templates = [
            'new_application' => '
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9f9f9; padding: 30px; }
                        .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                        .student-info { background: white; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #667eea; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>🎉 New Internship Application!</h1>
                        </div>
                        <div class="content">
                            <p>Hello {{employer_name}},</p>
                            <p>Great news! A student has applied for your internship position:</p>
                            <div class="student-info">
                                <h3>{{internship_title}} at {{company_name}}</h3>
                                <p><strong>Applicant:</strong> {{student_name}}</p>
                                <p><strong>Email:</strong> {{student_email}}</p>
                                <p><strong>Program:</strong> {{student_program}}</p>
                                <p><strong>Department:</strong> {{student_department}}</p>
                            </div>
                            <p>Review their application and respond at your earliest convenience:</p>
                            <a href="{{view_application_url}}" class="button">Review Application</a>
                        </div>
                        <div class="footer">
                            <p>InternHub - Connecting students with opportunities</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            
            'application_accepted' => '
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f0fdf4; padding: 30px; }
                        .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                        .success-badge { background: #10b981; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>🎉 Congratulations!</h1>
                        </div>
                        <div class="content">
                            <p>Dear {{student_name}},</p>
                            <p>We have fantastic news! Your application has been <span class="success-badge">ACCEPTED</span></p>
                            <h3>{{internship_title}} at {{company_name}}</h3>
                            <p>The employer was impressed with your application and would like to move forward with the next steps.</p>
                            <p>Please check your dashboard for next steps and any additional information from the employer.</p>
                            <a href="{{dashboard_url}}" class="button">View Dashboard</a>
                        </div>
                        <div class="footer">
                            <p>Best of luck with your internship! - InternHub Team</p>
                        </div>
                    </div>
                </body>
                </html>
            ',
            
            'deadline_reminder' => '
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #fef3c7; padding: 30px; }
                        .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; background: #f59e0b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                        .urgent { background: #fecaca; border-left: 4px solid #dc2626; padding: 15px; border-radius: 5px; margin: 15px 0; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h1>⏰ Application Deadline Reminder</h1>
                        </div>
                        <div class="content">
                            <p>Hi {{student_name}},</p>
                            <div class="urgent">
                                <strong>Only {{days_left}} day(s) left</strong> to apply for this internship you saved!
                            </div>
                            <h3>{{internship_title}} at {{company_name}}</h3>
                            <p><strong>Application Deadline:</strong> {{deadline}}</p>
                            <p>Don\'t miss this opportunity! Apply now before it\'s too late.</p>
                            <a href="{{apply_url}}" class="button">Apply Now</a>
                        </div>
                        <div class="footer">
                            <p>InternHub - Your success is our priority</p>
                        </div>
                    </div>
                </body>
                </html>
            '
        ];
        
        $template = $templates[$template_name] ?? '';
        
        // Replace variables
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', htmlspecialchars($value), $template);
        }
        
        return $template;
    }
    
    /**
     * Send email using PHPMailer or native mail function
     */
    private function sendEmail($to, $subject, $body) {
        // If PHPMailer is available, use it for better deliverability
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendWithPHPMailer($to, $subject, $body);
        } else {
            return $this->sendWithMail($to, $subject, $body);
        }
    }
    
    private function sendWithMail($to, $subject, $body) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: InternHub <noreply@internhub.com>',
            'Reply-To: support@internhub.com',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    private function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
    }
}

// Usage example:
$emailManager = new EmailNotificationManager($conn);

// Send application notification when student applies
// Add this to your apply-internship.php after successful application
if ($application_successful) {
    $emailManager->sendApplicationNotification($internship_id, $student_id);
}

// Send status update when employer accepts/rejects
// Add this to your update-application-status.php
if ($status_updated) {
    $emailManager->sendApplicationStatusUpdate($application_id, $new_status);
}

// Set up cron job for deadline reminders (run daily)
// Add to crontab: 0 9 * * * php /path/to/your/project/send-daily-reminders.php
if (php_sapi_name() === 'cli') {
    $emailManager->sendDeadlineReminders();
}
?>