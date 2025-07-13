<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'task_manager');
define('DB_USER', 'root');
define('DB_PASS', 'celina21');

// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'patriciamuthoni414@gmail.com');
define('SMTP_PASS', 'gpeh sqkj fvpm kefp ');
define('SMTPAUTH', true);
define('SMTPSECURE', 'tls');

// Email Configuration
define('EMAIL_HOST', 'smtp.gmail.com'); 
define('EMAIL_PORT', 587); 
define('EMAIL_USERNAME', 'patriciamuthoni414@gmail.com'); 
define('EMAIL_PASSWORD', 'gpeh sqkj fvpm kefp '); 
define('EMAIL_FROM', 'patriciamuthoni414@gmail.com'); 
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