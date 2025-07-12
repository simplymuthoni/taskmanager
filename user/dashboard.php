<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/UserManager.php';
require_once '../includes/TaskManager.php';
require_once '../includes/functions.php';

$db = new Database();
$connection = $db->connect();
$userManager = new UserManager($connection);
$taskManager = new TaskManager($connection);

requireLogin();

$userId = $_SESSION['user_id'];
$userTasks = $taskManager->getTasksByUser($userId);
$taskStats = $taskManager->getTaskStats($userId);
$message = '';

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $taskId = (int)$_POST['task_id'];
    $newStatus = $_POST['status'];
    
    $result = $taskManager->updateTaskStatus($taskId, $newStatus, $userId);
    $message = $result['message'];
    
    // Refresh tasks
    $userTasks = $taskManager->getTasksByUser($userId);
    $taskStats = $taskManager->getTaskStats($userId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Task Manager</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">Task Manager</a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="h3 mb-4">My Tasks</h1>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="row">
                <div class="col-12">
                    <?php echo showAlert($message, 'success'); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Total Tasks</h5>
                                <h2><?php echo $taskStats['total']; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tasks fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Pending</h5>
                                <h2><?php echo $taskStats['pending']; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">In Progress</h5>
                                <h2><?php echo $taskStats['in_progress']; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-spinner fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Completed</h5>
                                <h2><?php echo $taskStats['completed']; ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tasks List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">My Tasks</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($userTasks)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No tasks assigned yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($userTasks as $task): ?>
                                    <div class="col-md-6 col-lg-4 mb-4">
                                        <div class="card task-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h6>
                                                    <span class="badge <?php echo getStatusBadge($task['status']); ?>">
                                                        <?php echo $task['status']; ?>
                                                    </span>
                                                </div>
                                                <p class="card-text text-muted small">
                                                    <?php echo htmlspecialchars($task['description']); ?>
                                                </p>
                                                <div class="mb-3">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> 
                                                        Deadline: 
                                                        <?php 
                                                        $deadlineClass = isOverdue($task['deadline']) && $task['status'] !== 'Completed' ? 'text-danger' : '';
                                                        ?>
                                                        <span class="<?php echo $deadlineClass; ?>">
                                                            <?php echo formatDate($task['deadline']); ?>
                                                        </span>
                                                    </small>
                                                </div>
                                                <div class="mb-3">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user"></i> 
                                                        Assigned by: <?php echo htmlspecialchars($task['assigned_by_name']); ?>
                                                    </small>
                                                </div>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                    <div class="input-group">
                                                        <select name="status" class="form-select form-select-sm">
                                                            <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="In Progress" <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                            <option value="Completed" <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                        </select>
                                                        <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                                            Update
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>