<?php
/**
 * AdminManager - Complete Admin Management System
 * Combines user management with admin key handling
 */

require_once 'usermanager.php';
require_once 'adminkeyhandler.php';

class AdminManager {
    private $pdo;
    private $userManager;
    private $keyHandler;
    private $maxLoginAttempts = 3;
    private $lockoutDuration = 7200; // 2 hours for admin accounts
    
    public function __construct($pdo) {
        if (!$pdo instanceof PDO) {
            throw new InvalidArgumentException('Valid PDO connection required');
        }
        
        $this->pdo = $pdo;
        $this->userManager = new UserManager($pdo);
        $this->keyHandler = new AdminKeyHandler($pdo);
        
        // Initialize admin tables
        $this->initializeTables();
    }
    
    /**
     * Initialize admin-specific tables
     */
    private function initializeTables() {
        try {
            // Admin keys table
            $stmt = $this->pdo->prepare("
                CREATE TABLE IF NOT EXISTS admin_keys (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    uid VARCHAR(36) NOT NULL UNIQUE DEFAULT (UUID()),
                    key_hash VARCHAR(64) NOT NULL UNIQUE,
                    created_by VARCHAR(100) NOT NULL,
                    department_restriction VARCHAR(100) DEFAULT NULL,
                    email_domain_restriction VARCHAR(100) DEFAULT NULL,
                    max_uses INT DEFAULT NULL,
                    usage_count INT DEFAULT 0,
                    permissions JSON DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    expires_at TIMESTAMP NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    disabled_reason TEXT DEFAULT NULL,
                    disabled_at TIMESTAMP NULL,
                    revoked_by VARCHAR(100) DEFAULT NULL,
                    revoked_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_key_hash (key_hash),
                    INDEX idx_created_by (created_by),
                    INDEX idx_is_active (is_active),
                    INDEX idx_expires_at (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $stmt->execute();
            
            // Admin profiles table for additional admin-specific data
            $stmt = $this->pdo->prepare("
                CREATE TABLE IF NOT EXISTS admin_profiles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_uid VARCHAR(36) NOT NULL UNIQUE,
                    admin_level ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
                    permissions JSON DEFAULT NULL,
                    last_activity TIMESTAMP NULL,
                    security_clearance VARCHAR(50) DEFAULT NULL,
                    notes TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_uid) REFERENCES users(uid) ON DELETE CASCADE,
                    INDEX idx_admin_level (admin_level),
                    INDEX idx_last_activity (last_activity)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error initializing admin tables: " . $e->getMessage());
            throw new Exception("Failed to initialize admin tables");
        }
    }
    
    /**
     * Generate a UUID v4
     */
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Create a new admin key
     */
    public function createAdminKey($created_by, $options = []) {
        try {
            // Generate a secure random key
            $key = bin2hex(random_bytes(16)); // 32 character key
            $keyHash = hash('sha256', $key);
            
            $uid = $this->generateUUID();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_keys 
                (uid, key_hash, created_by, department_restriction, email_domain_restriction, 
                 max_uses, permissions, notes, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $expires_at = null;
            if (isset($options['expires_days']) && $options['expires_days'] > 0) {
                $expires_at = date('Y-m-d H:i:s', strtotime('+' . $options['expires_days'] . ' days'));
            }
            
            $result = $stmt->execute([
                $uid,
                $keyHash,
                $created_by,
                $options['department_restriction'] ?? null,
                $options['email_domain_restriction'] ?? null,
                $options['max_uses'] ?? null,
                isset($options['permissions']) ? json_encode($options['permissions']) : null,
                $options['notes'] ?? null,
                $expires_at
            ]);
            
            if ($result) {
                $this->keyHandler->logKeyHistory($uid, 'created', $options);
                return [
                    'success' => true,
                    'key' => $key,
                    'uid' => $uid,
                    'expires_at' => $expires_at,
                    'message' => 'Admin key created successfully'
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Error creating admin key: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create admin key'];
        }
        
        return ['success' => false, 'message' => 'Unknown error occurred'];
    }
    
    /**
     * Register a new admin user with key validation
     */
    public function registerAdmin($username, $email, $password, $name, $admin_key, $phone = null, $department = null, $job_title = null) {
        try {
            // Validate admin key first
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $keyValidation = $this->keyHandler->validateAdminKey($admin_key, $email, $department, $ip_address);
            
            if (!$keyValidation['valid']) {
                return ['success' => false, 'message' => $keyValidation['message']];
            }
            
            // Register the user with admin role
            $result = $this->userManager->register($username, $email, $password, $name, 'admin', $phone, $department, $job_title);
            
            if ($result['success']) {
                // Get the user's UID
                $user = $this->userManager->getUserByEmail($email);
                if ($user) {
                    // Create admin profile
                    $this->createAdminProfile($user['uid'], [
                        'admin_level' => 'admin',
                        'permissions' => $keyValidation['key_data']['permissions'] ?? null,
                        'notes' => 'Registered with admin key'
                    ]);
                    
                    // Record the admin registration
                    $this->keyHandler->recordAdminRegistration(
                        $user['uid'], 
                        $keyValidation['key_data']['uid'],
                        [
                            'username' => $username,
                            'email' => $email,
                            'department' => $department,
                            'job_title' => $job_title
                        ]
                    );
                    
                    return [
                        'success' => true,
                        'message' => 'Admin user registered successfully',
                        'user_uid' => $user['uid']
                    ];
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error registering admin: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to register admin user'];
        }
    }
    
    /**
     * Create admin profile
     */
    private function createAdminProfile($user_uid, $options = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_profiles 
                (user_uid, admin_level, permissions, security_clearance, notes) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $user_uid,
                $options['admin_level'] ?? 'admin',
                isset($options['permissions']) ? json_encode($options['permissions']) : null,
                $options['security_clearance'] ?? null,
                $options['notes'] ?? null
            ]);
            
        } catch (PDOException $e) {
            error_log("Error creating admin profile: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all admin users with their profiles
     */
    public function getAllAdmins() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.uid, u.username, u.email, u.name, u.phone, u.department, 
                       u.job_title, u.status, u.last_login, u.created_at,
                       ap.admin_level, ap.permissions, ap.last_activity, 
                       ap.security_clearance, ap.notes as admin_notes
                FROM users u
                LEFT JOIN admin_profiles ap ON u.uid = ap.user_uid
                WHERE u.role = 'admin'
                ORDER BY u.created_at DESC
            ");
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting admin users: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get admin user by ID with profile
     */
    public function getAdminById($uid) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.uid, u.username, u.email, u.name, u.phone, u.department, 
                       u.job_title, u.status, u.last_login, u.created_at,
                       ap.admin_level, ap.permissions, ap.last_activity, 
                       ap.security_clearance, ap.notes as admin_notes
                FROM users u
                LEFT JOIN admin_profiles ap ON u.uid = ap.user_uid
                WHERE u.uid = ? AND u.role = 'admin'
            ");
            
            $stmt->execute([$uid]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting admin by ID: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update admin user and profile
     */
    public function updateAdmin($uid, $userData, $adminData = []) {
        try {
            $this->pdo->beginTransaction();
            
            // Update user data
            $userResult = $this->userManager->updateUser(
                $uid,
                $userData['username'],
                $userData['email'],
                $userData['name'],
                'admin', // Keep admin role
                $userData['phone'] ?? null,
                $userData['department'] ?? null,
                $userData['job_title'] ?? null,
                $userData['status'] ?? 'active'
            );
            
            if (!$userResult['success']) {
                $this->pdo->rollBack();
                return $userResult;
            }
            
            // Update admin profile
            if (!empty($adminData)) {
                $stmt = $this->pdo->prepare("
                    UPDATE admin_profiles 
                    SET admin_level = ?, permissions = ?, security_clearance = ?, notes = ?
                    WHERE user_uid = ?
                ");
                
                $stmt->execute([
                    $adminData['admin_level'] ?? 'admin',
                    isset($adminData['permissions']) ? json_encode($adminData['permissions']) : null,
                    $adminData['security_clearance'] ?? null,
                    $adminData['notes'] ?? null,
                    $uid
                ]);
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Admin updated successfully'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error updating admin: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update admin'];
        }
    }
    
    /**
     * Delete admin user
     */
    public function deleteAdmin($uid) {
        try {
            $this->pdo->beginTransaction();
            
            // Delete admin profile first (foreign key constraint)
            $stmt = $this->pdo->prepare("DELETE FROM admin_profiles WHERE user_uid = ?");
            $stmt->execute([$uid]);
            
            // Delete user
            $result = $this->userManager->deleteUser($uid);
            
            if ($result['success']) {
                $this->pdo->commit();
                return $result;
            } else {
                $this->pdo->rollBack();
                return $result;
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error deleting admin: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete admin'];
        }
    }
    
    /**
     * Admin login with enhanced security
     */
    public function adminLogin($email, $password) {
        // Use the UserManager login but with admin-specific checks
        $result = $this->userManager->login($email, $password);
        
        if ($result['success'] && $result['user']['role'] === 'admin') {
            // Update admin last activity
            $this->updateAdminActivity($result['user']['uid']);
            
            // Set admin-specific session variables
            $_SESSION['admin_level'] = $this->getAdminLevel($result['user']['uid']);
            $_SESSION['admin_permissions'] = $this->getAdminPermissions($result['user']['uid']);
            
            return $result;
        } elseif ($result['success'] && $result['user']['role'] !== 'admin') {
            // User exists but is not an admin
            $this->userManager->logout();
            return ['success' => false, 'message' => 'Access denied: Admin privileges required'];
        }
        
        return $result;
    }
    
    /**
     * Update admin activity timestamp
     */
    private function updateAdminActivity($user_uid) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE admin_profiles 
                SET last_activity = NOW() 
                WHERE user_uid = ?
            ");
            $stmt->execute([$user_uid]);
        } catch (PDOException $e) {
            error_log("Error updating admin activity: " . $e->getMessage());
        }
    }
    
    /**
     * Get admin level
     */
    private function getAdminLevel($user_uid) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT admin_level 
                FROM admin_profiles 
                WHERE user_uid = ?
            ");
            $stmt->execute([$user_uid]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['admin_level'] : 'admin';
        } catch (PDOException $e) {
            error_log("Error getting admin level: " . $e->getMessage());
            return 'admin';
        }
    }
    
    /**
     * Get admin permissions
     */
    private function getAdminPermissions($user_uid) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT permissions 
                FROM admin_profiles 
                WHERE user_uid = ?
            ");
            $stmt->execute([$user_uid]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && $result['permissions'] ? json_decode($result['permissions'], true) : [];
        } catch (PDOException $e) {
            error_log("Error getting admin permissions: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if current user is super admin
     */
    public function isSuperAdmin() {
        return isset($_SESSION['admin_level']) && $_SESSION['admin_level'] === 'super_admin';
    }
    
    /**
     * Check if current user has specific permission
     */
    public function hasPermission($permission) {
        if ($this->isSuperAdmin()) {
            return true; // Super admin has all permissions
        }
        
        $permissions = $_SESSION['admin_permissions'] ?? [];
        return in_array($permission, $permissions);
    }
    
    /**
     * Get admin key management methods (delegate to KeyHandler)
     */
    public function getActiveAdminKeys() {
        return $this->keyHandler->getActiveKeys();
    }
    
    public function revokeAdminKey($uid, $revoked_by = null) {
        $revoked_by = $revoked_by ?? ($_SESSION['username'] ?? 'system');
        return $this->keyHandler->revokeKey($uid, $revoked_by);
    }
    
    public function getAdminKeyStatistics($uid = null) {
        return $this->keyHandler->getKeyStatistics($uid);
    }
    
    public function disableAdminKey($uid, $reason = '') {
        return $this->keyHandler->disableKey($uid, $reason);
    }
    
    public function cleanupOldKeyAttempts($days = 30) {
        return $this->keyHandler->cleanupOldAttempts($days);
    }
    
    /**
     * Get admin dashboard statistics
     */
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // Total admin users
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
            $stmt->execute();
            $stats['total_admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Active admin users (logged in within last 24 hours)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as active 
                FROM users u 
                JOIN admin_profiles ap ON u.uid = ap.user_uid 
                WHERE u.role = 'admin' AND ap.last_activity > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
            $stats['active_admins'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
            
            // Total admin keys
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM admin_keys WHERE is_active = 1");
            $stmt->execute();
            $stats['total_keys'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Recent admin registrations (last 7 days)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as recent 
                FROM admin_registrations 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            $stats['recent_registrations'] = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];
            
            return $stats;
            
        } catch (PDOException $e) {
            error_log("Error getting dashboard stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get admin activity log
     */
    public function getAdminActivityLog($limit = 50) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT akl.*, u.username, u.name
                FROM admin_key_history akl
                LEFT JOIN users u ON akl.performed_by = u.username
                ORDER BY akl.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Error getting admin activity log: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Export admin data for backup/reporting
     */
    public function exportAdminData($format = 'json') {
        try {
            $data = [
                'admins' => $this->getAllAdmins(),
                'admin_keys' => $this->getActiveAdminKeys(),
                'key_statistics' => $this->getAdminKeyStatistics(),
                'generated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($format === 'json') {
                return json_encode($data, JSON_PRETTY_PRINT);
            } elseif ($format === 'csv') {
                // Convert to CSV format (simplified)
                $csv = "Username,Email,Name,Department,Admin Level,Status,Created At\n";
                foreach ($data['admins'] as $admin) {
                    $csv .= sprintf("%s,%s,%s,%s,%s,%s,%s\n",
                        $admin['username'],
                        $admin['email'],
                        $admin['name'],
                        $admin['department'],
                        $admin['admin_level'],
                        $admin['status'],
                        $admin['created_at']
                    );
                }
                return $csv;
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Error exporting admin data: " . $e->getMessage());
            return false;
        }
    }
}
?>