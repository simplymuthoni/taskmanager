<?php
class UserManager {
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 18000; // 6 hours in seconds

    private function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    }

    public function __construct($database) {
        $this->db = $database;
    }
    
    public function register($username, $email, $password, $name, $last_name, $role = 'user', $phone = null, $department = null, $job_title = null) {
        // Check if email already exists
        if ($this->emailExists($email)) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Check if username already exists
        if ($this->usernameExists($username)) {
            return ['success' => false, 'message' => 'Username already exists'];
        }

        $uid = $this->generateUUID();
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $verificationToken = bin2hex(random_bytes(32));
        
        try {
            $stmt = $this->db->prepare("INSERT INTO users (uid, username, email, password, name, last_name, role, phone, department, job_title, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([$uid, $username, $email, $hashedPassword, $name, $last_name, $role, $phone, $department, $job_title, $verificationToken]);
            
            if ($result) {
                return ['success' => true, 'message' => 'User created successfully', 'verification_token' => $verificationToken];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error creating user: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function login($email, $password) {
        // First check if account is locked
        if ($this->isAccountLocked($email)) {
            return ['success' => false, 'message' => 'Account is temporarily locked due to too many failed attempts'];
        }
        
        $stmt = $this->db->prepare("SELECT uid, username, email, password, name, last_name, role, status, email_verified, login_attempts FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Account is ' . $user['status']];
        }
        
        // Check if email is verified
        if (!$user['email_verified']) {
            return ['success' => false, 'message' => 'Please verify your email address first'];
        }
        
        if (password_verify($password, $user['password'])) {
            // Reset login attempts on successful login
            $this->resetLoginAttempts($email);
            
            // Update last login time
            $this->updateLastLogin($user['uid']);
            
            // Set session variables
            $_SESSION['user_uid'] = $user['uid'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_last_name'] = $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            return ['success' => true, 'user' => $user];
        } else {
            // Increment login attempts
            $this->incrementLoginAttempts($email);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
    }
    
    public function logout() {
        session_unset();
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_uid']) && isset($_SESSION['login_time']) && 
               (time() - $_SESSION['login_time']) < SESSION_TIMEOUT;
    }
    
    public function isAdmin() {
        return $this->isLoggedIn() && $_SESSION['user_role'] === 'admin';
    }
    
    public function getAllUsers() {
        $stmt = $this->db->query("SELECT uid, username, email, name, last_name, role, phone, department, job_title, status, email_verified, last_login, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT uid, username, email, name, last_name, role, phone, department, job_title, status, email_verified, last_login, created_at FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function updateUser($uid, $username, $email, $name, $last_name, $role, $phone = null, $department = null, $job_title = null, $status = 'active') {
        try {
            // Check if username exists for other users
            if ($this->usernameExistsForOtherUser($username, $uid)) {
                return ['success' => false, 'message' => 'Username already exists'];
            }
            
            // Check if email exists for other users
            if ($this->emailExistsForOtherUser($email, $uid)) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
            
            $stmt = $this->db->prepare("UPDATE users SET username = ?, email = ?, name = ?, last_name = ?, role = ?, phone = ?, department = ?, job_title = ?, status = ? WHERE id = ?");
            $result = $stmt->execute([$username, $email, $name, $last_name, $role, $phone, $department, $job_title, $status, $uid]);
            
            if ($result) {
                return ['success' => true, 'message' => 'User updated successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function deleteUser($uid) {
        try {
            $stmt = $this->db->prepare("DELETE FROM users WHERE uid = ?");
            $result = $stmt->execute([$uid]);
            
            if ($result) {
                return ['success' => true, 'message' => 'User deleted successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function verifyEmail($token) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET email_verified = TRUE, verification_token = NULL WHERE verification_token = ?");
            $result = $stmt->execute([$token]);
            
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Email verified successfully'];
            } else {
                return ['success' => false, 'message' => 'Invalid or expired verification token'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error verifying email: ' . $e->getMessage()];
        }
    }
    
    public function requestPasswordReset($email) {
        $user = $this->getUserByEmail($email);
        if (!$user) {
            return ['success' => false, 'message' => 'Email not found'];
        }
        
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
            $result = $stmt->execute([$resetToken, $resetExpires, $email]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Password reset token generated', 'reset_token' => $resetToken];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error generating reset token: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function resetPassword($token, $newPassword) {
        try {
            $stmt = $this->db->prepare("SELECT uid FROM users WHERE reset_token = ? AND reset_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid or expired reset token'];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE uid = ?");
            $result = $stmt->execute([$hashedPassword, $user['uid']]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Password reset successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error resetting password: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    public function changePassword($userUid, $currentPassword, $newPassword) {
        $stmt = $this->db->prepare("SELECT password FROM users WHERE uid = ?");
        $stmt->execute([$userUid]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($currentPassword, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $result = $stmt->execute([$hashedPassword, $userUid]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Password changed successfully'];
            }
        } catch(PDOException $e) {
            return ['success' => false, 'message' => 'Error changing password: ' . $e->getMessage()];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    // Private helper methods
    private function emailExists($email) {
        $stmt = $this->db->prepare("SELECT uid FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    private function usernameExists($username) {
        $stmt = $this->db->prepare("SELECT uid FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch() !== false;
    }
    
    private function emailExistsForOtherUser($email, $userUid) {
        $stmt = $this->db->prepare("SELECT uid FROM users WHERE email = ? AND uid != ?");
        $stmt->execute([$email, $userUid]);
        return $stmt->fetch() !== false;
    }
    
    private function usernameExistsForOtherUser($username, $userUid) {
        $stmt = $this->db->prepare("SELECT uid FROM users WHERE username = ? AND uid != ?");
        $stmt->execute([$username, $userUid]);
        return $stmt->fetch() !== false;
    }
    
    private function getUserByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    private function isAccountLocked($email) {
        $stmt = $this->db->prepare("SELECT account_locked_until FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && $user['account_locked_until'] && strtotime($user['account_locked_until']) > time()) {
            return true;
        }
        
        return false;
    }
    
    private function incrementLoginAttempts($email) {
        $stmt = $this->db->prepare("UPDATE users SET login_attempts = login_attempts + 1 WHERE email = ?");
        $stmt->execute([$email]);
        
        // Check if we need to lock the account
        $stmt = $this->db->prepare("SELECT login_attempts FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && $user['login_attempts'] >= $this->maxLoginAttempts) {
            $lockUntil = date('Y-m-d H:i:s', time() + $this->lockoutDuration);
            $stmt = $this->db->prepare("UPDATE users SET account_locked_until = ? WHERE email = ?");
            $stmt->execute([$lockUntil, $email]);
        }
    }
    
    private function resetLoginAttempts($email) {
        $stmt = $this->db->prepare("UPDATE users SET login_attempts = 0, account_locked_until = NULL WHERE email = ?");
        $stmt->execute([$email]);
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
}
?>
