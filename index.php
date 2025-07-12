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
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row min-vh-100">
            <div class="col-md-6 d-flex align-items-center justify-content-center bg-primary">
                <div class="text-white text-center">
                    <h1 class="display-4 mb-4">Task Manager</h1>
                    <p class="lead">Organize your tasks, manage your team, and boost productivity with our comprehensive task management system.</p>
                </div>
            </div>
            <div class="col-md-6 d-flex align-items-center justify-content-center">
                <div class="card shadow-lg" style="width: 100%; max-width: 400px;">
                    <div class="card-body p-5">
                        <h2 class="text-center mb-4">Welcome</h2>
                        <p class="text-center mb-4">Choose an option to get started</p>
                        
                        <div class="d-grid gap-3">
                            <a href="login.php" class="btn btn-primary btn-lg">Login</a>
                            <a href="register.php" class="btn btn-outline-secondary btn-lg">Register</a>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>