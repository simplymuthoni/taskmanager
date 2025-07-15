<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/usermanager.php';
require_once '../includes/emailservice.php';
require_once '../includes/functions.php';
require_once '../includes/adminkeyhandler.php';
require_once '../includes/adminmanager.php';

$db = new Database();
$connection = $db->connect();
$userManager = new UserManager($connection);
$emailService = new EmailService();

$error = '';
$success = '';
$keyData = null;

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
    $role = 'admin'; // Fixed role for admin registration
    $admin_key = $_POST['admin_key'] ?? '';
    
    // Validation
    if (empty($username) || empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'Username, name, email, and password are required';
    } elseif (!validateEmail($email)) {
        $error = 'Invalid email format';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores';
    } elseif (empty($phone)) {
        $error = 'Phone number is required for admin registration';
    } elseif (empty($department)) {
        $error = 'Department is required for admin registration';
    } elseif (empty($job_title)) {
        $error = 'Job title is required for admin registration';
    } elseif (empty($admin_key)) {
        $error = 'Admin registration key is required';
    }

    // Admin password validation
    if (empty($error)) {
        if (strlen($password) < (defined('ADMIN_PASSWORD_MIN_LENGTH') ? ADMIN_PASSWORD_MIN_LENGTH : 8)) {
            $error = 'Admin passwords must be at least 8 characters long';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
            $error = 'Admin password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
        }
    }

    // Admin key validation with multi-key support
    if (empty($error)) {
        try {
            // Initialize admin key handler
            $adminKeyHandler = new AdminKeyHandler($connection);
            
            // Validate admin key with restrictions
            $keyValidation = $adminKeyHandler->validateAdminKey(
                $admin_key, 
                $email, 
                $department, 
                $_SERVER['REMOTE_ADDR']
            );

            if (!$keyValidation['valid']) {
                $error = $keyValidation['message'];
            } else {
                // Key is valid, store key data for later use
                $keyData = $keyValidation['key_data'];
                
                // Additional validation based on key restrictions
                if ($keyData['department_restriction'] && empty($department)) {
                    $error = 'Department is required for this admin registration key';
                } elseif ($keyData['department_restriction'] && $department !== $keyData['department_restriction']) {
                    $error = 'Department must be: ' . $keyData['department_restriction'];
                }
            }
        } catch (Exception $e) {
            error_log('AdminKeyHandler error: ' . $e->getMessage());
            $error = 'An error occurred while validating the admin key';
        }
    }

    // Process registration if no errors
    if (empty($error)) {
        $result = $userManager->register(
            $username,
            $email,
            $password,
            $name,
            $role,
            $phone,
            $department,
            $job_title
        );
        
        if ($result['success']) {
            $success = 'Admin registration successful!';

            // Handle admin registration specifics
            if ($keyData) {
                // Log admin registration for security audit
                $logData = [
                    'username' => $username,
                    'email' => $email,
                    'department' => $department,
                    'job_title' => $job_title,
                    'key_used' => $keyData['uid'],
                    'key_created_by' => $keyData['created_by'],
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                error_log("Admin registration: " . json_encode($logData));
                
                // Record the admin registration in database
                $adminKeyHandler->recordAdminRegistration($result['user_uid'], $keyData['uid'], $logData);
                
                // Add key-specific success message
                if ($keyData['department_restriction']) {
                    $success .= ' (Registered for ' . $keyData['department_restriction'] . ' department)';
                }

                // Check if key has reached max uses
                if ($keyData['max_uses'] && ($keyData['usage_count'] + 1) >= $keyData['max_uses']) {
                    error_log("Admin key {$keyData['uid']} has reached maximum usage limit");
                    
                    // Optionally disable the key after max uses
                    $adminKeyHandler->disableKey($keyData['uid'], 'Maximum usage limit reached');
                }
            }
            
            // Only try to send email if email service is properly configured
            if (isset($emailService) && method_exists($emailService, 'sendVerificationEmail')) {
                try {
                    $emailSent = $emailService->sendVerificationEmail($email, $name, $result['verification_token']);
                    if ($emailSent) {
                        $success = 'Admin registration successful! Please check your email to verify your account before logging in.';
                    } else {
                        $success .= ' Please verify your email manually or contact support for verification.';
                    }
                } catch (Exception $e) {
                    error_log("Email error: " . $e->getMessage());
                    $success .= ' Email verification will be sent shortly.';
                }
            }
            
            // Log admin registration for security
            error_log("Admin registration: Username: $username, Email: $email, IP: " . $_SERVER['REMOTE_ADDR']);
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
    <title>Admin Registration - Task Manager</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .registration-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: linear-gradient(45deg, #ff6b6b, #ee5a24);
            color: white;
        }
        .alert-success {
            background: linear-gradient(45deg, #51cf66, #40c057);
            color: white;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        .required {
            color: #dc3545;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 10px;
        }
        .logo h2 {
            color: #495057;
            font-weight: 700;
        }
        .input-group {
            margin-bottom: 20px;
        }
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
            border-radius: 10px 0 0 10px;
        }
        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }
        .password-requirements {
            font-size: 14px;
            color: #6c757d;
            margin-top: 5px;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="registration-container">
                    <div class="logo">
                        <i class="fas fa-user-shield"></i>
                        <h2>Admin Registration</h2>
                        <p class="text-muted">Create your administrator account</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" name="username" 
                                           placeholder="Username" 
                                           value="<?php echo htmlspecialchars($username ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-id-card"></i>
                                    </span>
                                    <input type="text" class="form-control" name="name" 
                                           placeholder="Full Name" 
                                           value="<?php echo htmlspecialchars($name ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control" name="email" 
                                           placeholder="Email Address" 
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <input type="tel" class="form-control" name="phone" 
                                           placeholder="Phone Number" 
                                           value="<?php echo htmlspecialchars($phone ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-building"></i>
                                    </span>
                                    <select class="form-control" name="department" required>
                                        <option value="">Select Department</option>
                                        <option value="IT" <?php echo ($department ?? '') === 'IT' ? 'selected' : ''; ?>>Information Technology</option>
                                        <option value="HR" <?php echo ($department ?? '') === 'HR' ? 'selected' : ''; ?>>Human Resources</option>
                                        <option value="Finance" <?php echo ($department ?? '') === 'Finance' ? 'selected' : ''; ?>>Finance</option>
                                        <option value="Marketing" <?php echo ($department ?? '') === 'Marketing' ? 'selected' : ''; ?>>Marketing</option>
                                        <option value="Operations" <?php echo ($department ?? '') === 'Operations' ? 'selected' : ''; ?>>Operations</option>
                                        <option value="Sales" <?php echo ($department ?? '') === 'Sales' ? 'selected' : ''; ?>>Sales</option>
                                        <option value="Support" <?php echo ($department ?? '') === 'Support' ? 'selected' : ''; ?>>Customer Support</option>
                                        <option value="Management" <?php echo ($department ?? '') === 'Management' ? 'selected' : ''; ?>>Management</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-briefcase"></i>
                                    </span>
                                    <input type="text" class="form-control" name="job_title" 
                                           placeholder="Job Title" 
                                           value="<?php echo htmlspecialchars($job_title ?? ''); ?>" 
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" name="password" 
                                           placeholder="Password" required>
                                </div>
                                <div class="password-requirements">
                                    <small>Must contain: uppercase, lowercase, number, and special character</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" name="confirm_password" 
                                           placeholder="Confirm Password" required>
                                </div>
                            </div>
                        </div>

                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-key"></i>
                            </span>
                            <input type="text" class="form-control" name="admin_key" 
                                   placeholder="Admin Registration Key" 
                                   value="<?php echo htmlspecialchars($admin_key ?? ''); ?>" 
                                   required>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i> 
                            You need a valid admin registration key to create an administrator account.
                        </small>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-user-plus me-2"></i>
                                Register Admin Account
                            </button>
                        </div>
                    </form>

                    <div class="back-link">
                        <a href="../login.php">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add some interactive feedback
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input, select');
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });
            
            // Password strength indicator
            const passwordInput = document.querySelector('input[name="password"]');
            const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const hasUpper = /[A-Z]/.test(password);
                const hasLower = /[a-z]/.test(password);
                const hasNumber = /\d/.test(password);
                const hasSpecial = /[@$!%*?&]/.test(password);
                const minLength = password.length >= 8;
                
                if (hasUpper && hasLower && hasNumber && hasSpecial && minLength) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    if (password.length > 0) {
                        this.classList.add('is-invalid');
                    }
                }
            });
            
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value === passwordInput.value && this.value.length > 0) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    if (this.value.length > 0) {
                        this.classList.add('is-invalid');
                    }
                }
            });
        });
    </script>
</body>
</html>