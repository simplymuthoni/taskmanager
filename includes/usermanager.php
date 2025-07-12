<?php
class UserManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function register($name, $email, $password, $role = 'user') {
        // Check if email already exists
        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $result = $stmt->execute([$name, $email, $hashedPassword, $role]);
            
            if ($result) {
                return ['success' => true, 'message' => 'User created successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error creating user: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            return ['success' => true, 'user' => $user];
        }
        
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    public function logout() {
        session_unset();
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['login_time']) && 
               (time() - $_SESSION['login_time']) < SESSION_TIMEOUT;
    }
    
    public function isAdmin() {
        return $this->isLoggedIn() && $_SESSION['user_role'] === 'admin';
    }
    
    public function getAllUsers() {
        $stmt = $this->db->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function updateUser($id, $name, $email, $role) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
            $result = $stmt->execute([$name, $email, $role, $id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'User updated successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function deleteUser($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result) {
                return ['success' => true, 'message' => 'User deleted successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    private function emailExists($email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
}
?>