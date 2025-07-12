<?php
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

function getStatusBadge($status) {
    $badges = [
        'Pending' => 'badge-warning',
        'In Progress' => 'badge-info',
        'Completed' => 'badge-success'
    ];
    
    return $badges[$status] ?? 'badge-secondary';
}

function isOverdue($deadline) {
    return strtotime($deadline) < strtotime(date('Y-m-d'));
}

function redirectTo($location) {
    header("Location: $location");
    exit();
}

function showAlert($message, $type = 'info') {
    return "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='close' data-dismiss='alert'>
                    <span>&times;</span>
                </button>
            </div>";
}

function requireLogin() {
    global $userManager;
    if (!$userManager->isLoggedIn()) {
        redirectTo('login.php');
    }
}

function requireAdmin() {
    global $userManager;
    if (!$userManager->isAdmin()) {
        redirectTo('index.php');
    }
}
?>