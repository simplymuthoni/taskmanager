<?php
/**
 * Enhanced Multi-Key Admin Handler
 * Handles multiple admin registration keys with different restrictions
 */

class AdminKeyHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Validate admin registration key with multiple key support
     */
    public function validateAdminKey($provided_key, $email = null, $department = null, $ip_address = null) {
        // Check rate limiting first
        if (!$this->checkRateLimit($ip_address)) {
            return [
                'valid' => false,
                'message' => 'Too many attempts. Please try again later.'
            ];
        }
        
        // Find the key in database
        $keyData = $this->findValidKey($provided_key);
        
        if (!$keyData) {
            $this->logKeyAttempt($provided_key, null, $ip_address, false, 'Key not found', $email);
            return [
                'valid' => false,
                'message' => 'Invalid admin registration key'
            ];
        }
        
        // Check if key is expired
        if ($keyData['expires_at'] && strtotime($keyData['expires_at']) < time()) {
            $this->logKeyAttempt($provided_key, $keyData['id'], $ip_address, false, 'Key expired', $email);
            return [
                'valid' => false,
                'message' => 'Admin registration key has expired'
            ];
        }
        
        // Check if key has reached max uses
        if ($keyData['max_uses'] && $keyData['usage_count'] >= $keyData['max_uses']) {
            $this->logKeyAttempt($provided_key, $keyData['id'], $ip_address, false, 'Max uses exceeded', $email);
            return [
                'valid' => false,
                'message' => 'Admin registration key has reached maximum uses'
            ];
        }
        
        // Check department restriction
        if ($keyData['department_restriction'] && $department !== $keyData['department_restriction']) {
            $this->logKeyAttempt($provided_key, $keyData['id'], $ip_address, false, 'Department restriction', $email);
            return [
                'valid' => false,
                'message' => 'This key is restricted to ' . $keyData['department_restriction'] . ' department'
            ];
        }
        
        // Check email domain restriction
        if ($keyData['email_domain_restriction'] && $email) {
            $emailDomain = substr(strrchr($email, '@'), 1);
            $requiredDomain = ltrim($keyData['email_domain_restriction'], '@');
            
            if ($emailDomain !== $requiredDomain) {
                $this->logKeyAttempt($provided_key, $keyData['id'], $ip_address, false, 'Email domain restriction', $email);
                return [
                    'valid' => false,
                    'message' => 'This key is restricted to ' . $keyData['email_domain_restriction'] . ' email addresses'
                ];
            }
        }
        
        // Key is valid - increment usage count
        $this->incrementKeyUsage($keyData['id']);
        $this->logKeyAttempt($provided_key, $keyData['id'], $ip_address, true, 'Success', $email);
        
        return [
            'valid' => true,
            'message' => 'Admin key validated successfully',
            'key_data' => $keyData
        ];
    }
    
    /**
     * Find a valid key in the database
     */
    private function findValidKey($provided_key) {
        $stmt = $this->pdo->prepare("
            SELECT id, key_hash, expires_at, max_uses, usage_count, 
                   department_restriction, email_domain_restriction, 
                   permissions, created_by, notes
            FROM admin_keys 
            WHERE key_hash = ? 
            AND is_active = TRUE
        ");
        
        $stmt->execute([hash('sha256', $provided_key)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Increment key usage count
     */
    private function incrementKeyUsage($key_id) {
        $stmt = $this->pdo->prepare("
            UPDATE admin_keys 
            SET usage_count = usage_count + 1 
            WHERE id = ?
        ");
        
        $stmt->execute([$key_id]);
        
        // Log the usage
        $this->logKeyHistory($key_id, 'used');
    }
    
    /**
     * Check rate limiting for admin key attempts
     */
    private function checkRateLimit($ip_address, $max_attempts = 5, $time_window = 3600) {
        if (!$ip_address) return true;
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM admin_key_attempts 
            WHERE ip_address = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        
        $stmt->execute([$ip_address, $time_window]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['attempts'] < $max_attempts;
    }
    
    /**
     * Log admin key attempts
     */
    private function logKeyAttempt($key, $key_id, $ip_address, $success, $failure_reason = null, $email = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_key_attempts 
                (key_hash, key_id, ip_address, success, failure_reason, user_agent, email_attempted, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                hash('sha256', $key),
                $key_id,
                $ip_address,
                $success ? 1 : 0,
                $failure_reason,
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                $email
            ]);
        } catch (Exception $e) {
            error_log("Failed to log admin key attempt: " . $e->getMessage());
        }
    }
    
    /**
     * Log key history for audit trail
     */
    private function logKeyHistory($key_id, $action, $details = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_key_history 
                (admin_key_id, action, performed_by, ip_address, details, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $key_id,
                $action,
                $_SESSION['username'] ?? 'system',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $details ? json_encode($details) : null
            ]);
        } catch (Exception $e) {
            error_log("Failed to log key history: " . $e->getMessage());
        }
    }
    
    /**
     * Record successful admin registration
     */
    public function recordAdminRegistration($user_id, $key_id, $registration_data = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_registrations 
                (user_id, admin_key_id, registered_by, ip_address, user_agent, registration_data, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $key_id,
                $_SESSION['username'] ?? 'self-registration',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                json_encode($registration_data)
            ]);
            
            $this->logKeyHistory($key_id, 'used', ['user_id' => $user_id]);
            
        } catch (Exception $e) {
            error_log("Failed to record admin registration: " . $e->getMessage());
        }
    }
    
    /**
     * Get all active admin keys (for admin panel)
     */
    public function getActiveKeys() {
        $stmt = $this->pdo->prepare("
            SELECT id, LEFT(key_hash, 8) as key_preview, 
                   created_by, department_restriction, email_domain_restriction,
                   max_uses, usage_count, expires_at, notes, created_at
            FROM admin_keys 
            WHERE is_active = TRUE 
            ORDER BY created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Revoke a specific key
     */
    public function revokeKey($key_id, $revoked_by = 'system') {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE admin_keys 
                SET is_active = FALSE, revoked_at = NOW(), revoked_by = ?
                WHERE id = ?
            ");
            
            $success = $stmt->execute([$revoked_by, $key_id]);
            
            if ($success) {
                $this->logKeyHistory($key_id, 'revoked', ['revoked_by' => $revoked_by]);
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("Failed to revoke admin key: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get key usage statistics
     */
    public function getKeyStatistics($key_id = null) {
        $whereClause = $key_id ? "WHERE ak.id = ?" : "";
        $params = $key_id ? [$key_id] : [];
        
        $stmt = $this->pdo->prepare("
            SELECT 
                ak.id,
                ak.created_by,
                ak.department_restriction,
                ak.max_uses,
                ak.usage_count,
                ak.expires_at,
                ak.notes,
                COUNT(ar.id) as successful_registrations,
                COUNT(aka.id) as total_attempts,
                SUM(CASE WHEN aka.success = 1 THEN 1 ELSE 0 END) as successful_attempts
            FROM admin_keys ak
            LEFT JOIN admin_registrations ar ON ak.id = ar.admin_key_id
            LEFT JOIN admin_key_attempts aka ON ak.id = aka.key_id
            {$whereClause}
            GROUP BY ak.id
            ORDER BY ak.created_at DESC
        ");
        
        $stmt->execute($params);
        return $key_id ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}