<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_user':
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = sanitize_input($_POST['role']);
                
                $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$username, $email, $password, $role])) {
                    $success_message = "User added successfully!";
                } else {
                    $error_message = "Error adding user.";
                }
                break;
                
            case 'edit_user':
                $user_id = (int)$_POST['user_id'];
                $username = sanitize_input($_POST['username']);
                $email = sanitize_input($_POST['email']);
                $role = sanitize_input($_POST['role']);
                
                $sql = "UPDATE users SET username = ?, email = ?, role = ?";
                $params = [$username, $email, $role];
                
                if (!empty($_POST['password'])) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $user_id;
                
                $stmt = $db->prepare($sql);
                if ($stmt->execute($params)) {
                    $success_message = "User updated successfully!";
                } else {
                    $error_message = "Error updating user.";
                }
                break;
                
            case 'delete_user':
                $user_id = (int)$_POST['user_id'];
                
                // Check if user has tasks
                $check_stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
                $check_stmt->execute([$user_id]);
                $task_count = $check_stmt->fetchColumn();
                
                if ($task_count > 0) {
                    $error_message = "Cannot delete user with assigned tasks. Please reassign or delete tasks first.";
                } else {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND id != ?");
                    if ($stmt->execute([$user_id, $_SESSION['user_id']])) {
                        $success_message = "User deleted successfully!";
                    } else {
                        $error_message = "Error deleting user or cannot delete yourself.";
                    }
                }
                break;
        }
    }
}

// Get all users
$stmt = $db->prepare("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Task Manager</title>
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
                <li><a href="users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="tasks.php"><i class="fas fa-list-check"></i> Tasks</a></li>
                <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <header class="content-header">
                <h1><i class="fas fa-users"></i> User Management</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
            </header>

            <div class="content-body">
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

                <!-- Add/Edit User Form -->
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo $edit_user ? 'Edit User' : 'Add New User'; ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="user-form">
                            <input type="hidden" name="action" value="<?php echo $edit_user ? 'edit_user' : 'add_user'; ?>">
                            <?php if ($edit_user): ?>
                                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input type="text" id="username" name="username" 
                                           value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>" 
                                           required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>" 
                                           required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password">Password <?php echo $edit_user ? '(leave blank to keep current)' : ''; ?></label>
                                    <input type="password" id="password" name="password" 
                                           <?php echo !$edit_user ? 'required' : ''; ?>>
                                </div>
                                <div class="form-group">
                                    <label for="role">Role</label>
                                    <select id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="admin" <?php echo ($edit_user && $edit_user['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                        <option value="user" <?php echo ($edit_user && $edit_user['role'] == 'user') ? 'selected' : ''; ?>>User</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $edit_user ? 'Update User' : 'Add User'; ?>
                                </button>
                                <?php if ($edit_user): ?>
                                    <a href="users.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users List -->
                <div class="card">
                    <div class="card-header">
                        <h3>All Users (<?php echo count($users); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td>
                                                <div class="user-info">
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                            <td class="actions">
                                                <a href="users.php?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                            class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
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
                <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
                <p class="text-danger">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form id="deleteForm" method="POST">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/script.js"></script>
    <script>
        function deleteUser(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = username;
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
    </script>
</body>
</html>