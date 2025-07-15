<?php
// includes/emailmanager.php

class EmailManager {
     private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $this->smtp_host = SMTP_HOST ?? 'smtp.gmail.com';
        $this->smtp_port = SMTP_PORT ?? 587;
        $this->smtp_username = $config['smtp_username'] ?? 'patriciamuthoni414@gmail.com';
        $this->smtp_password = $config['smtp_password'] ?? 'gpeh sqkj fvpm kefp';
        $this->from_email = $config['from_email'] ?? 'noreply@taskmanager.com';
        $this->from_name = $config['from_name'] ?? 'Task Manager';
    }
    
    /**
     * Send task assignment email notification
     */
    public function sendTaskAssignmentEmail($toEmail, $taskData) {
        $subject = "New Task Assigned: " . $taskData['title'];
        
        $message = $this->getTaskAssignmentEmailTemplate($taskData);
        
        return $this->sendEmail($toEmail, $subject, $message);
    }
    
    /**
     * Send task status update email notification
     */
    public function sendTaskStatusUpdateEmail($toEmail, $taskData, $oldStatus, $newStatus) {
        $subject = "Task Status Updated: " . $taskData['title'];
        
        $message = $this->getTaskStatusUpdateEmailTemplate($taskData, $oldStatus, $newStatus);
        
        return $this->sendEmail($toEmail, $subject, $message);
    }
    
    /**
     * Send task deadline reminder email
     */
    public function sendTaskDeadlineReminderEmail($toEmail, $taskData) {
        $subject = "Task Deadline Reminder: " . $taskData['title'];
        
        $message = $this->getTaskDeadlineReminderEmailTemplate($taskData);
        
        return $this->sendEmail($toEmail, $subject, $message);
    }
    
    /**
     * Generic email sending method
     */
    private function sendEmail($toEmail, $subject, $message) {
        // Using PHP's mail() function for simplicity
        // In production, consider using PHPMailer or similar library
        
        $headers = array(
            'From: ' . $this->fromname . ' <' . $this->fromemail . '>',
            'Reply-To: ' . $this->fromemail,
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: PHP/' . phpversion()
        );
        
        $headersString = implode("\r\n", $headers);
        
        try {
            return mail($toEmail, $subject, $message, $headersString);
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email using PHPMailer (alternative implementation)
     * Uncomment and configure if you prefer to use PHPMailer
     */
    /*
    private function sendEmailWithPHPMailer($toEmail, $subject, $message) {
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
            $mail->addAddress($toEmail);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
    */
    
    /**
     * Get task assignment email template
     */
    private function getTaskAssignmentEmailTemplate($taskData) {
        $deadline = date('F j, Y g:i A', strtotime($taskData['deadline']));
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #007bff; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
                .content { background-color: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                .task-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #007bff; }
                .footer { margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🎯 New Task Assigned</h2>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>You have been assigned a new task in the Task Manager system.</p>
                    
                    <div class='task-details'>
                        <h3>📋 Task Details:</h3>
                        <p><strong>Title:</strong> " . htmlspecialchars($taskData['title']) . "</p>
                        <p><strong>Description:</strong> " . htmlspecialchars($taskData['description']) . "</p>
                        <p><strong>Deadline:</strong> " . $deadline . "</p>
                        <p><strong>Status:</strong> <span style='color: #ffc107;'>Pending</span></p>
                    </div>
                    
                    <p>Please log in to the Task Manager system to view more details and start working on this task.</p>
                    <p>If you have any questions, please contact your administrator.</p>
                    
                    <p>Best regards,<br>Task Manager System</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get task status update email template
     */
    private function getTaskStatusUpdateEmailTemplate($taskData, $oldStatus, $newStatus) {
        $statusColor = $this->getStatusColor($newStatus);
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #28a745; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
                .content { background-color: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                .task-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #28a745; }
                .status-change { background-color: #e9ecef; padding: 10px; border-radius: 5px; margin: 10px 0; }
                .footer { margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>📈 Task Status Updated</h2>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>The status of your task has been updated in the Task Manager system.</p>
                    
                    <div class='task-details'>
                        <h3>📋 Task Details:</h3>
                        <p><strong>Title:</strong> " . htmlspecialchars($taskData['title']) . "</p>
                        <p><strong>Description:</strong> " . htmlspecialchars($taskData['description']) . "</p>
                        
                        <div class='status-change'>
                            <p><strong>Status Change:</strong></p>
                            <p>From: <span style='color: #6c757d;'>" . htmlspecialchars($oldStatus) . "</span></p>
                            <p>To: <span style='color: " . $statusColor . ";'>" . htmlspecialchars($newStatus) . "</span></p>
                        </div>
                    </div>
                    
                    <p>Please log in to the Task Manager system to view more details.</p>
                    
                    <p>Best regards,<br>Task Manager System</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get task deadline reminder email template
     */
    private function getTaskDeadlineReminderEmailTemplate($taskData) {
        $deadline = date('F j, Y g:i A', strtotime($taskData['deadline']));
        $timeRemaining = $this->getTimeRemaining($taskData['deadline']);
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #dc3545; color: white; padding: 20px; border-radius: 5px 5px 0 0; }
                .content { background-color: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                .task-details { background-color: white; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #dc3545; }
                .deadline-warning { background-color: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; border: 1px solid #ffeaa7; }
                .footer { margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>⏰ Task Deadline Reminder</h2>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>This is a reminder that you have a task approaching its deadline.</p>
                    
                    <div class='task-details'>
                        <h3>📋 Task Details:</h3>
                        <p><strong>Title:</strong> " . htmlspecialchars($taskData['title']) . "</p>
                        <p><strong>Description:</strong> " . htmlspecialchars($taskData['description']) . "</p>
                        <p><strong>Current Status:</strong> " . htmlspecialchars($taskData['status']) . "</p>
                        
                        <div class='deadline-warning'>
                            <p><strong>⚠️ Deadline:</strong> " . $deadline . "</p>
                            <p><strong>Time Remaining:</strong> " . $timeRemaining . "</p>
                        </div>
                    </div>
                    
                    <p>Please log in to the Task Manager system and update your task status if you haven't already.</p>
                    
                    <p>Best regards,<br>Task Manager System</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Get status color for email templates
     */
    private function getStatusColor($status) {
        switch (strtolower($status)) {
            case 'pending':
                return '#ffc107';
            case 'in progress':
                return '#17a2b8';
            case 'completed':
                return '#28a745';
            default:
                return '#6c757d';
        }
    }
    
    /**
     * Calculate time remaining until deadline
     */
    private function getTimeRemaining($deadline) {
        $now = new DateTime();
        $deadlineDate = new DateTime($deadline);
        $interval = $now->diff($deadlineDate);
        
        if ($deadlineDate < $now) {
            return "Overdue by " . $interval->format('%a days, %h hours');
        }
        
        if ($interval->days > 0) {
            return $interval->format('%a days, %h hours remaining');
        } elseif ($interval->h > 0) {
            return $interval->format('%h hours, %i minutes remaining');
        } else {
            return $interval->format('%i minutes remaining');
        }
    }
}