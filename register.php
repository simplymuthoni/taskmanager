<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/usermanager.php';
require_once 'includes/emailservice.php';
require_once 'includes/functions.php';
require_once 'includes/adminkeyhandler.php';
require_once 'includes/adminmanager.php';

$db = new Database();
$connection = $db->connect();
$userManager = new UserManager($connection);
$emailService = new EmailService();

$error = '';
$success = '';
$keydata = null;

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
    $role = sanitize($_POST['role'] ?? 'user'); 
    $admin_key = $_POST['admin_key'] ?? '';
    
    // Validation
    if (empty($username) || empty($name) || empty($last_name) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = 'Username, name, last name, email, and password are required';
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

    // Admin role validation with multi-key support
    if (empty($error) && $role === 'admin') {
        if (empty($admin_key)) {
            $error = 'Admin registration key is required for admin accounts';
        } else {
            // Initialize admin key handler
            $adminKeyHandler = new AdminKeyHandler($pdo);
            
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
        try{
                // Key is valid, store key data for later use
                $keyData = $keyValidation['key_data'];
                
                // Additional validation based on key restrictions
                if ($keyData['department_restriction'] && empty($department)) {
                    $error = 'Department is required for this admin registration key';
                } elseif (empty($job_title)) {
                    $error = 'Job title is required for admin registration';
                } elseif (strlen($password) < (defined('ADMIN_PASSWORD_MIN_LENGTH') ? ADMIN_PASSWORD_MIN_LENGTH : 8)) {
                    $error = 'Admin passwords must be at least 8 characters long';
                } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
                    $error = 'Admin password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
                }
                
                // If key has department restriction, enforce it
                if ($keyData['department_restriction'] && $department !== $keyData['department_restriction']) {
                    $error = 'Department must be: ' . $keyData['department_restriction'];
                }
            } catch (Exception $e) {
            error_log('AdminKeyHandler error: ' . $e->getMessage());
            $error = 'An error occurred while validating the admin key';
        }
        }  
    }
        // Process registration if no errors
        if (empty($error)) {
            $result = $userManager->register(
                $username,
                $email,
                $password,
                $name,
                $last_name,
                $role,
                !empty($phone) ? $phone : null,
                !empty($department) ? $department : null,
                !empty($job_title) ? $job_title : null
            );
            
            if ($result['success']) {
                $roleText = $role === 'admin' ? 'Admin' : 'User';
                $success = $roleText . ' registration successful!';

                // Handle admin registration specifics
            if ($role === 'admin' && $keyData) {
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
                
                /// Record the admin registration in database
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
                
                // // Send notification to super admin about new admin registration
                // try {
                //     $adminKeyHandler->notifyAdminRegistration($logData);
                // } catch (Exception $e) {
                //     error_log("Failed to send admin registration notification: " . $e->getMessage());
                // }
            }
        
                // Only try to send email if email service is properly configured
                if (isset($emailService) && method_exists($emailService, 'sendVerificationEmail')) {
                    try {
                        $emailSent = $emailService->sendVerificationEmail($email, $name, $result['verification_token']);
                        if ($emailSent) {
                            $success = $roleText . ' registration successful! Please check your email to verify your account before logging in.';
                        } else {
                            $success .= ' Please verify your email manually or contact support for verification.';
                        }
                    } catch (Exception $e) {
                        error_log("Email error: " . $e->getMessage());
                        $success .= ' Email verification will be sent shortly.';
                    }
                }
                
                // Log admin registration for security
                if ($role === 'admin') {
                    error_log("Admin registration: Username: $username, Email: $email, IP: " . $_SERVER['REMOTE_ADDR']);
                }
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Task Manager</title>
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
            max-width: 700px;
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
        
        .form-select {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
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
        
        .btn-register.admin {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        
        .btn-register.admin:hover {
            box-shadow: 0 10px 25px rgba(220, 53, 69, 0.3);
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
        
        .logo.admin {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
        
        .role-selector {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .role-option {
            cursor: pointer;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .role-option:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        
        .role-option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.15);
        }
        
        .role-option.admin.selected {
            border-color: #dc3545;
            background: rgba(220, 53, 69, 0.15);
        }
        
        .admin-fields {
            background: rgba(220, 53, 69, 0.05);
            border: 2px solid rgba(220, 53, 69, 0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .admin-warning {
            background: rgba(255, 193, 7, 0.1);
            border: 2px solid rgba(255, 193, 7, 0.5);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .password-requirements {
            background: rgba(23, 162, 184, 0.1);
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 0.5rem;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 1.5rem;
        }
        
        .password-requirements li {
            font-size: 0.875rem;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-md-11">
                <div class="register-container">
                    <div class="register-header">
                        <div class="logo" id="logoIcon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <h2 id="headerTitle">Create Account</h2>
                        <p id="headerSubtitle">Join us and start managing your tasks efficiently</p>
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
                        <!-- Role Selection -->
                        <div class="role-selector">
                            <h6 class="mb-3"><i class="fas fa-user-tag me-2"></i>Account Type</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="role-option selected" data-role="user">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user me-2 text-primary"></i>
                                            <div>
                                                <strong>Regular User</strong>
                                                <div class="text-muted small">Standard access to task management</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="role-option admin" data-role="admin">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-crown me-2 text-danger"></i>
                                            <div>
                                                <strong>Administrator</strong>
                                                <div class="text-muted small">Full system access and management</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="role" id="selectedRole" value="user">
                        </div>
                        
                        <!-- Admin Warning -->
                        <div class="admin-warning d-none" id="adminWarning">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                                <div>
                                    <strong>Administrator Registration</strong>
                                    <div class="text-muted small">Admin accounts have full system access. Please ensure you have proper authorization.</div>
                                </div>
                            </div>
                        </div>
                        
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
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                           required placeholder="John">
                                </div>
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
                                <div class="form-text" id="passwordHelp">At least 6 characters</div>
                                <div class="password-requirements d-none" id="adminPasswordReq">
                                    <strong>Admin Password Requirements:</strong>
                                    <ul>
                                        <li>At least 8 characters long</li>
                                        <li>Contains uppercase and lowercase letters</li>
                                        <li>Contains at least one number</li>
                                        <li>Contains at least one special character (@$!%*?&)</li>
                                    </ul>
                                </div>
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
                        
                        <!-- Admin Key Field -->
                        <div class="d-none" id="adminKeyField">
                            <div class="mb-3">
                                <label for="admin_key" class="form-label">Admin Registration Key <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-key"></i>
                                    </span>
                                    <input type="password" class="form-control" id="admin_key" name="admin_key" 
                                           placeholder="Enter admin registration key">
                                </div>
                                <div class="form-text">Contact system administrator for the registration key</div>
                            </div>
                        </div>
                        
                        <!-- Required fields for admin -->
                        <div class="admin-fields d-none" id="adminRequiredFields">
                            <h6 class="mb-3 text-danger"><i class="fas fa-exclamation-circle me-2"></i>Required Admin Information</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="admin_phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" class="form-control" id="admin_phone" name="phone" 
                                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                                               placeholder="+1234567890">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="admin_department" class="form-label">Department <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-building"></i>
                                        </span>
                                        <select class="form-control" id="admin_department" name="department" required>
                                            <option value="" disabled selected>Select department</option>
                                            <option value="IT" <?php echo (isset($_POST['department']) && $_POST['department'] === 'IT') ? 'selected' : ''; ?>>IT</option>
                                            <option value="HR" <?php echo (isset($_POST['department']) && $_POST['department'] === 'HR') ? 'selected' : ''; ?>>HR</option>
                                            <option value="Finance" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Finance') ? 'selected' : ''; ?>>Finance</option>
                                            <option value="Operation" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Operation') ? 'selected' : ''; ?>>Operation</option>
                                            <option value="Marketing" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Marketing') ? 'selected' : ''; ?>>Marketing</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="admin_job_title" class="form-label">Job Title <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-briefcase"></i>
                                    </span>
                                    <input type="text" class="form-control" id="admin_job_title" name="job_title" 
                                           value="<?php echo isset($_POST['job_title']) ? htmlspecialchars($_POST['job_title']) : ''; ?>" 
                                           placeholder="System Administrator, IT Manager, etc.">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Optional fields for regular users -->
                        <div id="optionalFieldsSection">
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
                        </div>
                        
                        <button type="submit" class="btn btn-register" id="submitBtn">
                            <i class="fas fa-user-plus me-2"></i>Create Account
                        </button>
                    </form>
                    
                    <div class="divider">
                        <span>or</span>
                    </div>
                    
                    <div class="footer-links">
                        <a href="login.php">Already have an account? Sign in</a>
                        <span class="mx-2">•</span>
                        <a href="index.php">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
   <script>
    // Initialize on Page Load
    document.addEventListener('DOMContentLoaded', function() {
        toggleAdminKey();
        initializeDefaultRole();
    });

    // Initialize default role if none selected
    function initializeDefaultRole() {
        const selectedRole = document.getElementById('selectedRole').value;
        if (!selectedRole) {
            // Default to user role
            const userOption = document.querySelector('.role-option[data-role="user"]');
            if (userOption) {
                userOption.click();
            }
        }
    }

    // Toggle admin key function
    function toggleAdminKey() {
        const role = document.getElementById('selectedRole').value;
        const adminKeyGroup = document.getElementById('admin-key-group');
        const adminKeyInput = document.getElementById('admin_key');
        
        if (role === 'admin') {
            adminKeyGroup.style.display = 'block';
            adminKeyInput.required = true;
        } else {
            adminKeyGroup.style.display = 'none';
            adminKeyInput.required = false;
            adminKeyInput.value = '';
        }
    }

    // Role selection functionality
    const roleOptions = document.querySelectorAll('.role-option');
    const selectedRoleInput = document.getElementById('selectedRole');
    const logoIcon = document.getElementById('logoIcon');
    const headerTitle = document.getElementById('headerTitle');
    const headerSubtitle = document.getElementById('headerSubtitle');
    const submitBtn = document.getElementById('submitBtn');
    const adminWarning = document.getElementById('adminWarning');
    const adminKeyField = document.getElementById('adminKeyField');
    const adminRequiredFields = document.getElementById('adminRequiredFields');
    const optionalFieldsSection = document.getElementById('optionalFieldsSection');
    const passwordHelp = document.getElementById('passwordHelp');
    const adminPasswordReq = document.getElementById('adminPasswordReq');
    
    roleOptions.forEach(option => {
        option.addEventListener('click', function() {
            const role = this.dataset.role;
            
            // Remove selected class from all options
            roleOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Add selected class to clicked option
            this.classList.add('selected');
            
            // Update hidden input
            selectedRoleInput.value = role;
            
            // Update UI based on role
            updateUIForRole(role);
        });
    });

    // Update UI based on selected role
    function updateUIForRole(role) {
        if (role === 'admin') {
            // Admin UI updates
            logoIcon.classList.add('admin');
            headerTitle.textContent = 'Create Admin Account';
            headerSubtitle.textContent = 'Administrator registration with full system access';
            submitBtn.classList.add('admin');
            submitBtn.innerHTML = '<i class="fas fa-crown me-2"></i>Create Admin Account';
            adminWarning.classList.remove('d-none');
            adminKeyField.classList.remove('d-none');
            adminRequiredFields.classList.remove('d-none');
            optionalFieldsSection.classList.add('d-none');
            passwordHelp.textContent = 'At least 8 characters with complexity requirements';
            adminPasswordReq.classList.remove('d-none');
            
            // Make admin fields required
            setAdminFieldsRequired(true);
        } else {
            // User UI updates
            logoIcon.classList.remove('admin');
            headerTitle.textContent = 'Create User Account';
            headerSubtitle.textContent = 'Standard user registration for task management';
            submitBtn.classList.remove('admin');
            submitBtn.innerHTML = '<i class="fas fa-user-plus me-2"></i>Create User Account';
            adminWarning.classList.add('d-none');
            adminKeyField.classList.add('d-none');
            adminRequiredFields.classList.add('d-none');
            optionalFieldsSection.classList.remove('d-none');
            passwordHelp.textContent = 'At least 6 characters';
            adminPasswordReq.classList.add('d-none');
            
            // Remove required attributes from admin fields
            setAdminFieldsRequired(false);
        }
        
        // Update admin key visibility
        toggleAdminKey();
    }

    // Helper function to set admin fields required status
    function setAdminFieldsRequired(required) {
        const adminFields = ['admin_phone', 'admin_department', 'admin_job_title'];
        adminFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                if (required) {
                    field.setAttribute('required', 'required');
                } else {
                    field.removeAttribute('required');
                    field.classList.remove('is-invalid');
                    field.setCustomValidity('');
                }
            }
        });
    }

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

    // Password validation functions
    function validatePassword(password, role) {
        const minLength = role === 'admin' ? 8 : 6;
        
        if (password.length < minLength) {
            return {
                isValid: false,
                message: `Password must be at least ${minLength} characters long`
            };
        }
        
        if (role === 'admin') {
            // Admin password complexity requirements
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumbers = /\d/.test(password);
            const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            
            if (!hasUppercase || !hasLowercase || !hasNumbers || !hasSpecialChar) {
                return {
                    isValid: false,
                    message: 'Admin password must contain uppercase, lowercase, numbers, and special characters'
                };
            }
        }
        
        return { isValid: true, message: '' };
    }

    // Validate common required fields
    function validateCommonFields() {
        const requiredFields = ['username', 'email', 'password', 'confirm_password'];
        let isValid = true;
        
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && !field.value.trim()) {
                field.classList.add('is-invalid');
                field.setCustomValidity('This field is required');
                isValid = false;
            } else if (field) {
                field.classList.remove('is-invalid');
                field.setCustomValidity('');
            }
        });
        
        return isValid;
    }

    // Validate user-specific fields
    function validateUserFields() {
        // User fields are generally optional, but we can add validation if needed
        // For now, just clear any previous validation errors
        const userFields = ['phone', 'department', 'job_title'];
        userFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.classList.remove('is-invalid');
                field.setCustomValidity('');
            }
        });
        return true;
    }

    // Validate admin-specific fields
    function validateAdminFields() {
        const adminFields = [
            { id: 'admin_key', name: 'Admin key' },
            { id: 'admin_phone', name: 'Phone number' },
            { id: 'admin_department', name: 'Department' },
            { id: 'admin_job_title', name: 'Job title' }
        ];
        
        let isValid = true;
        
        adminFields.forEach(field => {
            const fieldElement = document.getElementById(field.id);
            if (fieldElement) {
                if (!fieldElement.value.trim()) {
                    fieldElement.classList.add('is-invalid');
                    fieldElement.setCustomValidity(`${field.name} is required for admin registration`);
                    isValid = false;
                } else {
                    fieldElement.classList.remove('is-invalid');
                    fieldElement.setCustomValidity('');
                }
            }
        });
        
        return isValid;
    }

    // Main form validation
    const registrationForm = document.getElementById('registrationForm');
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(event) {
            const role = selectedRoleInput.value;
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            let isValid = true;
            
            console.log('Form submitted with role:', role);
            
            // Clear previous validation states
            document.querySelectorAll('.is-invalid').forEach(field => {
                field.classList.remove('is-invalid');
                field.setCustomValidity('');
            });
            
            // Validate common fields
            if (!validateCommonFields()) {
                isValid = false;
            }
            
            // Check if passwords match
            if (password !== confirmPassword) {
                confirmPasswordInput.classList.add('is-invalid');
                confirmPasswordInput.setCustomValidity('Passwords do not match');
                isValid = false;
            }
            
            // Validate password complexity
            const passwordValidation = validatePassword(password, role);
            if (!passwordValidation.isValid) {
                passwordInput.classList.add('is-invalid');
                passwordInput.setCustomValidity(passwordValidation.message);
                isValid = false;
            }
            
            // Role-specific validation
            if (role === 'admin') {
                if (!validateAdminFields()) {
                    isValid = false;
                }
            } else if (role === 'user') {
                if (!validateUserFields()) {
                    isValid = false;
                }
            }
            
            // Email validation
            const emailField = document.getElementById('email');
            if (emailField && emailField.value) {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(emailField.value)) {
                    emailField.classList.add('is-invalid');
                    emailField.setCustomValidity('Please enter a valid email address');
                    isValid = false;
                }
            }
            
            // Username validation
            const usernameField = document.getElementById('username');
            if (usernameField && usernameField.value) {
                const username = usernameField.value.trim();
                if (username.length < 3) {
                    usernameField.classList.add('is-invalid');
                    usernameField.setCustomValidity('Username must be at least 3 characters long');
                    isValid = false;
                } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                    usernameField.classList.add('is-invalid');
                    usernameField.setCustomValidity('Username can only contain letters, numbers, and underscores');
                    isValid = false;
                }
            }
            
            // If form is not valid, prevent submission
            if (!isValid) {
                event.preventDefault();
                console.log('Form validation failed, preventing submission');
                
                // Scroll to first invalid field
                const firstInvalidField = document.querySelector('.is-invalid');
                if (firstInvalidField) {
                    firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalidField.focus();
                }
                
                return false;
            }
            
            console.log('Form validation passed, submitting');
            
            // Optional: Show loading state
            const submitButton = document.getElementById('submitBtn');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
            }
        });
    }

    // Real-time validation feedback
    function setupRealTimeValidation() {
        // Password confirmation real-time validation
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                const confirmPassword = this.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    this.classList.add('is-invalid');
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.classList.remove('is-invalid');
                    this.setCustomValidity('');
                }
            });
        }
        
        // Email real-time validation
        const emailField = document.getElementById('email');
        if (emailField) {
            emailField.addEventListener('blur', function() {
                const email = this.value.trim();
                if (email) {
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(email)) {
                        this.classList.add('is-invalid');
                        this.setCustomValidity('Please enter a valid email address');
                    } else {
                        this.classList.remove('is-invalid');
                        this.setCustomValidity('');
                    }
                }
            });
        }
        
        // Username real-time validation
        const usernameField = document.getElementById('username');
        if (usernameField) {
            usernameField.addEventListener('blur', function() {
                const username = this.value.trim();
                if (username) {
                    if (username.length < 3) {
                        this.classList.add('is-invalid');
                        this.setCustomValidity('Username must be at least 3 characters long');
                    } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
                        this.classList.add('is-invalid');
                        this.setCustomValidity('Username can only contain letters, numbers, and underscores');
                    } else {
                        this.classList.remove('is-invalid');
                        this.setCustomValidity('');
                    }
                }
            });
        }
    }

    // Initialize real-time validation
    document.addEventListener('DOMContentLoaded', function() {
        setupRealTimeValidation();
    });
</script>
</body>
</html>
