<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/mailer.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle task actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_task':
                $title = sanitize_input($_POST['title']);
                $description = sanitize_input($_POST['description']);
                $assigned_to = (int)$_POST['assigned_to'];
                $deadline = $_POST['deadline'];
                $priority = sanitize_input($_POST['priority']);
                
                $stmt = $db->prepare("INSERT INTO tasks (title, description, assigned_to, deadline, priority, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $description, $assigned_to, $deadline, $priority, $_SESSION['user_id']])) {
                    // Send email notification
                    $user_stmt = $db->prepare("SELECT username, email FROM users WHERE id = ?");
                    $user_stmt->execute([$assigned_to]);
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        $mailer->sendTaskAssignmentNotification(
                            $user['email'],
                            $user['username'],
                            $title,
                            $description,
                            date('F j, Y', strtotime($deadline))
                        );
                    }
                    
                    $success_message = "Task created and assigned successfully!";
                } else {
                    $error_message = "Error creating task.";
                }
                break;
                
            case 'edit_task':
                $task_id = (int)$_POST['task_id'];
                $title = sanitize_input($_POST['title']);
                $description = sanitize_input($_POST['description']);
                $assigned_to = (int)$_POST['assigned_to'];
                $deadline = $_POST['deadline'];
                $priority = sanitize_input($_POST['priority']);
                $status = sanitize_input($_POST['status']);
                
                $stmt = $db->prepare("UPDATE tasks SET title = ?, description = ?, assigned_to = ?, deadline = ?, priority = ?, status = ? WHERE id = ?");
                if ($stmt->execute([$title, $description, $assigned_to, $deadline, $priority, $status, $task_id])) {
                    $success_message = "Task updated successfully!";
                } else {
                    $error_message = "Error updating task.";
                }
                break;
                
            case 'delete_task':
                $task_id = (int)$_POST['task_id'];
                
                $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
                if ($stmt->execute([$task_id])) {
                    $success_message = "Task deleted successfully!";
                } else {
                    $error_message = "Error deleting task.";
                }
                break;
        }
    }
}

// Get all tasks with user information
$stmt = $db->prepare("
    SELECT t.*, u.username, u.email, 
           creator.username as created_by_name
    FROM tasks t 
    LEFT JOIN users u ON t.assigned_to = u.id 
    LEFT JOIN users creator ON t.created_by = creator.id
    ORDER BY t.created_at DESC
");
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for assignment dropdown
$stmt = $db->prepare("SELECT id, username, email FROM users WHERE role = 'user' ORDER BY username");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get task for editing
$edit_task = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_task = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get task statistics
$stats_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN deadline < NOW() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
    FROM tasks
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management - Task Manager</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-tasks"></i> Task Manager</h3>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="tasks.php" class="active"><i class="fas fa-list-check"></i> Tasks</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <header class="content-header">
                <h1><i class="fas fa-list-check"></i> Task Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <div class="content-body">
                <!-- Task Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total']; ?></h3>
                            <p>Total Tasks</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['pending']; ?></h3>
                            <p>Pending</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon in-progress">
                            <i class="fas fa-spinner"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['in_progress']; ?></h3>
                            <p>In Progress</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon completed">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['completed']; ?></h3>
                            <p>Completed</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon overdue">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['overdue']; ?></h3>
                            <p>Overdue</p>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <!-- Add/Edit Task Form -->
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo $edit_task ? 'Edit Task' : 'Create New Task'; ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="task-form">
                            <input type="hidden" name="action" value="<?php echo $edit_task ? 'edit_task' : 'add_task'; ?>">
                            <?php if ($edit_task): ?>
                                <input type="hidden" name="task_id" value="<?php echo $edit_task['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="title">Task Title</label>
                                    <input type="text" id="title" name="title" 
                                           value="<?php echo $edit_task ? htmlspecialchars($edit_task['title']) : ''; ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group full-width">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" rows="4" required><?php echo $edit_task ? htmlspecialchars($edit_task['description']) : ''; ?></textarea>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="assigned_to">Assign To</label>
                                    <select id="assigned_to" name="assigned_to" required>
                                        <option value="">Select User</option>
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                    <?php echo ($edit_task && $edit_task['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="deadline">Deadline</label>
                                    <input type="datetime-local" id="deadline" name="deadline" 
                                           value="<?php echo $edit_task ? date('Y-m-d\TH:i', strtotime($edit_task['deadline'])) : ''; ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="priority">Priority</label>
                                    <select id="priority" name="priority" required>
                                        <option value="">Select Priority</option>
                                        <option value="low" <?php echo ($edit_task && $edit_task['priority'] == 'low') ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo ($edit_task && $edit_task['priority'] == 'medium') ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo ($edit_task && $edit_task['priority'] == 'high') ? 'selected' : ''; ?>>High</option>
                                    </select>
                                </div>
                                <?php if ($edit_task): ?>
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" required>
                                        <option value="pending" <?php echo ($edit_task['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="in_progress" <?php echo ($edit_task['status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                        <option value="completed" <?php echo ($edit_task['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $edit_task ? 'Update Task' : 'Create Task'; ?>
                                </button>
                                <?php if ($edit_task): ?>
                                    <a href="tasks.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tasks List -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Tasks (<?php echo count($tasks); ?>)</h3>
                        <div class="card-actions">
                            <input type="text" id="searchTasks" placeholder="Search tasks..." class="search-input">
                            <select id="statusFilter" class="filter-select">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                            <select id="priorityFilter" class="filter-select">
                                <option value="">All Priority</option>
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="tasks-grid">
                            <?php foreach ($tasks as $task): ?>
                                <div class="task-card" data-status="<?php echo $task['status']; ?>" data-priority="<?php echo $task['priority']; ?>">
                                    <div class="task-header">
                                        <h4><?php echo htmlspecialchars($task['title']); ?></h4>
                                        <div class="task-badges">
                                            <span class="priority-badge priority-<?php echo $task['priority']; ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                            <span class="status-badge status-<?php echo $task['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="task-content">
                                        <p><?php echo htmlspecialchars(substr($task['description'], 0, 100)); ?><?php echo strlen($task['description']) > 100 ? '...' : ''; ?></p>
                                        
                                        <div class="task-meta">
                                            <div class="task-info">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($task['username']); ?></span>
                                            </div>
                                            <div class="task-info">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo date('M j, Y g:i A', strtotime($task['deadline'])); ?></span>
                                            </div>
                                            <div class="task-info">
                                                <i class="fas fa-user-plus"></i>
                                                <span>By <?php echo htmlspecialchars($task['created_by_name']); ?></span>
                                            </div>
                                        </div>
                                        
                                        <?php if (strtotime($task['deadline']) < time() && $task['status'] != 'completed'): ?>
                                            <div class="task-overdue">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <span>Overdue</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="task-actions">
                                        <a href="tasks.php?edit=<?php echo $task['id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <button onclick="deleteTask(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title']); ?>')" 
                                                class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h4>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the task <strong id="deleteTaskTitle"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="task_id" id="deleteTaskId">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Task</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/script.js"></script>
    <script>
        function deleteTask(taskId, taskTitle) {
            document.getElementById('deleteTaskId').value = taskId;
            document.getElementById('deleteTaskTitle').textContent = taskTitle;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Search and filter functionality
        document.getElementById('searchTasks').addEventListener('input', filterTasks);
        document.getElementById('statusFilter').addEventListener('change', filterTasks);
        document.getElementById('priorityFilter').addEventListener('change', filterTasks);

        function filterTasks() {
            const searchTerm = document.getElementById('searchTasks').value.toLowerCase();
            const statusFilter = document.getElementById('statusFilter').value;
            const priorityFilter = document.getElementById('priorityFilter').value;
            const taskCards = document.querySelectorAll('.task-card');

            taskCards.forEach(card => {
                const title = card.querySelector('h4').textContent.toLowerCase();
                const status = card.getAttribute('data-status');
                const priority = card.getAttribute('data-priority');

                const matchesSearch = title.includes(searchTerm);
                const matchesStatus = !statusFilter || status === statusFilter;
                const matchesPriority = !priorityFilter || priority === priorityFilter;

                if (matchesSearch && matchesStatus && matchesPriority) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>