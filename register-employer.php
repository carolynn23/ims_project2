<?php
session_start();
require_once 'config.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Employer Registration - Join Our Network</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px 0;
    }

    .registration-container {
      width: 100%;
      max-width: 800px;
      margin: 0 auto;
      position: relative;
    }

    .hero-section {
      text-align: center;
      margin-bottom: 30px;
      color: white;
    }

    .hero-section h1 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 10px;
      text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .hero-section p {
      font-size: 1.1rem;
      opacity: 0.9;
      font-weight: 300;
    }

    .benefits-bar {
      display: flex;
      justify-content: center;
      gap: 30px;
      margin-bottom: 30px;
      flex-wrap: wrap;
    }

    .benefit-item {
      display: flex;
      align-items: center;
      gap: 8px;
      color: white;
      font-size: 0.9rem;
      opacity: 0.9;
    }

    .benefit-item i {
      color: #a8e6cf;
    }

    .form-container {
      background: white;
      border-radius: 24px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      animation: slideInUp 0.6s ease-out;
    }

    .form-header {
      background: linear-gradient(135deg, #f8fafc 0%, #e6fffa 100%);
      padding: 30px;
      text-align: center;
      border-bottom: 1px solid #e2e8f0;
    }

    .form-header h2 {
      color: #2d3748;
      font-size: 1.8rem;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .form-header p {
      color: #718096;
      font-size: 1rem;
    }

    .form-content {
      padding: 40px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 25px;
      margin-bottom: 30px;
    }

    .form-section {
      background: #f8fafc;
      padding: 25px;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
    }

    .section-title {
      font-size: 1.2rem;
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title i {
      color: #667eea;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group.full-width {
      grid-column: 1 / -1;
    }

    .form-label {
      display: block;
      font-weight: 500;
      color: #4a5568;
      margin-bottom: 8px;
      font-size: 0.95rem;
    }

    .form-label.required::after {
      content: ' *';
      color: #e53e3e;
    }

    .input-wrapper {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #a0aec0;
      z-index: 2;
    }

    .form-input {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-size: 1rem;
      transition: all 0.3s ease;
      background: white;
      font-family: 'Inter', sans-serif;
    }

    .form-input.with-icon {
      padding-left: 45px;
    }

    .form-input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      transform: translateY(-1px);
    }

    .form-input:valid {
      border-color: #38a169;
    }

    .form-textarea {
      resize: vertical;
      min-height: 100px;
      font-family: 'Inter', sans-serif;
    }

    .password-wrapper {
      position: relative;
    }

    .password-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #a0aec0;
      cursor: pointer;
      z-index: 2;
    }

    .password-toggle:hover {
      color: #667eea;
    }

    .password-strength {
      margin-top: 8px;
      height: 4px;
      background: #e2e8f0;
      border-radius: 2px;
      overflow: hidden;
    }

    .password-strength-bar {
      height: 100%;
      width: 0%;
      transition: all 0.3s ease;
      border-radius: 2px;
    }

    .password-strength.weak .password-strength-bar {
      width: 33%;
      background: #e53e3e;
    }

    .password-strength.medium .password-strength-bar {
      width: 66%;
      background: #d69e2e;
    }

    .password-strength.strong .password-strength-bar {
      width: 100%;
      background: #38a169;
    }

    .submit-section {
      background: linear-gradient(135deg, #f8fafc 0%, #e6fffa 100%);
      padding: 30px;
      border-radius: 16px;
      text-align: center;
      margin-top: 20px;
    }

    .submit-btn {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      padding: 16px 40px;
      border: none;
      border-radius: 16px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
      min-width: 200px;
      justify-content: center;
    }

    .submit-btn:hover:not(:disabled) {
      transform: translateY(-3px);
      box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
    }

    .submit-btn:disabled {
      background: #cbd5e0;
      color: #a0aec0;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .terms-checkbox {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 20px;
      padding: 20px;
      background: #fffbf0;
      border-radius: 12px;
      border: 1px solid #fbd38d;
    }

    .checkbox-wrapper {
      margin-top: 2px;
    }

    .checkbox-wrapper input[type="checkbox"] {
      width: 18px;
      height: 18px;
      accent-color: #667eea;
    }

    .terms-text {
      color: #744210;
      font-size: 0.9rem;
      line-height: 1.5;
    }

    .terms-text a {
      color: #667eea;
      text-decoration: none;
      font-weight: 500;
    }

    .terms-text a:hover {
      text-decoration: underline;
    }

    .login-link {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #e2e8f0;
      color: #718096;
    }

    .login-link a {
      color: #667eea;
      text-decoration: none;
      font-weight: 500;
    }

    .login-link a:hover {
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      .hero-section h1 {
        font-size: 2rem;
      }

      .benefits-bar {
        gap: 15px;
      }

      .benefit-item {
        font-size: 0.8rem;
      }

      .form-content {
        padding: 25px;
      }

      .form-grid {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .form-section {
        padding: 20px;
      }
    }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .success-animation {
      animation: successPulse 0.6s ease-out;
    }

    @keyframes successPulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
  </style>
</head>
<body>
  <div class="registration-container">
    <div class="hero-section">
      <h1><i class="fas fa-handshake"></i> Join Our Network</h1>
      <p>Connect with talented students and grow your team</p>
    </div>

    <div class="benefits-bar">
      <div class="benefit-item">
        <i class="fas fa-users"></i>
        Access to Top Talent
      </div>
      <div class="benefit-item">
        <i class="fas fa-chart-line"></i>
        Easy Management
      </div>
      <div class="benefit-item">
        <i class="fas fa-shield-alt"></i>
        Secure Platform
      </div>
      <div class="benefit-item">
        <i class="fas fa-headset"></i>
        24/7 Support
      </div>
    </div>

    <div class="form-container">
      <div class="form-header">
        <h2>Employer Registration</h2>
        <p>Create your account to start posting internships</p>
      </div>

      <div class="form-content">
        <form action="insert-employer.php" method="POST" id="registrationForm">
          <div class="form-grid">
            <!-- Company Information Section -->
            <div class="form-section">
              <h3 class="section-title">
                <i class="fas fa-building"></i>
                Company Information
              </h3>

              <div class="form-group">
                <label for="company_name" class="form-label required">Company Name</label>
                <div class="input-wrapper">
                  <i class="fas fa-building input-icon"></i>
                  <input type="text" id="company_name" name="company_name" class="form-input with-icon" required />
                </div>
              </div>

              <div class="form-group">
                <label for="industry" class="form-label required">Industry</label>
                <div class="input-wrapper">
                  <i class="fas fa-industry input-icon"></i>
                  <input type="text" id="industry" name="industry" class="form-input with-icon" required />
                </div>
              </div>

              <div class="form-group">
                <label for="website" class="form-label">Website</label>
                <div class="input-wrapper">
                  <i class="fas fa-globe input-icon"></i>
                  <input type="url" id="website" name="website" class="form-input with-icon" placeholder="https://www.example.com" />
                </div>
              </div>
            </div>

            <!-- Contact Information Section -->
            <div class="form-section">
              <h3 class="section-title">
                <i class="fas fa-address-card"></i>
                Contact Information
              </h3>

              <div class="form-group">
                <label for="contact_person" class="form-label required">Contact Person</label>
                <div class="input-wrapper">
                  <i class="fas fa-user input-icon"></i>
                  <input type="text" id="contact_person" name="contact_person" class="form-input with-icon" required />
                </div>
              </div>

              <div class="form-group">
                <label for="email" class="form-label required">Email Address</label>
                <div class="input-wrapper">
                  <i class="fas fa-envelope input-icon"></i>
                  <input type="email" id="email" name="email" class="form-input with-icon" required />
                </div>
              </div>

              <div class="form-group">
                <label for="phone" class="form-label required">Phone Number</label>
                <div class="input-wrapper">
                  <i class="fas fa-phone input-icon"></i>
                  <input type="tel" id="phone" name="phone" class="form-input with-icon" required />
                </div>
              </div>
            </div>
          </div>

          <!-- Full Width Sections -->
          <div class="form-section">
            <h3 class="section-title">
              <i class="fas fa-map-marker-alt"></i>
              Location & Profile
            </h3>

            <div class="form-group">
              <label for="address" class="form-label required">Company Address</label>
              <div class="input-wrapper">
                <i class="fas fa-map-marker-alt input-icon"></i>
                <input type="text" id="address" name="address" class="form-input with-icon" required />
              </div>
            </div>

            <div class="form-group">
              <label for="company_profile" class="form-label required">Company Profile</label>
              <textarea 
                id="company_profile" 
                name="company_profile" 
                class="form-input form-textarea" 
                rows="4" 
                placeholder="Tell us about your company, mission, values, and what makes you a great place to work..."
                required></textarea>
            </div>
          </div>

          <!-- Security Section -->
          <div class="form-section">
            <h3 class="section-title">
              <i class="fas fa-lock"></i>
              Account Security
            </h3>

            <div class="form-group">
              <label for="password" class="form-label required">Password</label>
              <div class="password-wrapper">
                <div class="input-wrapper">
                  <i class="fas fa-lock input-icon"></i>
                  <input type="password" id="password" name="password" class="form-input with-icon" required />
                  <i class="fas fa-eye password-toggle" id="passwordToggle"></i>
                </div>
                <div class="password-strength" id="passwordStrength">
                  <div class="password-strength-bar"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Terms and Submit -->
          <div class="terms-checkbox">
            <div class="checkbox-wrapper">
              <input type="checkbox" id="terms" required />
            </div>
            <div class="terms-text">
              I agree to the <a href="#" onclick="openTerms()">Terms of Service</a> and 
              <a href="#" onclick="openPrivacy()">Privacy Policy</a>. I understand that my company 
              information will be visible to students on the platform.
            </div>
          </div>

          <div class="submit-section">
            <button type="submit" class="submit-btn" id="submitBtn">
              <i class="fas fa-user-plus"></i>
              Create Account
            </button>
          </div>

          <div class="login-link">
            Already have an account? <a href="login.php">Sign in here</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Password toggle functionality
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('passwordToggle');
    const passwordStrength = document.getElementById('passwordStrength');

    passwordToggle.addEventListener('click', function() {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      
      this.classList.toggle('fa-eye');
      this.classList.toggle('fa-eye-slash');
    });

    // Password strength checker
    passwordInput.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;
      
      // Check password criteria
      if (password.length >= 8) strength++;
      if (password.match(/[a-z]/)) strength++;
      if (password.match(/[A-Z]/)) strength++;
      if (password.match(/[0-9]/)) strength++;
      if (password.match(/[^a-zA-Z0-9]/)) strength++;

      // Update strength indicator
      passwordStrength.className = 'password-strength';
      if (strength >= 2 && strength < 4) {
        passwordStrength.classList.add('weak');
      } else if (strength >= 4) {
        passwordStrength.classList.add(strength === 4 ? 'medium' : 'strong');
      }
    });

    // Form validation and submission
    const form = document.getElementById('registrationForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function(e) {
      // Disable submit button to prevent double submission
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Account...';
      
      // Basic client-side validation
      const requiredFields = form.querySelectorAll('[required]');
      let allValid = true;
      
      requiredFields.forEach(field => {
        if (!field.value.trim()) {
          allValid = false;
          field.focus();
          field.style.borderColor = '#e53e3e';
        } else {
          field.style.borderColor = '#38a169';
        }
      });

      if (!allValid) {
        e.preventDefault();
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
        alert('Please fill in all required fields.');
        return;
      }

      // Password strength validation
      const password = passwordInput.value;
      if (password.length < 8) {
        e.preventDefault();
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
        alert('Password must be at least 8 characters long.');
        passwordInput.focus();
        return;
      }

      // Add success animation
      form.classList.add('success-animation');
    });

    // Real-time validation feedback
    const inputs = document.querySelectorAll('.form-input');
    inputs.forEach(input => {
      input.addEventListener('blur', function() {
        if (this.hasAttribute('required') && !this.value.trim()) {
          this.style.borderColor = '#e53e3e';
        } else if (this.value.trim()) {
          this.style.borderColor = '#38a169';
        }
      });

      input.addEventListener('focus', function() {
        this.style.borderColor = '#667eea';
      });
    });

    // Auto-format phone number
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value.length >= 6) {
        value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
      } else if (value.length >= 3) {
        value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
      }
      e.target.value = value;
    });

    // Modal functions for terms and privacy
    function openTerms() {
      alert('Terms of Service would open in a modal or new page');
    }

    function openPrivacy() {
      alert('Privacy Policy would open in a modal or new page');
    }

    // Character count for company profile
    const profileTextarea = document.getElementById('company_profile');
    profileTextarea.addEventListener('input', function() {
      // You could add character count display here if needed
    });

    // Add loading states and animations
    document.addEventListener('DOMContentLoaded', function() {
      // Stagger animation for form sections
      const sections = document.querySelectorAll('.form-section');
      sections.forEach((section, index) => {
        section.style.animationDelay = `${index * 0.1}s`;
        section.style.animation = 'slideInUp 0.6s ease-out forwards';
      });
    });
  </script>
</body>
</html>