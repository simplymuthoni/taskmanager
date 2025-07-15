<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'task_manager');
define('DB_USER', 'root');
define('DB_PASS', 'celina21');
define('DB_CHARSET', 'utf8mb4');

// PDO options for better security and error handling
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

// Create DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// Initialize PDO connection with proper error handling
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Test the connection
    $pdo->query("SELECT 1");
    
    // Optional: Log successful connection (remove in production)
    error_log("Database connection successful");
    
} catch (PDOException $e) {
    // Log the specific error
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show user-friendly error message
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        // Only show detailed error in development
        die("Database connection failed: " . $e->getMessage());
    } else {
        // Production error message
        die("Database connection failed. Please try again later.");
    }
}

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
define('EMAIL_PASSWORD', 'gpeh sqkj fvpm kefp'); 
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