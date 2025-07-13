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
    $username = sanitize($_POST['username']);
    $name = sanitize($_POST['name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $phone = sanitize($_POST['phone']);
    $department = sanitize($_POST['department']);
    $job_title = sanitize($_POST['job_title']);
    $role = sanitize($_POST['role'] ?? 'user'); // Default to user if not set
    $admin_key = $_POST['admin_key'] ?? ''; // Admin registration key
    
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
    } elseif ($role === 'admin' && $admin_key !== ADMIN_REGISTRATION_KEY) {
        $error = 'Invalid admin registration key';
    } else {
        // Additional validation for admin role
        if ($role === 'admin') {
            if (empty($department) || empty($job_title)) {
                $error = 'Department and job title are required for admin registration';
            } elseif (strlen($password) < 8) {
                $error = 'Admin passwords must be at least 8 characters long';
            } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
                $error = 'Admin password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
            }
        }
        
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
                                <label for="name" class="form-label">First Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                           required placeholder="John">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" 
                                           required placeholder="Doe">
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
                                        <input type="text" class="form-control" id="admin_department" name="department" 
                                               value="<?php echo isset($_POST['department']) ? htmlspecialchars($_POST['department']) : ''; ?>" 
                                               placeholder="IT, Management, etc.">
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
                if (role === 'admin') {
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
                    document.getElementById('admin_phone').setAttribute('required', 'required');
                    document.getElementById('admin_department').setAttribute('required', 'required');
                    document.getElementById('admin_job_title').setAttribute('required', 'required');
                } else {
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
                    document.getElementById('admin_phone').removeAttribute('required');
                    document.getElementById('admin_department').removeAttribute('required');
                    document.getElementById('admin_job_title').removeAttribute('required');
                }
            });
        });
        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword'); 
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const eyeIcon = document.getElementById('eyeIcon');
        const eyeIconConfirm = document.getElementById('eyeIconConfirm');   
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            eyeIcon.classList.toggle('fa-eye');
            eyeIcon.classList.toggle('fa-eye-slash');
        });
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            eyeIconConfirm.classList.toggle('fa-eye');
            eyeIconConfirm.classList.toggle('fa-eye-slash');
            });
        // Form validation
        const registrationForm = document.getElementById('registrationForm');
        registrationForm.addEventListener('submit', function(event) {
            const role = selectedRoleInput.value;
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            let isValid = true;
            // Check if passwords match
            if (password !== confirmPassword) {
                event.preventDefault();
                isValid = false;
                confirmPasswordInput.classList.add('is-invalid');
                confirmPasswordInput.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordInput.classList.remove('is-invalid');
                confirmPasswordInput.setCustomValidity('');
            }
            // Additional validation for admin role
            if (role === 'admin') {
                const adminKey = document.getElementById('admin_key').value;
                const adminPhone = document.getElementById('admin_phone').value;
                const adminDepartment = document.getElementById('admin_department').value;
                const adminJobTitle = document.getElementById('admin_job_title').value;
                if (!adminKey || !adminPhone || !adminDepartment || !adminJobTitle)
                {
                    event.preventDefault();
                    isValid = false;
                    if (!adminKey) {
                        document.getElementById('admin_key').classList.add('is-invalid');
                        document.getElementById('admin_key').setCustomValidity('Admin key is required');
                    } else {
                        document.getElementById('admin_key').classList.remove('is-invalid');
                        document.getElementById('admin_key').setCustomValidity('');
                    }
                    if (!adminPhone) {
                        document.getElementById('admin_phone').classList.add('is-invalid');
                        document.getElementById('admin_phone').setCustomValidity('Phone number is required');
                    } else {
                        document.getElementById('admin_phone').classList.remove('is-invalid');
                        document.getElementById('admin_phone').setCustomValidity('');
                    }
                    if (!adminDepartment) {
                        document.getElementById('admin_department').classList.add('is-invalid');
                        document.getElementById('admin_department').setCustomValidity('Department is required');
                    } else {
                        document.getElementById('admin_department').classList.remove('is-invalid');
                        document.getElementById('admin_department').setCustomValidity('');
                    }
                    if (!adminJobTitle) {
                        document.getElementById('admin_job_title').classList.add('is-invalid');
                        document.getElementById('admin_job_title').setCustomValidity('Job title is required');
                    } else {
                        document.getElementById('admin_job_title').classList.remove('is-invalid');
                        document.getElementById('admin_job_title').setCustomValidity('');
                    }
                }
            }
            // If form is not valid, prevent submission
            if (!isValid) {
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
