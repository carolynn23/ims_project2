<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Your Role - EduPortal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            width: 100%;
            text-align: center;
        }

        .header {
            margin-bottom: 50px;
            color: white;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            z-index: 0;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(10px);
        }

        .logo-icon svg {
            width: 30px;
            height: 30px;
            fill: white;
        }

        .header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 18px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
            max-width: 500px;
            margin: 0 auto;
        }

        .role-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .role-card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            border: 3px solid transparent;
        }

        .role-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.15);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--role-color);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .role-card:hover::before {
            transform: scaleX(1);
        }

        .role-card.student {
            --role-color: linear-gradient(135deg, #4CAF50, #45a049);
        }

        .role-card.employer {
            --role-color: linear-gradient(135deg, #2196F3, #1976D2);
        }

        .role-card.alumni {
            --role-color: linear-gradient(135deg, #FF9800, #F57C00);
        }

        .role-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--role-color);
            color: white;
            font-size: 36px;
            transition: all 0.3s ease;
        }

        .role-card:hover .role-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .role-card h3 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
            transition: color 0.3s ease;
        }

        .role-card:hover h3 {
            background: var(--role-color);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .role-description {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
        }

        .role-features {
            list-style: none;
            text-align: left;
        }

        .role-features li {
            color: #555;
            font-size: 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .role-features li::before {
            content: '✓';
            color: var(--role-color);
            font-weight: bold;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .cta-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-top: 30px;
        }

        .cta-section h3 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .cta-section p {
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .login-link {
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .login-link:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.6);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }

            .header h1 {
                font-size: 28px;
            }

            .header p {
                font-size: 16px;
            }

            .role-cards {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .role-card {
                padding: 30px 20px;
            }

            .role-icon {
                width: 70px;
                height: 70px;
                font-size: 30px;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 24px;
            }

            .role-card h3 {
                font-size: 20px;
            }

            .role-description {
                font-size: 14px;
            }
        }

        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .floating-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }

        .floating-circle:nth-child(1) {
            top: 10%;
            left: 10%;
            width: 60px;
            height: 60px;
            animation-delay: 0s;
        }

        .floating-circle:nth-child(2) {
            top: 20%;
            right: 10%;
            width: 40px;
            height: 40px;
            animation-delay: 2s;
        }

        .floating-circle:nth-child(3) {
            bottom: 20%;
            left: 20%;
            width: 80px;
            height: 80px;
            animation-delay: 4s;
        }

        .floating-circle:nth-child(4) {
            bottom: 10%;
            right: 20%;
            width: 50px;
            height: 50px;
            animation-delay: 1s;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px) rotate(0deg);
                opacity: 0.3;
            }
            50% {
                transform: translateY(-20px) rotate(180deg);
                opacity: 0.6;
            }
        }

        .role-card::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            transform: translate(-50%, -50%);
            transition: all 0.3s ease;
            border-radius: 50%;
        }

        .role-card:hover::after {
            width: 300px;
            height: 300px;
        }
    </style>
</head>
<body>
    <div class="floating-elements">
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
        <div class="floating-circle"></div>
    </div>

    <div class="container">
        <div class="header">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2L2 7V10C2 17 6.84 23.74 12 23.74S22 17 22 10V7L12 2Z"/>
                </svg>
            </div>
            <h1>Choose Your Path</h1>
            <p>Select your role to create an account tailored to your needs and start your journey with EduPortal</p>
        </div>

        <div class="role-cards">
            <a href="register-student.php" class="role-card student">
                <div class="role-icon">🎓</div>
                <h3>Student</h3>
                <p class="role-description">
                    Join as a student to discover internships, build your profile, and connect with potential employers.
                </p>
                <ul class="role-features">
                    <li>Browse internship opportunities</li>
                    <li>Create and manage your profile</li>
                    <li>Apply to positions</li>
                    <li>Track application status</li>
                    <li>Access career resources</li>
                </ul>
            </a>

            <a href="register-employer.php" class="role-card employer">
                <div class="role-icon">💼</div>
                <h3>Employer</h3>
                <p class="role-description">
                    Register as an employer to post internships, discover talented students, and build your team.
                </p>
                <ul class="role-features">
                    <li>Post internship positions</li>
                    <li>Search student profiles</li>
                    <li>Manage applications</li>
                    <li>Schedule interviews</li>
                    <li>Company profile management</li>
                </ul>
            </a>

            <a href="register-alumni.php" class="role-card alumni">
                <div class="role-icon">🌟</div>
                <h3>Alumni</h3>
                <p class="role-description">
                    Join as an alumni to mentor students, share experiences, and give back to the community.
                </p>
                <ul class="role-features">
                    <li>Mentor current students</li>
                    <li>Share career experiences</li>
                    <li>Network with peers</li>
                    <li>Post job opportunities</li>
                    <li>Alumni community access</li>
                </ul>
            </a>
        </div>

        <div class="cta-section">
            <h3>Already have an account?</h3>
            <p>Sign in to access your dashboard and continue where you left off.</p>
            <a href="login.php" class="login-link">Sign In</a>
        </div>
    </div>

    <script>
        // Add smooth hover effects
        const roleCards = document.querySelectorAll('.role-card');
        
        roleCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
            
            // Add click animation
            card.addEventListener('click', function() {
                this.style.transform = 'translateY(-5px) scale(0.98)';
                setTimeout(() => {
                    this.style.transform = 'translateY(-10px) scale(1.02)';
                }, 150);
            });
        });

        // Add loading animation when cards are clicked
        roleCards.forEach(card => {
            card.addEventListener('click', function() {
                const icon = this.querySelector('.role-icon');
                icon.style.transform = 'scale(1.2) rotate(360deg)';
                
                setTimeout(() => {
                    window.location.href = this.href;
                }, 300);
            });
        });

        // Parallax effect for floating elements
        document.addEventListener('mousemove', function(e) {
            const circles = document.querySelectorAll('.floating-circle');
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            circles.forEach((circle, index) => {
                const speed = (index + 1) * 0.5;
                const xMove = (x - 0.5) * speed * 20;
                const yMove = (y - 0.5) * speed * 20;
                
                circle.style.transform = `translate(${xMove}px, ${yMove}px)`;
            });
        });
    </script>
</body>
</html>