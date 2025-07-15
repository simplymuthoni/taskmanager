<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/usermanager.php';
require_once '../includes/taskmanager.php';
require_once '../includes/functions.php';
require_once '../includes/emailnotifications.php'; 

$db = new Database();
$connection = $db->connect();
$userManager = new UserManager($connection);
$taskManager = new TaskManager($connection);
$emailNotification = new EmailNotification();

requireLogin();
$userUid = $_SESSION['user_uid'];
$userTasks = $taskManager->getTasksByUser($userUid);
$taskStats = $taskManager->getTaskStats($userUid);
$recentTasks = $taskManager->getRecentTasks($userUid, 3);
$message = '';
$messageType = 'success';

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $taskId = (int)$_POST['task_id'];
    $newStatus = $_POST['status'];
    $result = $taskManager->updateTaskStatus($taskId, $newStatus, $userUid);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'danger';
    
    // Send email notification on status change
    if ($result['success']) {
        $task = $taskManager->getTaskById($taskId);
        $assignedBy = $userManager->getUserById($task['assigned_by']);
        if ($assignedBy) {
            $emailNotification->sendStatusUpdateNotification(
                $assignedBy['email'],
                $task['title'],
                $newStatus,
                $_SESSION['user_name']
            );
        }
    }
    
    // Refresh tasks
    $userTasks = $taskManager->getTasksByUser($userUid);
    $taskStats = $taskManager->getTaskStats($userUid);
}

// Handle mark as favorite
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favorite'])) {
    $taskId = (int)$_POST['task_id'];
    $result = $taskManager->toggleTaskFavorite($taskId, $userUid);
    $userTasks = $taskManager->getTasksByUser($userUid);
}

// Get user profile
$userProfile = $userManager->getUserById($userUid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Task Manager</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            --text-primary: #2d3748;
            --text-secondary: #718096;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .navbar {
            background: var(--glass-bg) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
        }

        .nav-link {
            color: white !important;
            font-weight: 500;
        }

        .container-fluid {
            padding: 2rem;
        }

        .dashboard-header {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
        }

        .welcome-text {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 2rem;
            color: white;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--primary-gradient);
            opacity: 0.1;
            transition: opacity 0.3s ease;
        }

        .stats-card:hover::before {
            opacity: 0.2;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .stats-card.warning::before {
            background: var(--warning-gradient);
        }

        .stats-card.info::before {
            background: var(--success-gradient);
        }

        .stats-card.success::before {
            background: var(--secondary-gradient);
        }

        .stats-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .stats-label {
            font-size: 1.1rem;
            font-weight: 500;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .stats-icon {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 3rem;
            opacity: 0.3;
            z-index: 1;
        }

        .main-card {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            background: none;
            border: none;
            padding: 2rem 2rem 1rem;
            color: white;
        }

        .card-header h5 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .task-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .task-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .task-card.pending::before {
            background: linear-gradient(135deg, #ffa726 0%, #ff9800 100%);
        }

        .task-card.in-progress::before {
            background: linear-gradient(135deg, #42a5f5 0%, #2196f3 100%);
        }

        .task-card.completed::before {
            background: linear-gradient(135deg, #66bb6a 0%, #4caf50 100%);
        }

        .task-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .task-card .card-body {
            padding: 1.5rem;
        }

        .task-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .task-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .task-meta {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .task-meta i {
            margin-right: 0.5rem;
            width: 16px;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pending {
            background: linear-gradient(135deg, #ffa726 0%, #ff9800 100%);
            color: white;
        }

        .status-badge.in-progress {
            background: linear-gradient(135deg, #42a5f5 0%, #2196f3 100%);
            color: white;
        }

        .status-badge.completed {
            background: linear-gradient(135deg, #66bb6a 0%, #4caf50 100%);
            color: white;
        }

        .btn-update {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .form-select {
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .favorite-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: #ffd700;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .favorite-btn:hover {
            transform: scale(1.1);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: white;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .alert-modern {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            border: 1px solid var(--glass-border);
            color: white;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }

        .overdue {
            color: #ef4444 !important;
            font-weight: 600;
        }

        .filter-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 0 2rem;
        }

        .filter-tab {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .filter-tab:hover,
        .filter-tab.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .container-fluid {
                padding: 1rem;
            }
            
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .task-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tasks me-2"></i>
                TaskFlow
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="welcome-text">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                    <p class="welcome-subtitle">Here's what's happening with your tasks today</p>
                </div>
                <div class="col-lg-4 text-end">
                    <div class="d-flex justify-content-end align-items-center">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <span><?php echo date('F j, Y'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-modern alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $taskStats['total']; ?></div>
                    <div class="stats-label">Total Tasks</div>
                    <i class="fas fa-tasks stats-icon"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card warning">
                    <div class="stats-number"><?php echo $taskStats['pending']; ?></div>
                    <div class="stats-label">Pending</div>
                    <i class="fas fa-clock stats-icon"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card info">
                    <div class="stats-number"><?php echo $taskStats['in_progress']; ?></div>
                    <div class="stats-label">In Progress</div>
                    <i class="fas fa-spinner stats-icon"></i>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stats-card success">
                    <div class="stats-number"><?php echo $taskStats['completed']; ?></div>
                    <div class="stats-label">Completed</div>
                    <i class="fas fa-check-circle stats-icon"></i>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="#" class="filter-tab active" data-filter="all">All Tasks</a>
            <a href="#" class="filter-tab" data-filter="pending">Pending</a>
            <a href="#" class="filter-tab" data-filter="in-progress">In Progress</a>
            <a href="#" class="filter-tab" data-filter="completed">Completed</a>
            <a href="#" class="filter-tab" data-filter="overdue">Overdue</a>
        </div>

        <!-- Tasks List -->
        <div class="main-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-list-ul me-2"></i>My Tasks</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-light btn-sm" onclick="toggleView()">
                            <i class="fas fa-th-large" id="viewToggle"></i>
                        </button>
                        <div class="input-group" style="width: 300px;">
                            <input type="text" class="form-control form-control-sm" placeholder="Search tasks..." id="searchInput">
                            <button class="btn btn-outline-light btn-sm" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 2rem;">
                <?php if (empty($userTasks)): ?>
                    <div class="empty-state">
                        <i class="fas fa-tasks"></i>
                        <h3>No tasks assigned yet</h3>
                        <p>You'll see your assigned tasks here once they're created.</p>
                    </div>
                <?php else: ?>
                    <div class="row" id="tasksContainer">
                        <?php foreach ($userTasks as $task): ?>
                            <div class="col-lg-4 col-md-6 mb-4 task-item" data-status="<?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>">
                                <div class="task-card <?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>">
                                    <form method="POST" action="" class="favorite-form">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <button type="submit" name="toggle_favorite" class="favorite-btn">
                                            <i class="fas fa-star <?php echo isset($task['is_favorite']) && $task['is_favorite'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h6>
                                            <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>">
                                                <?php echo $task['status']; ?>
                                            </span>
                                        </div>
                                        
                                        <p class="task-description">
                                            <?php echo htmlspecialchars($task['description']); ?>
                                        </p>
                                        
                                        <div class="task-meta">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span>Deadline: </span>
                                            <?php
                                            $deadlineClass = isOverdue($task['deadline']) && $task['status'] !== 'Completed' ? 'overdue' : '';
                                            ?>
                                            <span class="<?php echo $deadlineClass; ?>">
                                                <?php echo formatDate($task['deadline']); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="task-meta">
                                            <i class="fas fa-user"></i>
                                            <span>Assigned by: <?php echo htmlspecialchars($task['assigned_by_name']); ?></span>
                                        </div>
                                        
                                        <div class="task-meta">
                                            <i class="fas fa-flag"></i>
                                            <span>Priority: <?php echo htmlspecialchars($task['priority'] ?? 'Medium'); ?></span>
                                        </div>
                                        
                                        <form method="POST" action="" class="mt-3">
                                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                            <div class="input-group">
                                                <select name="status" class="form-select">
                                                    <option value="Pending" <?php echo $task['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="In Progress" <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="Completed" <?php echo $task['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-update">
                                                    <i class="fas fa-sync-alt me-1"></i>Update
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Update active tab
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const tasks = document.querySelectorAll('.task-item');
                
                tasks.forEach(task => {
                    if (filter === 'all') {
                        task.style.display = 'block';
                    } else if (filter === 'overdue') {
                        const isOverdue = task.querySelector('.overdue');
                        task.style.display = isOverdue ? 'block' : 'none';
                    } else {
                        const hasStatus = task.dataset.status === filter;
                        task.style.display = hasStatus ? 'block' : 'none';
                    }
                });
            });
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const tasks = document.querySelectorAll('.task-item');
            
            tasks.forEach(task => {
                const title = task.querySelector('.task-title').textContent.toLowerCase();
                const description = task.querySelector('.task-description').textContent.toLowerCase();
                const matches = title.includes(searchTerm) || description.includes(searchTerm);
                task.style.display = matches ? 'block' : 'none';
            });
        });

        // View toggle (optional: switch between grid and list view)
        let isGridView = true;
        function toggleView() {
            const container = document.getElementById('tasksContainer');
            const icon = document.getElementById('viewToggle');
            
            if (isGridView) {
                container.className = 'row list-view';
                icon.className = 'fas fa-th-large';
                isGridView = false;
            } else {
                container.className = 'row';
                icon.className = 'fas fa-list';
                isGridView = true;
            }
        }

        // Auto-refresh every 30 seconds for real-time updates
        setInterval(function() {
            // Only refresh if the page is visible
            if (!document.hidden) {
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newStats = doc.querySelectorAll('.stats-number');
                        const currentStats = document.querySelectorAll('.stats-number');
                        
                        newStats.forEach((stat, index) => {
                            if (currentStats[index] && currentStats[index].textContent !== stat.textContent) {
                                currentStats[index].textContent = stat.textContent;
                                currentStats[index].classList.add('updated');
                                setTimeout(() => currentStats[index].classList.remove('updated'), 1000);
                            }
                        });
                    });
            }
        }, 30000);

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.task-card, .stats-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>