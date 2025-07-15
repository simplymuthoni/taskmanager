<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/usermanager.php';
require_once 'includes/functions.php';

$db = new Database();
$connection = $db->connect();
$userManager = new UserManager($connection);

if ($userManager->isLoggedIn()) {
    if ($userManager->isAdmin()) {
        redirectTo('admin/dashboard.php');
    } else {
        redirectTo('user/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Manager - Welcome</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 2rem 0;
        }
        
        .welcome-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            margin: 1rem auto;
        }
        
        .welcome-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }
        
        .welcome-header h1 {
            color: #333;
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .welcome-header p {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 0;
            line-height: 1.6;
        }
        
        .btn-welcome {
            border-radius: 15px;
            padding: 1rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
            font-size: 1.1rem;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .btn-welcome::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-welcome:hover::before {
            left: 100%;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin-bottom: 1rem;
        }
        
        .btn-primary-custom:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-outline-custom {
            background: transparent;
            color: #667eea;
            border: 3px solid #667eea;
        }
        
        .btn-outline-custom:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.3);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        
        .logo:hover {
            transform: rotate(360deg) scale(1.1);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.5);
        }
        
        .features {
            display: flex;
            justify-content: space-around;
            margin: 2rem 0;
            padding: 1.5rem 0;
            border-top: 1px solid #e9ecef;
        }
        
        .feature-item {
            text-align: center;
            flex: 1;
            padding: 0 1rem;
        }
        
        .feature-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        
        .feature-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .feature-text {
            font-size: 0.9rem;
            color: #666;
            font-weight: 500;
        }
        
        .divider {
            text-align: center;
            margin: 2rem 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, #e9ecef, transparent);
        }
        
        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 1rem;
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .footer-text {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-top: 1.5rem;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 1rem;
        }
        
        .footer-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
            margin: 0 0.5rem;
        }
        
        .footer-links a:hover {
            color: #764ba2;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        
        .btn-icon {
            margin-right: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .welcome-container {
                padding: 2rem;
                margin: 0.5rem;
            }
            
            .welcome-header h1 {
                font-size: 2rem;
            }
            
            .features {
                flex-direction: column;
                gap: 1rem;
            }
            
            .feature-item {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="welcome-container">
                    <div class="welcome-header">
                        <div class="logo floating">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <h1>Task Manager</h1>
                        <p>Organize your tasks, manage your team, and boost productivity with our comprehensive task management system.</p>
                    </div>
                    
                    <div class="features">
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="feature-text">Organize Tasks</div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="feature-text">Team Management</div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="feature-text">Track Progress</div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-3">
                        <a href="login.php" class="btn btn-welcome btn-primary-custom">
                            <i class="fas fa-sign-in-alt btn-icon"></i>Login to Your Account
                        </a>
                        <a href="user_register.php" class="btn btn-welcome btn-outline-custom">
                            <i class="fas fa-user-plus btn-icon"></i>Create New Account
                        </a>
                    </div>
                    
                    <div class="divider">
                        <span>Quick Access</span>
                    </div>
                    
                    <div class="footer-links">
                        <a href="/admin/register.php">
                            <i class="fas fa-user-shield me-1"></i>Admin Registration
                        </a>
                        <span class="mx-2">•</span>
                        <a href="#" onclick="showFeatures()">
                            <i class="fas fa-info-circle me-1"></i>Learn More
                        </a>
                    </div>
                    
                    <div class="footer-text">
                        Start managing your tasks efficiently today!
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add smooth hover effects and animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add ripple effect to buttons
            const buttons = document.querySelectorAll('.btn-welcome');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = x + 'px';
                    ripple.style.top = y + 'px';
                    ripple.classList.add('ripple');
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            });
            
            // Add floating animation to feature icons
            const featureIcons = document.querySelectorAll('.feature-icon');
            featureIcons.forEach((icon, index) => {
                icon.style.animationDelay = `${index * 0.2}s`;
                icon.classList.add('floating');
            });
        });
        
        function showFeatures() {
            alert('Features:\n\n• Task Creation & Management\n• Team Collaboration\n• Progress Tracking\n• Deadline Reminders\n• File Attachments\n• Priority Settings\n• Status Updates\n• Reporting & Analytics');
        }
        
        // Add some interactive elements
        const logo = document.querySelector('.logo');
        if (logo) {
            logo.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.1) rotate(10deg)';
            });
            
            logo.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1) rotate(0deg)';
            });
        }
    </script>
    
    <style>
        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
            pointer-events: none;
        }
        
        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }
    </style>
</body>
</html>