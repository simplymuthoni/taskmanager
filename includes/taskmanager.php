<?php
class TaskManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function createTask($title, $description, $assignedTo, $assignedBy, $deadline) {
        try {
            $stmt = $this->db->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, deadline) VALUES (?, ?, ?, ?, ?)");
            $result = $stmt->execute([$title, $description, $assignedTo, $assignedBy, $deadline]);
            
            if ($result) {
                $this->sendTaskNotification($assignedTo, $title, $description, $deadline);
                return ['success' => true, 'message' => 'Task created successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error creating task: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function getTasksByUser($userId) {
        $stmt = $this->db->prepare("
            SELECT t.*, u.name as assigned_by_name 
            FROM tasks t 
            JOIN users u ON assigned_by = uid 
            WHERE assigned_to = ? 
            ORDER BY t.deadline ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function getAllTasks() {
        $stmt = $this->db->query("
            SELECT t.*, u1.name as assigned_to_name, u2.name as assigned_by_name 
            FROM tasks t 
            JOIN users u1 ON t.assigned_to = u1.id 
            JOIN users u2 ON t.assigned_by = u2.id 
            ORDER BY t.created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    public function getTaskById($id) {
        $stmt = $this->db->prepare("
            SELECT t.*, u1.name as assigned_to_name, u2.name as assigned_by_name 
            FROM tasks t 
            JOIN users u1 ON t.assigned_to = u1.id 
            JOIN users u2 ON t.assigned_by = u2.id 
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function updateTask($id, $title, $description, $assignedTo, $deadline) {
        try {
            $stmt = $this->db->prepare("UPDATE tasks SET title = ?, description = ?, assigned_to = ?, deadline = ? WHERE id = ?");
            $result = $stmt->execute([$title, $description, $assignedTo, $deadline, $id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Task updated successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error updating task: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function updateTaskStatus($taskId, $status, $userId) {
        try {
            $stmt = $this->db->prepare("UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?");
            $result = $stmt->execute([$status, $taskId, $userId]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Task status updated successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error updating task status: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function deleteTask($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM tasks WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Task deleted successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error deleting task: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function getTaskStats($userId = null) {
        $where = $userId ? "WHERE assigned_to = ?" : "";
        $params = $userId ? [$userId] : [];
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
            FROM tasks $where
        ");
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    private function sendTaskNotification($userId, $title, $description, $deadline) {
        $userStmt = $this->db->prepare("SELECT name, email FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        
        if ($user) {
            $subject = "New Task Assigned: " . $title;
            $message = "Hello " . $user['name'] . ",\n\n";
            $message .= "You have been assigned a new task:\n\n";
            $message .= "Title: " . $title . "\n";
            $message .= "Description: " . $description . "\n";
            $message .= "Deadline: " . date('F j, Y', strtotime($deadline)) . "\n\n";
            $message .= "Please log in to your dashboard to view and manage this task.\n\n";
            $message .= "Best regards,\nTask Manager Team";
            
            $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
            $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            
            mail($user['email'], $subject, $message, $headers);
        }
    }
}
?>