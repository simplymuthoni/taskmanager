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
            '', // last_name - not used in this form
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
    <link href="https://cdn