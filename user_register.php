<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/usermanager.php';
require_once 'includes/emailservice.php';
require_once 'includes/functions.php';

$db = new Database();
$connection = $db->connect();
$userManager = new UserManager($connection);
$emailService = new EmailService();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Use null coalescing operator to prevent undefined key warnings
    $username = sanitize($_POST['username'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $phone = sanitize($_POST['phone'] ?? '');
    $department = sanitize($_POST['department'] ?? '');
    $job_title = sanitize($_POST['job_title'] ?? '');
    $role = 'user'; // Fixed role for user registration
    
    // Validation
    if (empty($username) || empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'Username, name, email, and password are required';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } elseif (!validatePassword($password)) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores';
    } 

    // Process registration if no errors
    if (empty($error)) {
        $result = $userManager->register(
            $username,
            $email,
            $password,
            $name,
            $role,
            !empty($phone) ? $phone : null,
            !empty($department) ? $department : null,
            !empty($job_title) ? $job_title : null
        );
        
        if ($result['success']) {
            $success = 'User registration successful!';
            
            // Only try to send email if email service is properly configured
            if (isset($emailService) && method_exists($emailService, 'sendVerificationEmail')) {
                try {
                    $emailSent = $emailService->sendVerificationEmail($email, $name, $result['verification_token']);
                    if ($emailSent) {
                        $success = 'User registration successful! Please check your email to verify your account before logging in.';
                    } else {
                        $success .= ' Please verify your email manually or contact support for verification.';
                    }
                } catch (Exception $e) {
                    error_log("Email error: " . $e->getMessage());
                    $success .= ' Email verification will be sent shortly.';
                }
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration - Task Manager</title>
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
        
        .register-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            padding: 2rem;
            width: 100%;
            max-width: 600px;
            margin: 1rem auto;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h2 {
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
            color: #666;
            margin-bottom: 0;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px 0 0 10px;
            color: #666;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            width: 100%;
            color: white;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .btn-toggle {
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: transparent;
        }
        
        .btn-toggle:hover {
            background: #667eea;
            color: white;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 2rem;
        }
        
        .footer-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: #764ba2;
        }
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
        }
        
        .divider span {
            background: rgba(255, 255, 255, 0.95);
            padding: 0 1rem;
            color: #666;
            font-size: 0.9rem;
        }
        
        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .text-danger {
            color: #dc3545 !important;
        }
        
        .form-text {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .collapse {
            margin-top: 1rem;
        }
        
        .is-invalid {
            border-color: #dc3545;
        }
        
        .password-toggle {
            border-radius: 0 10px 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <div class="register-container">
                    <div class="register-header">
                        <div class="logo">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h2>Create User Account</h2>
                        <p>Join us and start managing your tasks efficiently</p>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <div><?php echo htmlspecialchars($success); ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="registrationForm">
                        <input type="hidden" name="role" value="user">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                           required placeholder="john_doe">
                                </div>
                                <div class="form-text">Letters, numbers, and underscores only</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                           required placeholder="john@example.com">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       required placeholder="John Doe">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           required placeholder="Enter password">
                                    <button class="btn btn-outline-secondary password-toggle" 
                                            type="button" 
                                            id="togglePassword">
                                        <i class="fas fa-eye" id="eyeIcon"></i>
                                    </button>
                                </div>
                                <div class="form-text">At least 6 characters</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           required placeholder="Confirm password">
                                    <button class="btn btn-outline-secondary password-toggle" 
                                            type="button" 
                                            id="toggleConfirmPassword">
                                        <i class="fas fa-eye" id="eyeIconConfirm"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Optional fields -->
                        <div class="mb-3">
                            <button type="button" class="btn btn-toggle" data-bs-toggle="collapse" data-bs-target="#optionalFields">
                                <i class="fas fa-plus me-2"></i>Add Optional Information
                            </button>
                        </div>
                        
                        <div class="collapse" id="optionalFields">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                               placeholder="+1234567890">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-building"></i>
                                        </span>
                                        <input type="text" class="form-control" id="department" name="department" 
                                               value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>" 
                                               placeholder="IT, Marketing, etc.">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="job_title" class="form-label">Job Title</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-briefcase"></i>
                                    </span>
                                    <input type="text" class="form-control" id="job_title" name="job_title" 
                                           value="<?php echo isset($_POST['job_title']) ? htmlspecialchars($_POST['job_title']) : ''; ?>" 
                                           placeholder="Developer, Manager, etc.">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-register">
                            <i class="fas fa-user-plus me-2"></i>Create User Account
                        </button>
                    </form>
                    
                    <div class="divider">
                        <span>or</span>
                    </div>
                    
                    <div class="footer-links">
                        <a href="login.php">Already have an account? Sign in</a>
                        <span class="mx-2">•</span>
                        <a href="admin_register.php">Admin Registration</a>
                        <span class="mx-2">•</span>
                        <a href="index.php">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword'); 
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const eyeIcon = document.getElementById('eyeIcon');
        const eyeIconConfirm = document.getElementById('eyeIconConfirm');   
        
        if (togglePassword && passwordInput && eyeIcon) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                eyeIcon.classList.toggle('fa-eye');
                eyeIcon.classList.toggle('fa-eye-slash');
            });
        }
        
        if (toggleConfirmPassword && confirmPasswordInput && eyeIconConfirm) {
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                eyeIconConfirm.classList.toggle('fa-eye');
                eyeIconConfirm.classList.toggle('fa-eye-slash');
            });
        }

        // Form validation
        const registrationForm = document.getElementById('registrationForm');
        if (registrationForm) {
            registrationForm.addEventListener('submit', function(event) {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                let isValid = true;
                
                // Clear previous validation states
                document.querySelectorAll('.is-invalid').forEach(field => {
                    field.classList.remove('is-invalid');
                });
                
                // Validate required fields
                const requiredFields = ['username', 'name', 'email', 'password', 'confirm_password'];
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && !field.value.trim()) {
                        field.classList.add('is-invalid');
                        isValid = false;
                    }
                });
                
                // Check if passwords match
                if (password !== confirmPassword) {
                    confirmPasswordInput.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Validate password length
                if (password.length < 6) {
                    passwordInput.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Email validation
                const emailField = document.getElementById('email');
                if (emailField && emailField.value) {
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(emailField.value)) {
                        emailField.classList.add('is-invalid');
                        isValid = false;
                    }
                }
                
                // Username validation
                const usernameField = document.getElementById('username');
                if (usernameField && usernameField.value) {
                    const username = usernameField.value.trim();
                    if (username.length < 3) {
                        usernameField.classList.add('is-invalid');
                        isValid = false;
                    } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                        usernameField.classList.add('is-invalid');
                        isValid = false;
                    }
                }
                
                // If form is not valid, prevent submission
                if (!isValid) {
                    event.preventDefault();
                    
                    // Scroll to first invalid field
                    const firstInvalidField = document.querySelector('.is-invalid');
                    if (firstInvalidField) {
                        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalidField.focus();
                    }
                    
                    return false;
                }
                
                // Show loading state
                const submitButton = document.querySelector('.btn-register');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
                }
            });
        }

        // Real-time validation feedback
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirmPassword = this.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });
        }
    </script>
</body>
</html>