-- Task Manager Database Schema
-- MySQL 8.0+ Compatible

-- Create Database
CREATE DATABASE IF NOT EXISTS task_manager;
USE task_manager;

-- Set charset and collation
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ================================================
-- TABLE: users
-- ================================================
CREATE TABLE `users` (
    `uid` CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `avatar` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `job_title` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `email_verified` BOOLEAN DEFAULT FALSE,
    `verification_token` VARCHAR(100) DEFAULT NULL,
    `reset_token` VARCHAR(100) DEFAULT NULL,
    `reset_expires` DATETIME DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `login_attempts` INT(11) DEFAULT 0,
    `account_locked_until` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`uid`),
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: categories
-- ================================================
CREATE TABLE `categories` (
    `id` CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `color` VARCHAR(7) DEFAULT '#667eea',
    `icon` VARCHAR(50) DEFAULT 'folder',
    `created_by` INT(11) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: tasks
-- ================================================
CREATE TABLE `tasks` (
    `id` CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    `assigned_to` INT(11) NOT NULL,
    `assigned_by` INT(11) NOT NULL,
    `category_id` INT(11) DEFAULT NULL,
    `due_date` DATETIME DEFAULT NULL,
    `start_date` DATETIME DEFAULT NULL,
    `completed_date` DATETIME DEFAULT NULL,
    `estimated_hours` DECIMAL(5,2) DEFAULT NULL,
    `actual_hours` DECIMAL(5,2) DEFAULT NULL,
    `progress` INT(11) DEFAULT 0,
    `tags` JSON DEFAULT NULL,
    `attachments` JSON DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_status` (`status`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_assigned_to` (`assigned_to`),
    INDEX `idx_assigned_by` (`assigned_by`),
    INDEX `idx_due_date` (`due_date`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: task_comments
-- ================================================
CREATE TABLE `task_comments` (
    `id` CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
    `task_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `comment` TEXT NOT NULL,
    `attachments` JSON DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_task_id` (`task_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: task_history
-- ================================================
CREATE TABLE `task_history` (
    `id` CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
    `task_id` INT(11) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `field_name` VARCHAR(50) DEFAULT NULL,
    `old_value` TEXT DEFAULT NULL,
    `new_value` TEXT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_task_id` (`task_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: notifications
-- ================================================
CREATE TABLE `notifications` (
    `id` CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
    `user_id` INT(11) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `data` JSON DEFAULT NULL,
    `read_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_read_at` (`read_at`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: email_logs
-- ================================================
CREATE TABLE `email_logs` (
    `id` CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
    `to_email` VARCHAR(255) NOT NULL,
    `from_email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `status` ENUM('sent', 'failed', 'pending') NOT NULL DEFAULT 'pending',
    `error_message` TEXT DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_to_email` (`to_email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: user_sessions
-- ================================================
CREATE TABLE `user_sessions` (
    `id` CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
    `user_id` INT(11) NOT NULL,
    `session_id` VARCHAR(128) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `session_id` (`session_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: system_settings
-- ================================================
CREATE TABLE `system_settings` (
    `id` CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT DEFAULT NULL,
    `setting_type` ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    `description` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- TABLE: file_uploads
-- ================================================
CREATE TABLE `file_uploads` (
    `id` CHAR(36) PRIMARY KEY NOT NULL DEFAULT (UUID()),
    `original_name` VARCHAR(255) NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` INT(11) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `uploaded_by` INT(11) NOT NULL,
    `related_type` ENUM('task', 'comment', 'user', 'other') DEFAULT 'other',
    `related_id` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_uploaded_by` (`uploaded_by`),
    INDEX `idx_related_type_id` (`related_type`, `related_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- VIEWS
-- ================================================

-- View for task statistics
CREATE VIEW `task_stats` AS
SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tasks,
    SUM(CASE WHEN due_date < NOW() AND status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue_tasks,
    SUM(CASE WHEN priority = 'high' OR priority = 'urgent' THEN 1 ELSE 0 END) as high_priority_tasks
FROM tasks;

-- View for user task summary
CREATE VIEW `user_task_summary` AS
SELECT 
    u.id as user_id,
    u.username,
    u.first_name,
    u.last_name,
    COUNT(t.id) as total_assigned_tasks,
    SUM(CASE WHEN t.status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
    SUM(CASE WHEN t.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
    SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
    SUM(CASE WHEN t.due_date < NOW() AND t.status NOT IN ('completed', 'cancelled') THEN 1 ELSE 0 END) as overdue_tasks
FROM users u
LEFT JOIN tasks t ON u.id = t.assigned_to
WHERE u.role = 'user' AND u.status = 'active'
GROUP BY u.id, u.username, u.first_name, u.last_name;

-- ================================================
-- TRIGGERS
-- ================================================

-- Trigger to log task status changes
DELIMITER //
CREATE TRIGGER `task_status_change_log` 
AFTER UPDATE ON `tasks`
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO task_history (task_id, user_id, action, field_name, old_value, new_value, description)
        VALUES (NEW.id, NEW.assigned_to, 'status_change', 'status', OLD.status, NEW.status, 
                CONCAT('Task status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
    
    -- Log completion date
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE tasks SET completed_date = NOW() WHERE id = NEW.id;
    END IF;
END//

-- Trigger to create notification on task assignment
CREATE TRIGGER `task_assignment_notification`
AFTER INSERT ON `tasks`
FOR EACH ROW
BEGIN
    INSERT INTO notifications (user_id, type, title, message, data)
    VALUES (NEW.assigned_to, 'task_assigned', 'New Task Assigned', 
            CONCAT('You have been assigned a new task: ', NEW.title),
            JSON_OBJECT('task_id', NEW.id, 'task_title', NEW.title, 'assigned_by', NEW.assigned_by));
END//

-- Trigger to clean up old sessions
CREATE TRIGGER `clean_expired_sessions`
AFTER INSERT ON `user_sessions`
FOR EACH ROW
BEGIN
    DELETE FROM user_sessions WHERE expires_at < NOW();
END//

DELIMITER ;

-- ================================================
-- INDEXES FOR PERFORMANCE
-- ================================================

-- Composite indexes for common queries
CREATE INDEX `idx_tasks_assigned_status` ON `tasks` (`assigned_to`, `status`);
CREATE INDEX `idx_tasks_due_status` ON `tasks` (`due_date`, `status`);
CREATE INDEX `idx_tasks_priority_status` ON `tasks` (`priority`, `status`);
CREATE INDEX `idx_notifications_user_read` ON `notifications` (`user_id`, `read_at`);

