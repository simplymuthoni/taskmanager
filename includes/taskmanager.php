<?php

class TaskManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Create a new task
     */
    public function createTask($title, $description, $assignedTo, $assignedBy, $deadline, $priority = 'Medium') {
        try {
            $stmt = $this->db->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, deadline, priority, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
            $result = $stmt->execute([$title, $description, $assignedTo, $assignedBy, $deadline, $priority]);
            
            if ($result) {
                $taskId = $this->db->lastInsertId();
                $this->sendTaskNotification($assignedTo, $title, $description, $deadline);
                return [
                    'success' => true, 
                    'message' => 'Task created successfully',
                    'task_id' => $taskId
                ];
            }
        } catch(PDOException $e) {
            error_log("Error creating task: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error creating task: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    /**
     * Get tasks assigned to a specific user
     */
    public function getTasksByUser($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name as assigned_by_name 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_by = u.id 
                WHERE t.assigned_to = ? 
                ORDER BY t.deadline ASC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching tasks by user: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all tasks (for admin/manager view)
     */
    public function getAllTasks() {
        try {
            $stmt = $this->db->query("
                SELECT t.*, u1.name as assigned_to_name, u2.name as assigned_by_name 
                FROM tasks t 
                LEFT JOIN users u1 ON t.assigned_to = u1.id 
                LEFT JOIN users u2 ON t.assigned_by = u2.id 
                ORDER BY t.created_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching all tasks: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a specific task by ID
     */
    public function getTaskById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u1.name as assigned_to_name, u2.name as assigned_by_name 
                FROM tasks t 
                LEFT JOIN users u1 ON t.assigned_to = u1.id 
                LEFT JOIN users u2 ON t.assigned_by = u2.id 
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching task by ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get recent tasks for a user
     */
    public function getRecentTasks($userId, $limit = 5) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name as assigned_by_name 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_by = u.id 
                WHERE t.assigned_to = ? 
                ORDER BY t.created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching recent tasks: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get overdue tasks for a user
     */
    public function getOverdueTasks($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name as assigned_by_name 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_by = u.id 
                WHERE t.assigned_to = ? 
                AND t.deadline < NOW() 
                AND t.status != 'Completed'
                ORDER BY t.deadline ASC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching overdue tasks: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get tasks approaching deadline
     */
    public function getTasksApproachingDeadline($userId, $days = 2) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name as assigned_by_name 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_by = u.id 
                WHERE t.assigned_to = ? 
                AND t.deadline BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL ? DAY)
                AND t.status != 'Completed'
                ORDER BY t.deadline ASC
            ");
            $stmt->execute([$userId, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching tasks approaching deadline: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update a task
     */
    public function updateTask($id, $title, $description, $assignedTo, $deadline, $priority = 'Medium') {
        try {
            $stmt = $this->db->prepare("
                UPDATE tasks 
                SET title = ?, description = ?, assigned_to = ?, deadline = ?, priority = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$title, $description, $assignedTo, $deadline, $priority, $id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Task updated successfully'];
            }
        } catch(PDOException $e) {
            error_log("Error updating task: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating task: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    /**
     * Update task status with permission check
     */
    public function updateTaskStatus($taskId, $status, $userId) {
        try {
            // Verify user has permission to update this task
            $checkStmt = $this->db->prepare("SELECT id, status FROM tasks WHERE id = ? AND assigned_to = ?");
            $checkStmt->execute([$taskId, $userId]);
            $task = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$task) {
                return [
                    'success' => false,
                    'message' => 'You do not have permission to update this task'
                ];
            }
            
            $oldStatus = $task['status'];
            
            // Update task status
            $stmt = $this->db->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ? AND assigned_to = ?");
            $result = $stmt->execute([$status, $taskId, $userId]);
            
            if ($result) {
                // Log the status change
                $this->logStatusChange($taskId, $oldStatus, $status, $userId);
                
                return ['success' => true, 'message' => 'Task status updated successfully'];
            }
        } catch(PDOException $e) {
            error_log("Error updating task status: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating task status: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    /**
     * Delete a task
     */
    public function deleteTask($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM tasks WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Task deleted successfully'];
            }
        } catch(PDOException $e) {
            error_log("Error deleting task: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error deleting task: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    /**
     * Get task statistics for a user
     */
    public function getTaskStats($useruid) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN deadline < NOW() AND status != 'Completed' THEN 1 ELSE 0 END) as overdue
                FROM tasks 
                WHERE assigned_to = ?
            ");
            $stmt->execute([$useruid]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total' => (int)$stats['total'],
                'pending' => (int)$stats['pending'],
                'in_progress' => (int)$stats['in_progress'],
                'completed' => (int)$stats['completed'],
                'overdue' => (int)$stats['overdue']
            ];
        } catch(PDOException $e) {
            error_log("Error fetching task stats: " . $e->getMessage());
            return [
                'total' => 0,
                'pending' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'overdue' => 0
            ];
        }
    }
    
    /**
     * Toggle task favorite status
     */
    public function toggleTaskFavorite($taskId, $userId) {
        try {
            // Check if favorite exists
            $checkStmt = $this->db->prepare("SELECT id FROM task_favorites WHERE task_id = ? AND user_id = ?");
            $checkStmt->execute([$taskId, $userId]);
            $exists = $checkStmt->fetch();
            
            if ($exists) {
                // Remove from favorites
                $stmt = $this->db->prepare("DELETE FROM task_favorites WHERE task_id = ? AND user_id = ?");
                $message = "Task removed from favorites";
            } else {
                // Add to favorites
                $stmt = $this->db->prepare("INSERT INTO task_favorites (task_id, user_id, created_at) VALUES (?, ?, NOW())");
                $message = "Task added to favorites";
            }
            
            $success = $stmt->execute([$taskId, $userId]);
            
            return [
                'success' => $success,
                'message' => $success ? $message : 'Error updating favorite status'
            ];
        } catch(PDOException $e) {
            error_log("Error toggling favorite: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error updating favorite status'
            ];
        }
    }
    
    /**
     * Get favorite tasks for a user
     */
    public function getFavoriteTasks($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name as assigned_by_name 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_by = u.id 
                INNER JOIN task_favorites f ON t.id = f.task_id 
                WHERE f.user_id = ? 
                ORDER BY f.created_at DESC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching favorite tasks: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Search tasks by title or description
     */
    public function searchTasks($userId, $searchTerm) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name as assigned_by_name 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_by = u.id 
                WHERE t.assigned_to = ? 
                AND (t.title LIKE ? OR t.description LIKE ?)
                ORDER BY t.created_at DESC
            ");
            $searchParam = "%{$searchTerm}%";
            $stmt->execute([$userId, $searchParam, $searchParam]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error searching tasks: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get tasks by status
     */
    public function getTasksByStatus($userId, $status) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name as assigned_by_name 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_by = u.id 
                WHERE t.assigned_to = ? AND t.status = ?
                ORDER BY t.deadline ASC
            ");
            $stmt->execute([$userId, $status]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching tasks by status: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get tasks by priority
     */
    public function getTasksByPriority($userId, $priority) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, u.name as assigned_by_name 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_by = u.id 
                WHERE t.assigned_to = ? AND t.priority = ?
                ORDER BY t.deadline ASC
            ");
            $stmt->execute([$userId, $priority]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching tasks by priority: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log status changes for audit trail
     */
    private function logStatusChange($taskId, $oldStatus, $newStatus, $userId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO task_status_log (task_id, old_status, new_status, changed_by, changed_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$taskId, $oldStatus, $newStatus, $userId]);
        } catch(PDOException $e) {
            error_log("Error logging status change: " . $e->getMessage());
        }
    }
    
    /**
     * Send task notification email
     */
    private function sendTaskNotification($userId, $title, $description, $deadline) {
        try {
            $userStmt = $this->db->prepare("SELECT name, email FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && defined('ENABLE_EMAIL_NOTIFICATIONS') && ENABLE_EMAIL_NOTIFICATIONS) {
                $subject = "New Task Assigned: " . $title;
                $message = "Hello " . $user['name'] . ",\n\n";
                $message .= "You have been assigned a new task:\n\n";
                $message .= "Title: " . $title . "\n";
                $message .= "Description: " . $description . "\n";
                $message .= "Deadline: " . date('F j, Y', strtotime($deadline)) . "\n\n";
                $message .= "Please log in to your dashboard to view and manage this task.\n\n";
                $message .= "Best regards,\nTask Manager Team";
                
                $headers = "From: " . (defined('FROM_NAME') ? FROM_NAME : 'Task Manager') . " <" . (defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@taskmanager.com') . ">\r\n";
                $headers .= "Reply-To: " . (defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@taskmanager.com') . "\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                mail($user['email'], $subject, $message, $headers);
            }
        } catch(PDOException $e) {
            error_log("Error sending task notification: " . $e->getMessage());
        }
    }
}

?>