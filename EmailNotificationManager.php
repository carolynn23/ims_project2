<?php
// Include database connection and mail configuration
require 'src/PHPMailer.php';     // To use the PHPMailer class
require 'src/Exception.php';     // To handle errors with exceptions
require 'src/SMTP.php';          // To use SMTP functionality
use PHPMailer\PHPMailer\PHPMailer;

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

    // Function to send email using SMTP
    private function sendEmail($to, $subject, $body) {
        // Use PHPMailer or any SMTP library to send the email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->smtp_config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_config['username'];
            $mail->Password = $this->smtp_config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_config['port'];

            $mail->setFrom($this->smtp_config['username'], 'Internship System');
            $mail->addAddress($to);

            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    // Function to send new application notification to the employer
    public function sendApplicationNotification($internship_id, $student_id) {
        $stmt = $this->conn->prepare("
            SELECT i.title, e.company_name, u.email as employer_email, u.username as employer_name,
                   s.full_name as student_name, s.email as student_email, s.program, s.department
            FROM internships i
            JOIN employers e ON i.employer_id = e.employer_id
            JOIN users u ON e.user_id = u.user_id
            JOIN students s ON s.user_id = u.user_id
            WHERE i.internship_id = ? AND s.student_id = ?
        ");
        $stmt->bind_param("ii", $internship_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $application = $result->fetch_assoc();

        $subject = "New Internship Application from " . $application['student_name'];
        $body = "Dear " . $application['employer_name'] . ",\n\n" .
                "You have received a new application for the internship: " . $application['title'] . ".\n\n" .
                "Student Details:\n" .
                "Name: " . $application['student_name'] . "\n" .
                "Program: " . $application['program'] . "\n" .
                "Department: " . $application['department'] . "\n" .
                "Resume: " . $application['resume'] . "\n\n" .
                "Regards,\nYour Internship System";

        // Send the email to the employer
        $this->sendEmail($application['employer_email'], $subject, $body);

        // Save the email notification to the database
        $stmt = $this->conn->prepare("INSERT INTO email_notifications (email_type, recipient_email, subject, body) 
                                     VALUES ('new_application', ?, ?, ?)");
        $stmt->bind_param("sss", $application['employer_email'], $subject, $body);
        $stmt->execute();
    }

    // Function to send application acceptance notification to the student
    public function sendApplicationAcceptedNotification($internship_id, $student_id) {
        $stmt = $this->conn->prepare("
            SELECT i.title, e.company_name, u.email as employer_email, u.username as employer_name,
                   s.full_name as student_name, s.email as student_email
            FROM internships i
            JOIN employers e ON i.employer_id = e.employer_id
            JOIN users u ON e.user_id = u.user_id
            JOIN students s ON s.user_id = u.user_id
            WHERE i.internship_id = ? AND s.student_id = ?
        ");
        $stmt->bind_param("ii", $internship_id, $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $application = $result->fetch_assoc();

        $subject = "Your Application for " . $application['title'] . " has been Accepted!";
        $body = "Dear " . $application['student_name'] . ",\n\n" .
                "We are pleased to inform you that your application for the internship: " . $application['title'] . 
                " with " . $application['company_name'] . " has been accepted.\n\n" .
                "We look forward to your contribution during the internship.\n\n" .
                "Best regards,\nThe Internship Team";

        // Send the email to the student
        $this->sendEmail($application['student_email'], $subject, $body);

        // Save the email notification to the database
        $stmt = $this->conn->prepare("INSERT INTO email_notifications (email_type, recipient_email, subject, body) 
                                     VALUES ('application_accepted', ?, ?, ?)");
        $stmt->bind_param("sss", $application['student_email'], $subject, $body);
        $stmt->execute();
    }

    // Function to send deadline reminder notification to the employer
    public function sendDeadlineReminderNotification($internship_id) {
        $stmt = $this->conn->prepare("
            SELECT i.title, e.company_name, e.contact_person, e.email AS employer_email, i.deadline
            FROM internships i
            JOIN employers e ON i.employer_id = e.employer_id
            WHERE i.internship_id = ?
        ");
        $stmt->bind_param("i", $internship_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $internship = $result->fetch_assoc();

        $subject = "Reminder: Internship Application Deadline Approaching for " . $internship['title'];
        $body = "Dear " . $internship['contact_person'] . ",\n\n" .
                "This is a reminder that the application deadline for the internship: " . $internship['title'] .
                " at " . $internship['company_name'] . " is approaching on " . $internship['deadline'] . ".\n\n" .
                "Best regards,\nThe Internship Team";

        // Send the email to the employer
        $this->sendEmail($internship['employer_email'], $subject, $body);

        // Save the email notification to the database
        $stmt = $this->conn->prepare("INSERT INTO email_notifications (email_type, recipient_email, subject, body) 
                                     VALUES ('deadline_reminder', ?, ?, ?)");
        $stmt->bind_param("sss", $internship['employer_email'], $subject, $body);
        $stmt->execute();
    }

    // Function to send mentorship request notification to the alumni
    public function sendMentorshipRequestNotification($student_id, $alumni_id) {
        $stmt = $this->conn->prepare("
            SELECT s.full_name AS student_name, a.full_name AS alumni_name, s.email AS student_email, a.email AS alumni_email
            FROM students s
            JOIN alumni a ON a.user_id = s.user_id
            WHERE s.student_id = ? AND a.alumni_id = ?
        ");
        $stmt->bind_param("ii", $student_id, $alumni_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();

        $subject = "Mentorship Request from " . $request['student_name'];
        $body = "Dear " . $request['alumni_name'] . ",\n\n" .
                "A mentorship request has been made by " . $request['student_name'] . ".\n\n" .
                "Student Email: " . $request['student_email'] . "\n\n" .
                "Best regards,\nThe Mentorship System";

        // Send the email to the alumni
        $this->sendEmail($request['alumni_email'], $subject, $body);

        // Save the email notification to the database
        $stmt = $this->conn->prepare("INSERT INTO email_notifications (email_type, recipient_email, subject, body) 
                                     VALUES ('mentorship_request', ?, ?, ?)");
        $stmt->bind_param("sss", $request['alumni_email'], $subject, $body);
        $stmt->execute();
    }
}
?>
