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

$message = '';
$messageType = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    
    if (empty($email)) {
        $message = 'Email is required';
    } elseif (!validateEmail($email)) {
        $message = 'Invalid email format';
    } else {
        // Check if user exists and is not verified
        $stmt = $connection->prepare("SELECT id, name, email_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $message = 'Email not found';
        } elseif ($user['email_verified']) {
            $message = 'Email is already verified';
            $messageType = 'info';
        } else {
            // Generate new verification token
            $newToken = bin2hex(random_bytes(32));
            
            $stmt = $connection->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
            $stmt->execute([$newToken, $user['id']]);
            
            // Send verification email
            $emailSent = $emailService->sendVerificationEmail($email, $user['name'], $newToken);
            
            if ($emailSent) {
                $message = 'Verification email sent successfully!';
                $messageType = 'success';
            } else {
                $message = 'Error sending verification email. Please try again.';
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
    <title>Resend Verification - Task Manager</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-md-6 d-flex align-items-center justify-content-center bg-primary">
                <div class="text-white text-center">
                    <h1 class="display-4 mb-4">Task Manager</h1>
                    <p class="lead">Resend Email Verification</p>
                </div>
            </div>
            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <div class="card shadow-lg" style="width: 100%; max-width: 400px;">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Resend Verification</h2>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Resend Verification</button>
                            </div>
                        </form>
                        
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