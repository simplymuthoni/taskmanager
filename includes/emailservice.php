<?php
class EmailService {
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        // Load email configuration from config.php 
        $this->smtpHost = EMAIL_HOST ?? 'smtp.gmail.com';
        $this->smtpPort = EMAIL_PORT ?? 587;
        $this->smtpUsername = EMAIL_USERNAME ?? 'patriciamuthoni414@gmail.com';
        $this->smtpPassword = EMAIL_PASSWORD ?? 'gpeh sqkj fvpm kefp ';
        $this->fromEmail = EMAIL_FROM ?? 'patriciamuthoni414@gmail.com';
        $this->fromName = EMAIL_FROM_NAME ?? 'Task Manager';

        // Validate SMTP configuration
        if (empty($this->smtpHost) || empty($this->smtpPort) ||
            empty($this->smtpUsername) || empty($this->fromEmail) || empty($this->fromName)) {
            throw new Exception("Email configuration is incomplete. Please check your settings.");
        }

        // Ensure SMTP credentials are set
        if (empty($this->smtpUsername) || empty($this->smtpPassword)) {
            throw new Exception("SMTP credentials are not set. Please check your configuration.");
            }
    }
        
    
    public function sendVerificationEmail($email, $name, $token) {
        $subject = "Please verify your email address";
        $verificationUrl = SITE_URL . "/verify-email.php?token=" . $token;
        
        $message = $this->getVerificationEmailTemplate($name, $verificationUrl);
        
        return $this->sendEmail($email, $subject, $message);
    }
    
    public function sendPasswordResetEmail($email, $name, $token) {
        $subject = "Reset your password";
        $resetUrl = SITE_URL . "/reset-password.php?token=" . $token;
        
        $message = $this->getPasswordResetEmailTemplate($name, $resetUrl);
        
        return $this->sendEmail($email, $subject, $message);
    }
    
    private function sendEmail($to, $subject, $message) {
        $headers = [
            'MIME-Version' => '1.0',
            'Content-type' => 'text/html; charset=UTF-8',
            'From' => $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To' => $this->fromEmail,
            'X-Mailer' => 'PHP/' . phpversion()
        ];
        
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= $key . ': ' . $value . "\r\n";
        }
        
        // For production, use PHPMailer or similar library
        // This is a basic implementation
        if (function_exists('mail')) {
            return mail($to, $subject, $message, $headerString);
        }
        
        // Alternative: Use PHPMailer (recommended for production)
        return $this->sendWithPHPMailer($to, $subject, $message);
    }
    
    private function sendWithPHPMailer($to, $subject, $message) {
        // Uncomment and configure if you have PHPMailer installed
        /*
        require_once 'vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtpUsername;
            $mail->Password = $this->smtpPassword;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtpPort;
            
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($to);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
        */
        
        // For development/testing - log email to file
        $logMessage = "To: $to\nSubject: $subject\n\n$message\n" . str_repeat("-", 50) . "\n\n";
        file_put_contents('logs/emails.log', $logMessage, FILE_APPEND | LOCK_EX);
        
        return true; // Return true for development
    }
    
    private function getVerificationEmailTemplate($name, $verificationUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Email Verification</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f8f9fa; }
                .button { display: inline-block; padding: 12px 30px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Task Manager</h1>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($name) . ",</h2>
                    <p>Thank you for registering with Task Manager! To complete your registration, please verify your email address by clicking the button below:</p>
                    <p style='text-align: center;'>
                        <a href='" . htmlspecialchars($verificationUrl) . "' class='button'>Verify Email Address</a>
                    </p>
                    <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                    <p><a href='" . htmlspecialchars($verificationUrl) . "'>" . htmlspecialchars($verificationUrl) . "</a></p>
                    <p>This verification link will expire in 24 hours.</p>
                    <p>If you didn't create an account with us, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Task Manager. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    private function getPasswordResetEmailTemplate($name, $resetUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f8f9fa; }
                .button { display: inline-block; padding: 12px 30px; background-color: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Password Reset</h1>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($name) . ",</h2>
                    <p>We received a request to reset your password. Click the button below to reset your password:</p>
                    <p style='text-align: center;'>
                        <a href='" . htmlspecialchars($resetUrl) . "' class='button'>Reset Password</a>
                    </p>
                    <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                    <p><a href='" . htmlspecialchars($resetUrl) . "'>" . htmlspecialchars($resetUrl) . "</a></p>
                    <p>This password reset link will expire in 1 hour.</p>
                    <p>If you didn't request a password reset, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Task Manager. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>
