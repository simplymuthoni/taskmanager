<?php

class EmailNotification {
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
     * Send email notification when a new task is assigned
     */
    public function sendNewTaskNotification($userEmail, $userName, $taskTitle, $taskDescription, $deadline, $assignedBy) {
        $subject = "New Task Assigned: " . $taskTitle;
        
        $body = $this->getEmailTemplate([
            'title' => 'New Task Assigned',
            'greeting' => "Hello " . $userName . ",",
            'main_content' => "You have been assigned a new task:",
            'task_details' => [
                'Title' => $taskTitle,
                'Description' => $taskDescription,
                'Deadline' => date('F j, Y', strtotime($deadline)),
                'Assigned by' => $assignedBy
            ],
            'action_text' => 'View Task',
            'action_url' => BASE_URL . '/user/user_dashboard.php',
            'footer_text' => 'Please log in to your dashboard to view and manage your tasks.'
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Send email notification when task status is updated
     */
    public function sendStatusUpdateNotification($assignerEmail, $taskTitle, $newStatus, $updatedBy) {
        $subject = "Task Status Updated: " . $taskTitle;
        
        $statusColors = [
            'Pending' => '#ffa726',
            'In Progress' => '#42a5f5',
            'Completed' => '#66bb6a'
        ];
        
        $body = $this->getEmailTemplate([
            'title' => 'Task Status Updated',
            'greeting' => "Hello,",
            'main_content' => "A task you assigned has been updated:",
            'task_details' => [
                'Task' => $taskTitle,
                'New Status' => $newStatus,
                'Updated by' => $updatedBy,
                'Updated on' => date('F j, Y \a\t g:i A')
            ],
            'action_text' => 'View Dashboard',
            'action_url' => BASE_URL . '/admin/admin_dashboard.php',
            'footer_text' => 'Log in to your dashboard to view all task updates.',
            'status_color' => $statusColors[$newStatus] ?? '#667eea'
        ]);
        
        return $this->sendEmail($assignerEmail, $subject, $body);
    }
    
    /**
     * Send email notification for overdue tasks
     */
    public function sendOverdueNotification($userEmail, $userName, $overdueTasks) {
        $subject = "Overdue Tasks Reminder - " . count($overdueTasks) . " task(s) need attention";
        
        $taskList = '';
        foreach ($overdueTasks as $task) {
            $taskList .= "<li style='margin-bottom: 10px;'>";
            $taskList .= "<strong>" . htmlspecialchars($task['title']) . "</strong><br>";
            $taskList .= "<small style='color: #666;'>Deadline: " . date('F j, Y', strtotime($task['deadline'])) . "</small>";
            $taskList .= "</li>";
        }
        
        $body = $this->getEmailTemplate([
            'title' => 'Overdue Tasks Reminder',
            'greeting' => "Hello " . $userName . ",",
            'main_content' => "You have " . count($overdueTasks) . " overdue task(s) that need immediate attention:",
            'custom_content' => "<ul style='padding-left: 20px;'>" . $taskList . "</ul>",
            'action_text' => 'View Tasks',
            'action_url' => BASE_URL . '/user/user_dashboard.php',
            'footer_text' => 'Please update these tasks as soon as possible.',
            'is_urgent' => true
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Send email notification for deadline reminders
     */
    public function sendDeadlineReminder($userEmail, $userName, $task, $daysUntilDeadline) {
        $subject = "Deadline Reminder: " . $task['title'] . " - " . $daysUntilDeadline . " day(s) left";
        
        $body = $this->getEmailTemplate([
            'title' => 'Deadline Reminder',
            'greeting' => "Hello " . $userName . ",",
            'main_content' => "This is a reminder that one of your tasks is approaching its deadline:",
            'task_details' => [
                'Task' => $task['title'],
                'Description' => $task['description'],
                'Deadline' => date('F j, Y', strtotime($task['deadline'])),
                'Days remaining' => $daysUntilDeadline . ' day(s)',
                'Current status' => $task['status']
            ],
            'action_text' => 'Update Task',
            'action_url' => BASE_URL . '/user/dashboard.php',
            'footer_text' => 'Please ensure this task is completed on time.'
        ]);
        
        return $this->sendEmail($userEmail, $subject, $body);
    }
    
    /**
     * Get HTML email template
     */
    private function getEmailTemplate($data) {
        $isUrgent = $data['is_urgent'] ?? false;
        $statusColor = $data['status_color'] ?? '#667eea';
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $data['title'] . '</title>
            <style>
                body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background-color: white; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; }
                .header h1 { color: white; margin: 0; font-size: 28px; font-weight: 600; }
                .content { padding: 30px; }
                .greeting { font-size: 18px; margin-bottom: 20px; color: #2d3748; }
                .main-text { font-size: 16px; line-height: 1.6; color: #4a5568; margin-bottom: 25px; }
                .task-details { background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; }
                .task-details h3 { margin-top: 0; color: #2d3748; font-size: 16px; }
                .detail-item { margin-bottom: 10px; }
                .detail-label { font-weight: 600; color: #2d3748; }
                .detail-value { color: #4a5568; }
                .action-button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 20px 0; }
                .action-button:hover { opacity: 0.9; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; color: #718096; font-size: 14px; border-top: 1px solid #e2e8f0; }
                .urgent { border-left: 4px solid #ef4444; }
                .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; background-color: ' . $statusColor . '; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header' . ($isUrgent ? ' urgent' : '') . '">
                    <h1>📋 ' . $data['title'] . '</h1>
                </div>
                <div class="content">
                    <div class="greeting">' . $data['greeting'] . '</div>
                    <div class="main-text">' . $data['main_content'] . '</div>';
        
        if (isset($data['task_details'])) {
            $html .= '<div class="task-details">
                        <h3>Task Details:</h3>';
            foreach ($data['task_details'] as $label => $value) {
                if ($label === 'New Status') {
                    $value = '<span class="status-badge">' . $value . '</span>';
                }
                $html .= '<div class="detail-item">
                            <span class="detail-label">' . $label . ':</span>
                            <span class="detail-value">' . $value . '</span>
                          </div>';
            }
            $html .= '</div>';
        }
        
        if (isset($data['custom_content'])) {
            $html .= '<div class="main-text">' . $data['custom_content'] . '</div>';
        }
        
        if (isset($data['action_text']) && isset($data['action_url'])) {
            $html .= '<div style="text-align: center;">
                        <a href="' . $data['action_url'] . '" class="action-button">' . $data['action_text'] . '</a>
                      </div>';
        }
        
        $html .= '</div>
                <div class="footer">
                    <p>' . $data['footer_text'] . '</p>
                    <p>This is an automated message from Task Manager System.</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Send email using PHPMailer or mail() function
     */
    private function sendEmail($to, $subject, $body) {
        // Using PHPMailer (recommended)
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendWithPHPMailer($to, $subject, $body);
        } else {
            return $this->sendWithMailFunction($to, $subject, $body);
        }
    }
    
    /**
     * Send email using PHPMailer
     */
    private function sendWithPHPMailer($to, $subject, $body) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->smtp_port;
            
            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Email sending failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send email using PHP mail() function (fallback)
     */
    private function sendWithMailFunction($to, $subject, $body) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $success = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if ($success) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        } else {
            error_log("Email sending failed using mail() function");
            return ['success' => false, 'message' => 'Email sending failed'];
        }
    }
    
    /**
     * Send bulk email notifications
     */
    public function sendBulkNotifications($recipients, $subject, $body) {
        $results = [];
        foreach ($recipients as $recipient) {
            $results[] = $this->sendEmail($recipient, $subject, $body);
        }
        return $results;
    }
}