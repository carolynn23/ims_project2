
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('student', 'employer', 'alumni', 'admin') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- Students
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    full_name VARCHAR(100),
    email VARCHAR(100),
    department VARCHAR(100),
    program VARCHAR(100),
     level ENUM('100', '200', '300', '400') NOT NULL,
    field_of_interest VARCHAR(100),
    skills TEXT,
    gpa DECIMAL(3,2),
    resume VARCHAR(255),
    preferences TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);


-- Employers
CREATE TABLE employers (
    employer_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    company_name VARCHAR(100),
    industry VARCHAR(100),
    company_profile TEXT,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    website VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Alumni
CREATE TABLE alumni (
    alumni_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    full_name VARCHAR(100),
    graduation_year YEAR,
    current_position VARCHAR(100),
    mentorship_offered BOOLEAN,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);


-- Internships
CREATE TABLE internships (
    internship_id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT,
    title VARCHAR(100),
    description TEXT,
    requirements TEXT,
    duration VARCHAR(50),
    location VARCHAR(100),
    deadline DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES employers(employer_id)
);

-- Applications
CREATE TABLE applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    internship_id INT,
    student_id INT,
    resume VARCHAR(255),
    cover_letter TEXT,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (internship_id) REFERENCES internships(internship_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id)
);
otifications
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    message TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Feedback (from Students to Employers)
CREATE TABLE feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    employer_id INT,
    message TEXT,
    rating INT CHECK (rating BETWEEN 1 AND 5),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (employer_id) REFERENCES employers(employer_id)
);

CREATE TABLE saved_internships (
    saved_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    internship_id INT NOT NULL,
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (internship_id) REFERENCES internships(internship_id) ON DELETE CASCADE
);
CREATE TABLE email_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    email_type ENUM('new_application', 'application_accepted', 'application_rejected', 'deadline_reminder', 'mentorship_request') NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for tracking failed login attempts to implement rate limiting
CREATE TABLE login_attempts (
    ip_address VARCHAR(45) NOT NULL,
    attempts INT DEFAULT 0,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (ip_address)
);

-- Table for mentorship requests between students and alumni
CREATE TABLE mentorship_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    alumni_id INT NOT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (alumni_id) REFERENCES alumni(alumni_id) ON DELETE CASCADE
);

-- Table for storing saved application deadlines for reminders
CREATE TABLE internship_deadlines (
    deadline_id INT AUTO_INCREMENT PRIMARY KEY,
    internship_id INT NOT NULL,
    reminder_date DATE NOT NULL,
    FOREIGN KEY (internship_id) REFERENCES internships(internship_id) ON DELETE CASCADE
);