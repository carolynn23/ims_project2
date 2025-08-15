<?php
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html>
<head>
  <title>Register — Student</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .registration-container {
      max-width: 800px;
      margin: 2vh auto;
      padding: 20px;
    }

    .wizard-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      position: relative;
    }

    .wizard-header {
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
      color: white;
      padding: 30px;
      text-align: center;
      position: relative;
    }

    .wizard-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
    }

    .wizard-icon {
      width: 60px;
      height: 60px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      backdrop-filter: blur(10px);
      position: relative;
      z-index: 1;
    }

    .wizard-icon svg {
      width: 30px;
      height: 30px;
      fill: white;
    }

    .wizard-title {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 5px;
      position: relative;
      z-index: 1;
    }

    .wizard-subtitle {
      opacity: 0.9;
      font-size: 1rem;
      position: relative;
      z-index: 1;
    }

    .progress-container {
      padding: 30px;
      background: #f8fafc;
      border-bottom: 1px solid #e2e8f0;
    }

    .progress-steps {
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
      margin-bottom: 20px;
    }

    .progress-line {
      position: absolute;
      top: 20px;
      left: 0;
      right: 0;
      height: 2px;
      background: #e2e8f0;
      z-index: 1;
    }

    .progress-line-active {
      height: 2px;
      background: linear-gradient(90deg, #3b82f6, #1d4ed8);
      transition: width 0.5s ease;
      position: absolute;
      top: 0;
      left: 0;
      z-index: 2;
    }

    .progress-step {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: white;
      border: 3px solid #e2e8f0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 0.9rem;
      color: #94a3b8;
      position: relative;
      z-index: 3;
      transition: all 0.3s ease;
    }

    .progress-step.active {
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
      border-color: #3b82f6;
      color: white;
      transform: scale(1.1);
      box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
    }

    .progress-step.completed {
      background: #10b981;
      border-color: #10b981;
      color: white;
    }

    .step-labels {
      display: flex;
      justify-content: space-between;
      margin-top: 15px;
    }

    .step-label {
      text-align: center;
      font-size: 0.85rem;
      color: #64748b;
      font-weight: 500;
      flex: 1;
    }

    .step-label.active {
      color: #3b82f6;
      font-weight: 600;
    }

    .wizard-content {
      padding: 40px;
    }

    .step-content {
      display: none;
    }

    .step-content.active {
      display: block;
      animation: fadeInUp 0.5s ease;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .step-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 10px;
    }

    .step-description {
      color: #64748b;
      margin-bottom: 30px;
      font-size: 1rem;
    }

    .form-label {
      font-weight: 600;
      color: #374151;
      margin-bottom: 8px;
      font-size: 0.9rem;
    }

    .form-control, .form-select {
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 1rem;
      transition: all 0.3s ease;
      background: #f8fafc;
    }

    .form-control:focus, .form-select:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
      background: white;
    }

    .form-text {
      font-size: 0.8rem;
      color: #6b7280;
      margin-top: 5px;
    }

    .wizard-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 30px 40px;
      background: #f8fafc;
      border-top: 1px solid #e2e8f0;
    }

    .btn {
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      border: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
      color: white;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
    }

    .btn-outline-secondary {
      background: white;
      border: 2px solid #e2e8f0;
      color: #64748b;
    }

    .btn-outline-secondary:hover {
      background: #f1f5f9;
      border-color: #cbd5e1;
      color: #475569;
      transform: translateY(-1px);
    }

    .btn-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
    }

    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
    }

    .alert {
      border-radius: 12px;
      margin-bottom: 25px;
      border: none;
      font-size: 0.9rem;
    }

    .alert-danger {
      background: #fef2f2;
      color: #dc2626;
      border-left: 4px solid #dc2626;
    }

    .alert-success {
      background: #f0fdf4;
      color: #16a34a;
      border-left: 4px solid #16a34a;
    }

    .field-group {
      background: #f8fafc;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 25px;
      border: 1px solid #e2e8f0;
    }

    .field-group-title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .field-group-icon {
      width: 20px;
      height: 20px;
      fill: #3b82f6;
    }

    .invalid-feedback {
      display: block;
      width: 100%;
      margin-top: 5px;
      font-size: 0.8rem;
      color: #dc2626;
    }

    .form-control.is-invalid {
      border-color: #dc2626;
    }

    @media (max-width: 768px) {
      .registration-container {
        margin: 1vh auto;
        padding: 10px;
      }

      .wizard-content {
        padding: 30px 20px;
      }

      .wizard-actions {
        padding: 20px;
        flex-direction: column;
        gap: 15px;
      }

      .progress-steps {
        margin-bottom: 15px;
      }

      .progress-step {
        width: 35px;
        height: 35px;
        font-size: 0.8rem;
      }

      .step-labels {
        margin-top: 10px;
      }

      .step-label {
        font-size: 0.75rem;
      }
    }
  </style>
</head>
<body>
<div class="registration-container">
  <div class="wizard-card">
    <div class="wizard-header">
      <div class="wizard-icon">
        <svg viewBox="0 0 24 24">
          <path d="M12 2L13.09 8.26L22 9L17.2 13.74L18.18 22.5L12 19.77L5.82 22.5L6.8 13.74L2 9L10.91 8.26L12 2Z"/>
        </svg>
      </div>
      <h1 class="wizard-title">Student Registration</h1>
      <p class="wizard-subtitle">Join our educational community in just 3 simple steps</p>
    </div>

    <div class="progress-container">
      <div class="progress-steps">
        <div class="progress-line">
          <div class="progress-line-active" id="progressLine"></div>
        </div>
        <div class="progress-step active" data-step="1">1</div>
        <div class="progress-step" data-step="2">2</div>
        <div class="progress-step" data-step="3">3</div>
      </div>
      <div class="step-labels">
        <div class="step-label active">Account Setup</div>
        <div class="step-label">Academic Details</div>
        <div class="step-label">Skills & Documents</div>
      </div>
    </div>

    <?php if (!empty($_GET['error'])): ?>
      <div class="px-4 pt-3">
        <div class="alert alert-danger"><?= htmlspecialchars($_GET['error']) ?></div>
      </div>
    <?php endif; ?>
    <?php if (!empty($_GET['success'])): ?>
      <div class="px-4 pt-3">
        <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
      </div>
    <?php endif; ?>

    <form method="post" action="insert-student.php" enctype="multipart/form-data" id="registrationForm">
      <div class="wizard-content">
        <!-- Step 1: Account Setup -->
        <div class="step-content active" id="step1">
          <h2 class="step-title">Account Setup</h2>
          <p class="step-description">Create your login credentials and basic account information</p>
          
          <div class="field-group">
            <div class="field-group-title">
              <svg class="field-group-icon" viewBox="0 0 24 24">
                <path d="M12 12C14.21 12 16 10.21 16 8C16 5.79 14.21 4 12 4C9.79 4 8 5.79 8 8C8 10.21 9.79 12 12 12ZM12 14C9.33 14 4 15.34 4 18V20H20V18C20 15.34 14.67 14 12 14Z"/>
              </svg>
              Login Credentials
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" required>
              </div>
              <div class="col-12">
                <label class="form-label">Student Number</label>
                <input type="text" name="student_number" class="form-control" placeholder="e.g., STU-2025-00123" required>
                <div class="form-text">Your institution-issued unique student ID</div>
              </div>
            </div>
          </div>

          <div class="field-group">
            <div class="field-group-title">
              <svg class="field-group-icon" viewBox="0 0 24 24">
                <path d="M12,17A2,2 0 0,0 14,15C14,13.89 13.1,13 12,13A2,2 0 0,0 10,15A2,2 0 0,0 12,17M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V10C4,8.89 4.9,8 6,8H7V6A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,3A3,3 0 0,0 9,6V8H15V6A3,3 0 0,0 12,3Z"/>
              </svg>
              Security
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" minlength="6" required>
                <div class="form-text">Minimum 6 characters</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2: Academic Details -->
        <div class="step-content" id="step2">
          <h2 class="step-title">Academic Details</h2>
          <p class="step-description">Tell us about your academic background and current studies</p>
          
          <div class="field-group">
            <div class="field-group-title">
              <svg class="field-group-icon" viewBox="0 0 24 24">
                <path d="M12 3L1 9L12 15L21 10.09V17H23V9L12 3ZM5 13.18V17.18L12 21L19 17.18V13.18L12 17L5 13.18Z"/>
              </svg>
              Personal Information
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Field of Interest</label>
                <input type="text" name="field_of_interest" class="form-control" placeholder="e.g., Data Science, UI/UX Design">
              </div>
            </div>
          </div>

          <div class="field-group">
            <div class="field-group-title">
              <svg class="field-group-icon" viewBox="0 0 24 24">
                <path d="M5,3H19A2,2 0 0,1 21,5V19A2,2 0 0,1 19,21H5A2,2 0 0,1 3,19V5A2,2 0 0,1 5,3M7.5,18A1.5,1.5 0 0,0 9,16.5A1.5,1.5 0 0,0 7.5,15A1.5,1.5 0 0,0 6,16.5A1.5,1.5 0 0,0 7.5,18M7.5,6A1.5,1.5 0 0,0 9,7.5A1.5,1.5 0 0,0 7.5,9A1.5,1.5 0 0,0 6,7.5A1.5,1.5 0 0,0 7.5,6M16.5,18A1.5,1.5 0 0,0 18,16.5A1.5,1.5 0 0,0 16.5,15A1.5,1.5 0 0,0 15,16.5A1.5,1.5 0 0,0 16.5,18M16.5,6A1.5,1.5 0 0,0 18,7.5A1.5,1.5 0 0,0 16.5,9A1.5,1.5 0 0,0 15,7.5A1.5,1.5 0 0,0 16.5,6M7.5,12A1.5,1.5 0 0,0 9,10.5A1.5,1.5 0 0,0 7.5,9A1.5,1.5 0 0,0 6,10.5A1.5,1.5 0 0,0 7.5,12M16.5,12A1.5,1.5 0 0,0 18,10.5A1.5,1.5 0 0,0 16.5,9A1.5,1.5 0 0,0 15,10.5A1.5,1.5 0 0,0 16.5,12Z"/>
              </svg>
              Academic Program
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Department</label>
                <input type="text" name="department" class="form-control" placeholder="e.g., Computer Science">
              </div>
              <div class="col-md-6">
                <label class="form-label">Program</label>
                <input type="text" name="program" class="form-control" placeholder="e.g., Bachelor of Science">
              </div>
              <div class="col-md-6">
                <label class="form-label">Current Level</label>
                <input type="text" name="level" class="form-control" placeholder="e.g., Level 300, Year 3">
              </div>
              <div class="col-md-6">
                <label class="form-label">GPA (Optional)</label>
                <input type="number" step="0.01" min="0" max="4" name="gpa" class="form-control" placeholder="e.g., 3.45">
                <div class="form-text">Current cumulative GPA (4.0 scale)</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 3: Skills & Documents -->
        <div class="step-content" id="step3">
          <h2 class="step-title">Skills & Documents</h2>
          <p class="step-description">Share your skills, preferences, and upload your resume</p>
          
          <div class="field-group">
            <div class="field-group-title">
              <svg class="field-group-icon" viewBox="0 0 24 24">
                <path d="M19,3H5C3.9,3 3,3.9 3,5V19C3,20.1 3.9,21 5,21H19C20.1,21 21,20.1 21,19V5C21,3.9 20.1,3 19,3ZM19,19H5V5H19V19ZM17,12H7V10H17V12ZM17,16H7V14H17V16ZM17,8H7V6H17V8Z"/>
              </svg>
              Professional Profile
            </div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Skills</label>
                <textarea name="skills" class="form-control" rows="4" placeholder="List your technical and soft skills separated by commas.&#10;&#10;Examples:&#10;• Technical: Python, JavaScript, SQL, React, Figma, Adobe Creative Suite&#10;• Soft Skills: Communication, Team Leadership, Problem-solving, Time Management"></textarea>
                <div class="form-text">Include both technical and soft skills</div>
              </div>
              <div class="col-12">
                <label class="form-label">Career Preferences</label>
                <textarea name="preferences" class="form-control" rows="4" placeholder="Share your career preferences and goals.&#10;&#10;Examples:&#10;• Preferred work environment (remote, hybrid, on-site)&#10;• Industry interests (tech, finance, healthcare, etc.)&#10;• Internship duration preferences&#10;• Career goals and aspirations"></textarea>
                <div class="form-text">Help employers understand what you're looking for</div>
              </div>
            </div>
          </div>

          <div class="field-group">
            <div class="field-group-title">
              <svg class="field-group-icon" viewBox="0 0 24 24">
                <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
              </svg>
              Document Upload
            </div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Resume/CV</label>
                <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
                <div class="form-text">
                  <strong>Optional:</strong> Upload your resume in PDF, DOC, or DOCX format (maximum 5MB)<br>
                  This will help employers learn more about your background and experience.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="wizard-actions">
        <button type="button" class="btn btn-outline-secondary" id="prevBtn" style="display: none;">
          ← Previous
        </button>
        <div>
          <a href="login.php" class="btn btn-outline-secondary me-2">Back to Login</a>
          <button type="button" class="btn btn-primary" id="nextBtn">
            Next →
          </button>
          <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">
            Create Account
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  let currentStep = 1;
  const totalSteps = 3;

  function updateProgress() {
    // Update progress line
    const progressLine = document.getElementById('progressLine');
    const progressWidth = ((currentStep - 1) / (totalSteps - 1)) * 100;
    progressLine.style.width = progressWidth + '%';

    // Update step indicators
    document.querySelectorAll('.progress-step').forEach((step, index) => {
      const stepNumber = index + 1;
      step.classList.remove('active', 'completed');
      
      if (stepNumber < currentStep) {
        step.classList.add('completed');
        step.innerHTML = '✓';
      } else if (stepNumber === currentStep) {
        step.classList.add('active');
        step.innerHTML = stepNumber;
      } else {
        step.innerHTML = stepNumber;
      }
    });

    // Update step labels
    document.querySelectorAll('.step-label').forEach((label, index) => {
      const stepNumber = index + 1;
      label.classList.remove('active');
      if (stepNumber === currentStep) {
        label.classList.add('active');
      }
    });

    // Show/hide step content
    document.querySelectorAll('.step-content').forEach((content, index) => {
      const stepNumber = index + 1;
      content.classList.remove('active');
      if (stepNumber === currentStep) {
        content.classList.add('active');
      }
    });

    // Update buttons
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    if (currentStep === 1) {
      prevBtn.style.display = 'none';
    } else {
      prevBtn.style.display = 'inline-block';
    }

    if (currentStep === totalSteps) {
      nextBtn.style.display = 'none';
      submitBtn.style.display = 'inline-block';
    } else {
      nextBtn.style.display = 'inline-block';
      submitBtn.style.display = 'none';
    }
  }

  function validateStep(step) {
    const stepElement = document.getElementById(`step${step}`);
    const requiredFields = stepElement.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    requiredFields.forEach(field => {
      field.classList.remove('is-invalid');
      const feedback = field.parentNode.querySelector('.invalid-feedback');
      if (feedback) feedback.remove();

      if (!field.value.trim()) {
        field.classList.add('is-invalid');
        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = 'invalid-feedback';
        feedbackDiv.textContent = 'This field is required.';
        field.parentNode.appendChild(feedbackDiv);
        isValid = false;
      }
    });

    // Additional validation for step 1
    if (step === 1) {
      const password = stepElement.querySelector('input[name="password"]');
      const confirmPassword = stepElement.querySelector('input[name="confirm_password"]');
      
      if (password.value && confirmPassword.value && password.value !== confirmPassword.value) {
        confirmPassword.classList.add('is-invalid');
        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = 'invalid-feedback';
        feedbackDiv.textContent = 'Passwords do not match.';
        confirmPassword.parentNode.appendChild(feedbackDiv);
        isValid = false;
      }
    }

    return isValid;
  }

  document.getElementById('nextBtn').addEventListener('click', function() {
    if (validateStep(currentStep)) {
      currentStep++;
      updateProgress();
      
      // Scroll to top of the wizard
      document.querySelector('.wizard-card').scrollIntoView({ 
        behavior: 'smooth', 
        block: 'start' 
      });
    }
  });

  document.getElementById('prevBtn').addEventListener('click', function() {
    currentStep--;
    updateProgress();
    
    // Scroll to top of the wizard
    document.querySelector('.wizard-card').scrollIntoView({ 
      behavior: 'smooth', 
      block: 'start' 
    });
  });

  document.getElementById('registrationForm').addEventListener('submit', function(e) {
    // Validate all steps before submission
    let allValid = true;
    for (let i = 1; i <= totalSteps; i++) {
      if (!validateStep(i)) {
        allValid = false;
        currentStep = i;
        updateProgress();
        break;
      }
    }
    
    if (!allValid) {
      e.preventDefault();
      document.querySelector('.wizard-card').scrollIntoView({ 
        behavior: 'smooth', 
        block: 'start' 
      });
    }
  });

  // Initialize
  updateProgress();

  // Real-time password confirmation validation
  document.addEventListener('input', function(e) {
    if (e.target.name === 'confirm_password' || e.target.name === 'password') {
      const password = document.querySelector('input[name="password"]');
      const confirmPassword = document.querySelector('input[name="confirm_password"]');
      
      if (password.value && confirmPassword.value) {
        if (password.value === confirmPassword.value) {
          confirmPassword.classList.remove('is-invalid');
          confirmPassword.classList.add('is-valid');
          const feedback = confirmPassword.parentNode.querySelector('.invalid-feedback');
          if (feedback) feedback.remove();
        } else {
          confirmPassword.classList.remove('is-valid');
          confirmPassword.classList.add('is-invalid');
        }
      }
    }
  });
</script>
</body>
</html>