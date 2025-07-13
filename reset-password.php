<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/usermanager.php';
require_once 'includes/functions.php';

$db = new Database();
$connection = $db->connect();
$userManager = new UserManager($connection);

$message = '';
$messageType = 'danger';
$validToken = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token is valid and not expired
    $stmt = $connection->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    $validToken = $stmt->fetch() !== false;
    
    if (!$validToken) {
        $message = 'Invalid or expired reset token';
    }
} else {
    $message = 'Invalid reset link';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirmPassword)) {
        $message = 'All fields are required';
    } elseif (!validatePassword($password)) {
        $message = 'Password must be at least 6 characters long';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match';
    } else {
        $result = $userManager->resetPassword($token, $password);
        
        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
            $validToken = false; // Hide form after successful reset
        } else {
            $message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Task Manager</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-md-6 d-flex align-items-center justify-content-center bg-primary">
                <div class="text-white text-center">
                    <h1 class="display-4 mb-4">Task Manager</h1>
                    <p class="lead">Reset Your Password</p>
                </div>
            </div>
            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <div class="card shadow-lg" style="width: 100%; max-width: 400px;">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Reset Password</h2>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($validToken): ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center">
                                <?php if ($messageType === 'success'): ?>
                                    <p>Your password has been reset successfully!</p>
                                    <a href="login.php" class="btn btn-primary">Login Now</a>
                                <?php else: ?>
                                    <p>Please request a new password reset link.</p>
                                    <a href="forgot-password.php" class="btn btn-secondary">Request New Link</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <p><a href="login.php">Back to Login</a></p>
                            <p><a href="index.php">Back to Home</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>