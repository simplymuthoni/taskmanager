**Project Overview**

A comprehensive task management system built with PHP and MySQL that allows administrators to manage users and tasks while enabling users to track their assigned tasks.

**System Architecture**
 
**Tech Stack**

    Backend: PHP 7.4+ with OOP principles
    Database: MySQL 8.0
    Frontend: HTML5, CSS3, Vanilla JavaScript
    Email: PHP mail() function
    Authentication: Session-based authentication

Features Implemented

    ✅ Admin user management (CRUD operations)
    ✅ Task assignment with deadlines
    ✅ Task status tracking (Pending, In Progress, Completed)
    ✅ User dashboard for task viewing and updates
    ✅ Email notifications for new task assignments
    ✅ Responsive design with modern UI
    ✅ Input validation and security measures

Project Structure

    task-manager/
    ├── admin/
    │   ├── dashboard.php
        |---register.php
    │   ├── users.php
    │   └── tasks.php
    ├── user/
    │   └── dashboard.php
    ├── includes/
        |--- adminkeyhandler.php
        |--- adminmanager.php
    │   ├── config.php
    │   ├── db.php
    |   |--- emailmanager.php
        |--- emailnotifications.php
        |--- emailservice.php
    │   ├── functions.php
    │   └── mailer.php
    |   |---taskmanager.php
    |   |--- usermanager.php
    ├── assets/
    │   ├── style.css
    │   └── script.js
    |---Vendor/
        |---composer/
        |---phpmailer/
        |--autoload.php
    |---.env
    |--- activekeys.txt
    |--- adminkeygeneration.php
    |---composer.json
    |---composer.lock
    |---forgot-password.php
    ├── index.php
    ├── login.php
    ├── register.php
    ├── logout.php
    └── task_manager.sql
    |--- resend-verifaction.php

** Database Schema**
 
    +------------------------+
    | Tables_in_task_manager |
    +------------------------+
    | admin_key_attempts     |
    | admin_key_history      |
    | admin_keys             |
    | admin_registrations    |
    | tasks                  |
    | users                  |
    | users_backup           |
    +------------------------+
DESC admin_key_attempts;
        +-----------------+--------------+------+-----+---------------------+-------+
        | Field           | Type         | Null | Key | Default             | Extra |
        +-----------------+--------------+------+-----+---------------------+-------+
        | uid             | varchar(36)  | NO   | PRI | uuid()              |       |
        | key_hash        | varchar(255) | NO   |     | NULL                |       |
        | ip_address      | varchar(45)  | YES  | MUL | NULL                |       |
        | success         | tinyint(1)   | YES  | MUL | 0                   |       |
        | user_agent      | text         | YES  |     | NULL                |       |
        | created_at      | timestamp    | YES  | MUL | current_timestamp() |       |
        | failure_reason  | text         | YES  |     | NULL                |       |
        | email_attempted | varchar(100) | YES  |     | NULL                |       |
        +-----------------+--------------+------+-----+---------------------+-------+
DESC admin_key_history;
+--------------+--------------+------+-----+---------------------+----------------+
| Field        | Type         | Null | Key | Default             | Extra          |
+--------------+--------------+------+-----+---------------------+----------------+
| id           | int(11)      | NO   | PRI | NULL                | auto_increment |
| admin_uid    | varchar(36)  | NO   | MUL | NULL                |                |
| action       | varchar(50)  | NO   | MUL | NULL                |                |
| performed_by | varchar(100) | YES  |     | NULL                |                |
| ip_address   | varchar(45)  | YES  |     | NULL                |                |
| details      | longtext     | YES  |     | NULL                |                |
| created_at   | timestamp    | YES  | MUL | current_timestamp() |                |
+--------------+--------------+------+-----+---------------------+----------------+
DESC admin_keys;
+--------------------------+--------------+------+-----+---------------------+-------+
| Field                    | Type         | Null | Key | Default             | Extra |
+--------------------------+--------------+------+-----+---------------------+-------+
| uid                      | char(36)     | NO   | PRI | uuid()              |       |
| key_hash                 | varchar(255) | NO   | UNI | NULL                |       |
| created_at               | timestamp    | YES  | MUL | current_timestamp() |       |
| expires_at               | timestamp    | YES  |     | NULL                |       |
| is_active                | tinyint(1)   | YES  | MUL | 1                   |       |
| created_by               | varchar(100) | YES  |     | NULL                |       |
| usage_count              | int(11)      | YES  |     | 0                   |       |
| max_uses                 | int(11)      | YES  |     | NULL                |       |
| revoked_at               | timestamp    | YES  |     | NULL                |       |
| notes                    | text         | YES  |     | NULL                |       |
| department_restriction   | varchar(50)  | YES  |     | NULL                |       |
| permissions              | text         | YES  |     | NULL                |       |
| email_domain_restriction | varchar(100) | YES  |     | NULL                |       |
| revoked_by               | varchar(100) | YES  |     | NULL                |       |
| disabled_reason          | text         | YES  |     | NULL                |       |
| disabled_at              | timestamp    | YES  |     | NULL                |       |
+--------------------------+--------------+------+-----+---------------------+-------+
DESC admin_registrations;
+-----------------------+--------------+------+-----+---------------------+-------+
| Field                 | Type         | Null | Key | Default             | Extra |
+-----------------------+--------------+------+-----+---------------------+-------+
| uid                   | char(36)     | NO   | PRI | uuid()              |       |
| user_uid              | char(36)     | NO   | MUL | NULL                |       |
| registered_by         | varchar(100) | YES  |     | NULL                |       |
| registration_key_used | varchar(255) | YES  |     | NULL                |       |
| ip_address            | varchar(45)  | YES  |     | NULL                |       |
| user_agent            | text         | YES  |     | NULL                |       |
| created_at            | timestamp    | YES  | MUL | current_timestamp() |       |
+-----------------------+--------------+------+-----+---------------------+-------+
DESC tasks;
+-------------+-------------------------------------------+------+-----+---------------------+-------+
| Field       | Type                                      | Null | Key | Default             | Extra |
+-------------+-------------------------------------------+------+-----+---------------------+-------+
| id          | char(36)                                  | NO   | PRI | NULL                |       |
| title       | varchar(255)                              | NO   |     | NULL                |       |
| description | text                                      | YES  |     | NULL                |       |
| assigned_to | char(36)                                  | NO   | MUL | NULL                |       |
| deadline    | date                                      | YES  |     | NULL                |       |
| status      | enum('Pending','In Progress','Completed') | YES  |     | Pending             |       |
| created_at  | timestamp                                 | YES  |     | current_timestamp() |       |
| assigned_by | char(36)                                  | NO   | MUL | NULL                |       |
+-------------+-------------------------------------------+------+-----+---------------------+-------+
DESC users;
+----------------------+---------------------------------------+------+-----+---------------------+-------------------------------+
| Field                | Type                                  | Null | Key | Default             | Extra                         |
+----------------------+---------------------------------------+------+-----+---------------------+-------------------------------+
| uid                  | char(36)                              | NO   | PRI | NULL                |                               |
| username             | varchar(50)                           | YES  | UNI | NULL                |                               |
| name                 | varchar(100)                          | NO   |     | NULL                |                               |
| email                | varchar(255)                          | NO   | UNI | NULL                |                               |
| password             | varchar(255)                          | NO   |     | NULL                |                               |
| role                 | enum('admin','user')                  | YES  | MUL | user                |                               |
| avatar               | varchar(255)                          | YES  |     | NULL                |                               |
| phone                | varchar(20)                           | YES  |     | NULL                |                               |
| department           | varchar(100)                          | YES  |     | NULL                |                               |
| job_title            | varchar(100)                          | YES  |     | NULL                |                               |
| status               | enum('active','inactive','suspended') | NO   | MUL | active              |                               |
| email_verified       | tinyint(1)                            | YES  |     | 0                   |                               |
| verification_token   | varchar(100)                          | YES  |     | NULL                |                               |
| reset_token          | varchar(100)                          | YES  |     | NULL                |                               |
| reset_expires        | datetime                              | YES  |     | NULL                |                               |
| last_login           | datetime                              | YES  |     | NULL                |                               |
| login_attempts       | int(11)                               | YES  |     | 0                   |                               |
| account_locked_until | datetime                              | YES  |     | NULL                |                               |
| created_at           | timestamp                             | YES  |     | current_timestamp() |                               |
| updated_at           | timestamp                             | YES  |     | current_timestamp() | on update current_timestamp() |
+----------------------+---------------------------------------+------+-----+---------------------+-------------------------------+



