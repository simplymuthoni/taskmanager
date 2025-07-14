<?php
/**
 * Multi-Admin Registration Key Generator
 * Generate different keys for different admins with various configurations
 */

require_once 'includes/config.php'; 

$host = 'localhost';
$dbname = 'task_manager';
$username = 'root';
$password = 'celina21';

class AdminKeyGenerator {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate a UUID v4
     */
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
    
    /**
     * Generate a new admin registration key
     */
    public function generateKey($config = []) {
        $defaults = [
            'length' => 32,
            'expires_hours' => null, // null = no expiry
            'max_uses' => 1, // How many times this key can be used
            'created_by' => 'system',
            'department_restriction' => null, // Restrict to specific department
            'permissions' => ['full_admin'], // What permissions this admin will have
            'notes' => '',
            'email_domain_restriction' => null // e.g., '@company.com'
        ];
        
        $config = array_merge($defaults, $config);
        
        // Generate secure random key - ensure uniqueness
        do {
            $key = bin2hex(random_bytes($config['length']));
            $keyHash = hash('sha256', $key);
            
            // Check if this key hash already exists
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_keys WHERE key_hash = ?");
            $stmt->execute([$keyHash]);
            $exists = $stmt->fetchColumn() > 0;
            
        } while ($exists);
        
        // Generate UUID for the uid field
        do {
            $uid = $this->generateUUID();
            
            // Check if this UUID already exists
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_keys WHERE uid = ?");
            $stmt->execute([$uid]);
            $uuidExists = $stmt->fetchColumn() > 0;
            
        } while ($uuidExists);
        
        // Calculate expiry timestamp
        $expires_at = $config['expires_hours'] ? 
            date('Y-m-d H:i:s', time() + ($config['expires_hours'] * 3600)) : 
            null;
        
        // Add a small delay to ensure different timestamps
        usleep(1000); // 1ms delay
        
        try {
            // Store in database with explicit uid
            $stmt = $this->pdo->prepare("
                INSERT INTO admin_keys 
                (uid, key_hash, expires_at, max_uses, created_by, department_restriction, 
                 permissions, notes, email_domain_restriction, created_at, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), TRUE)
            ");
            
            $stmt->execute([
                $uid,
                $keyHash,
                $expires_at,
                $config['max_uses'],
                $config['created_by'],
                $config['department_restriction'],
                json_encode($config['permissions']),
                $config['notes'],
                $config['email_domain_restriction']
            ]);
            
            return [
                'key' => $key,
                'uid' => $uid,
                'expires_at' => $expires_at,
                'max_uses' => $config['max_uses'],
                'config' => $config
            ];
            
        } catch (PDOException $e) {
            // Log the error and rethrow with more context
            error_log("Failed to insert admin key: " . $e->getMessage());
            throw new Exception("Failed to generate admin key: " . $e->getMessage());
        }
    }
    
    /**
     * Generate multiple keys for different scenarios
     */
    public function generateMultipleKeys() {
        $keys = [];
        
        // 1. Department-specific keys
        $departments = ['IT', 'HR', 'Finance', 'Operations'];
        foreach ($departments as $dept) {
            try {
                $keys[] = $this->generateKey([
                    'created_by' => 'system',
                    'department_restriction' => $dept,
                    'max_uses' => 3,
                    'expires_hours' => 168, // 1 week
                    'notes' => "Registration key for {$dept} department admins",
                    'permissions' => ['admin', 'department_' . strtolower($dept)]
                ]);
            } catch (Exception $e) {
                echo "Warning: Failed to generate key for {$dept} department: " . $e->getMessage() . "\n";
            }
        }
        
        // 2. Temporary keys for specific people
        $temp_keys = [
            [
                'created_by' => 'john.doe',
                'max_uses' => 1,
                'expires_hours' => 24,
                'notes' => 'One-time key for new IT admin',
                'email_domain_restriction' => '@company.com'
            ],
            [
                'created_by' => 'jane.smith',
                'max_uses' => 1,
                'expires_hours' => 48,
                'notes' => 'Key for HR department head',
                'department_restriction' => 'HR'
            ]
        ];
        
        foreach ($temp_keys as $config) {
            try {
                $keys[] = $this->generateKey($config);
            } catch (Exception $e) {
                echo "Warning: Failed to generate temporary key: " . $e->getMessage() . "\n";
            }
        }
        
        // 3. Emergency/Super admin key
        try {
            $keys[] = $this->generateKey([
                'created_by' => 'system',
                'max_uses' => 1,
                'expires_hours' => 72,
                'notes' => 'Emergency super admin key',
                'permissions' => ['super_admin']
            ]);
        } catch (Exception $e) {
            echo "Warning: Failed to generate emergency key: " . $e->getMessage() . "\n";
        }
        
        return $keys;
    }
    
    /**
     * Get all active keys
     */
    public function getActiveKeys() {
        $stmt = $this->pdo->prepare("
            SELECT uid, LEFT(key_hash, 8) as key_preview, expires_at, max_uses, 
                   usage_count, created_by, department_restriction, notes, 
                   email_domain_restriction, created_at
            FROM admin_keys 
            WHERE is_active = TRUE 
            AND (expires_at IS NULL OR expires_at > NOW())
            AND (max_uses IS NULL OR usage_count < max_uses)
            ORDER BY created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Revoke a specific key
     */
    public function revokeKey($uid, $revoked_by = 'system') {
        $stmt = $this->pdo->prepare("
            UPDATE admin_keys 
            SET is_active = FALSE, revoked_at = NOW(), revoked_by = ?
            WHERE uid = ?
        ");
        
        return $stmt->execute([$revoked_by, $uid]);
    }
    
    /**
     * Clean up expired or used keys
     */
    public function cleanupKeys() {
        $stmt = $this->pdo->prepare("
            UPDATE admin_keys 
            SET is_active = FALSE 
            WHERE is_active = TRUE 
            AND (
                (expires_at IS NOT NULL AND expires_at <= NOW()) 
                OR (max_uses IS NOT NULL AND usage_count >= max_uses)
            )
        ");
        
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Display keys in a readable format
     */
    public function displayKeys($keys) {
        echo "=== ADMIN REGISTRATION KEYS ===\n\n";
        
        foreach ($keys as $i => $keyData) {
            echo "KEY #" . ($i + 1) . "\n";
            echo "Key: " . $keyData['key'] . "\n";
            echo "UID: " . $keyData['uid'] . "\n";
            echo "Max Uses: " . ($keyData['max_uses'] ?: 'Unlimited') . "\n";
            echo "Expires: " . ($keyData['expires_at'] ?: 'Never') . "\n";
            echo "Department: " . ($keyData['config']['department_restriction'] ?: 'Any') . "\n";
            echo "Email Domain: " . ($keyData['config']['email_domain_restriction'] ?: 'Any') . "\n";
            echo "Created By: " . $keyData['config']['created_by'] . "\n";
            echo "Notes: " . $keyData['config']['notes'] . "\n";
            echo "Permissions: " . implode(', ', $keyData['config']['permissions']) . "\n";
            echo str_repeat('-', 50) . "\n";
        }
    }
}

// Usage example
try {
    // Database connection (adjust as needed)
    $pdo = new PDO("mysql:host=localhost;dbname=task_manager", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $generator = new AdminKeyGenerator($pdo);
    
    // Clean up any expired keys first
    $cleaned = $generator->cleanupKeys();
    if ($cleaned > 0) {
        echo "Cleaned up {$cleaned} expired/used keys.\n\n";
    }
    
    // Option 1: Generate a single custom key
    echo "=== SINGLE KEY GENERATION ===\n";
    try {
        $singleKey = $generator->generateKey([
            'created_by' => 'admin',
            'max_uses' => 2,
            'expires_hours' => 48,
            'department_restriction' => 'IT',
            'notes' => 'Key for IT department admin registration',
            'email_domain_restriction' => '@company.com'
        ]);
        
        echo "Generated Key: " . $singleKey['key'] . "\n";
        echo "UID: " . $singleKey['uid'] . "\n";
        echo "Expires: " . $singleKey['expires_at'] . "\n";
        echo "Max Uses: " . $singleKey['max_uses'] . "\n\n";
        
    } catch (Exception $e) {
        echo "Error generating single key: " . $e->getMessage() . "\n\n";
    }
    
    // Add a small delay before generating multiple keys
    sleep(1);
    
    // Option 2: Generate multiple keys
    echo "=== MULTIPLE KEYS GENERATION ===\n";
    $multipleKeys = $generator->generateMultipleKeys();
    
    if (!empty($multipleKeys)) {
        $generator->displayKeys($multipleKeys);
    } else {
        echo "No keys were generated successfully.\n\n";
    }
    
    // Option 3: View active keys
    echo "\n=== ACTIVE KEYS ===\n";
    $activeKeys = $generator->getActiveKeys();
    
    if (empty($activeKeys)) {
        echo "No active keys found.\n";
    } else {
        foreach ($activeKeys as $key) {
            echo "UID: {$key['uid']}, Preview: {$key['key_preview']}..., ";
            echo "Uses: {$key['usage_count']}/{$key['max_uses']}, ";
            echo "Expires: " . ($key['expires_at'] ?: 'Never') . ", ";
            echo "Created: {$key['created_at']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Make sure to create the database tables first!\n";
}
?>