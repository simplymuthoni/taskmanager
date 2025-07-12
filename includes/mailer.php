<?php
/**
 * Email Configuration and Mailer Class
 * Handles email sending via Gmail SMTP
 */

class Mailer {
    private $smtp_host = 'smtp.gmail.com';
    private $smtp_port = 587;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        // Configure your Gmail credentials here
        $this->smtp_username = 'your-email@gmail.com'; // Replace with your Gmail
        $this->smtp_password = 'your-app-password';    // Replace with your App Password
        $this->from_email = 'your-email@gmail.com';
        $this->from_name = 'Task Manager System';
    }
    
    /**
     * Send email using Gmail SMTP
     */
    public function sendEmail($to, $subject, $body, $isHTML = true) {
        try {
            // Email headers
            $headers = array();
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-type: " . ($isHTML ? "text/html" : "text/plain") . "; charset=UTF-8";
            $headers[] = "From: {$this->from_name} <{$this->from_email}>";
            $headers[] = "Reply-To: {$this->from_email}";
            $headers[] = "X-Mailer: PHP/" . phpversion();
            
            // Send email
            $result = mail($to, $subject, $body, implode("\r\n", $headers));
            
            if ($result) {
                error_log("Email sent successfully to: $to");
                return true;
            } else {
                error_log("Failed to send email to: $to");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send task assignment notification
     */
    public function sendTaskAssignmentNotification($userEmail, $userName, $taskTitle, $taskDescription, $deadline) {
        $subject = "New Task Assigned: " . $taskTitle;
        
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4f46e5; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
                .task-details { background: white; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .deadline { color: #dc3545; font-weight: bold; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Task Assignment</h2>
                </div>
                <div class='content'>
                    <p>Hello <strong>{$userName}</strong>,</p>
                    <p>You have been assigned a new task:</p>
                    
                    <div class='task-details'>
                        <h3>{$taskTitle}</h3>
                        <p><strong>Description:</strong> {$taskDescription}</p>
                        <p><strong>Deadline:</strong> <span class='deadline'>{$deadline}</span></p>
                    </div>
                    
                    <p>Please log in to your dashboard to view more details and update the task status.</p>
                    <p><a href='" . $_SERVER['HTTP_HOST'] . "/task-manager/user/dashboard.php' style='background: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Dashboard</a></p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from Task Manager System</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $this->sendEmail($userEmail, $subject, $body, true);
    }
    
    /**
     * Send task status update notification
     */
    public function sendTaskStatusNotification($adminEmail, $taskTitle, $userName, $oldStatus, $newStatus) {
        $subject = "Task Status Updated: " . $taskTitle;
        
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #10b981; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
                .status-change { background: white; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .status { padding: 5px 10px; border-radius: 4px; font-weight: bold; }
                .status-pending { background: #fef3c7; color: #92400e; }
                .status-progress { background: #dbeafe; color: #1e40af; }
                .status-completed { background: #d1fae5; color: #065f46; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Task Status Update</h2>
                </div>
                <div class='content'>
                    <p>Hello Admin,</p>
                    <p>A task status has been updated:</p>
                    
                    <div class='status-change'>
                        <h3>{$taskTitle}</h3>
                        <p><strong>Updated by:</strong> {$userName}</p>
                        <p><strong>Status changed from:</strong> <span class='status status-{$oldStatus}'>{$oldStatus}</span> to <span class='status status-{$newStatus}'>{$newStatus}</span></p>
                    </div>
                    
                    <p><a href='" . $_SERVER['HTTP_HOST'] . "/task-manager/admin/tasks.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View All Tasks</a></p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from Task Manager System</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $this->sendEmail($adminEmail, $subject, $body, true);
    }
    
    /**
     * Send deadline reminder notification
     */
    public function sendDeadlineReminder($userEmail, $userName, $taskTitle, $deadline) {
        $subject = "Task Deadline Reminder: " . $taskTitle;
        
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f59e0b; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
                .reminder { background: white; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #f59e0b; }
                .deadline { color: #dc3545; font-weight: bold; font-size: 18px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>⏰ Task Deadline Reminder</h2>
                </div>
                <div class='content'>
                    <p>Hello <strong>{$userName}</strong>,</p>
                    <p>This is a reminder about your upcoming task deadline:</p>
                    
                    <div class='reminder'>
                        <h3>{$taskTitle}</h3>
                        <p><strong>Deadline:</strong> <span class='deadline'>{$deadline}</span></p>
                        <p>Please ensure you complete this task on time.</p>
                    </div>
                    
                    <p><a href='" . $_SERVER['HTTP_HOST'] . "/task-manager/user/dashboard.php' style='background: #f59e0b; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Update Task Status</a></p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from Task Manager System</p>
                </div>
            </div>
        </body>
        </html>";
        
        return $this->sendEmail($userEmail, $subject, $body, true);
    }
}

// Initialize mailer instance
$mailer = new Mailer();
?>