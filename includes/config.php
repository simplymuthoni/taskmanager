<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'task_manager');
define('DB_USER', 'root');
define('DB_PASS', 'celina21');

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'managert344@gmail.com');
define('SMTP_PASS', '');

// Email Configuration
define('EMAIL_HOST', 'smtp.gmail.com'); 
define('EMAIL_PORT', 587); 
define('EMAIL_USERNAME', 'managert344@gmail.com'); 
define('EMAIL_PASSWORD', '@sAfezone44'); 
define('EMAIL_FROM', 'managert344@gmail.com'); 
define('EMAIL_FROM_NAME', 'Task Manager');

// Site Configuration
define('SITE_URL', 'http://localhost/task_manager'); 
define('SITE_NAME', 'Task Manager');

// Create logs directory if it doesn't exist
if (!file_exists('logs')) {
    mkdir('logs', 0755, true);
}

// Security
define('SESSION_TIMEOUT', 3600); // 1 hour

session_start();
?>