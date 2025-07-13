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

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $result = $userManager->verifyEmail($token);
    
    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'danger';
    }
} else {
    $message = 'Invalid verification link';
    $messageType = 'danger';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Task Manager</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-md-6 d-flex align-items-center justify-content-center bg-primary">
                <div class="text-white text-center">
                    <h1 class="display-4 mb-4">Task Manager</h1>
                    <p class="lead">Email Verification</p>
                </div>
            </div>
            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <div class="card shadow-lg" style="width: 100%; max-width: 400px;">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Email Verification</h2>
                        
                        <div class="alert alert-<?php echo $messageType; ?>" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                        
                        <div class="text-center">
                            <?php if ($messageType === 'success'): ?>
                                <p>Your email has been verified successfully!</p>
                                <a href="login.php" class="btn btn-primary">Login Now</a>
                            <?php else: ?>
                                <p>Please check your email for a new verification link or contact support.</p>
                                <a href="resend-verification.php" class="btn btn-secondary">Resend Verification</a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center mt-4">
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