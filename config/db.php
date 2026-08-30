<?php

$host = "127.0.0.1";
$dbname = "skillsync_db";
$username = "root";
$password = "";

try {
    // First try connecting directly to the target database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // If database does not exist (error 1049), create it and import schema
    if (strpos($e->getMessage(), "1049") !== false || strpos($e->getMessage(), "Unknown database") !== false) {
        try {
            $pdoRoot = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            
            $sqlFile = __DIR__ . '/../skillsync_db.sql';
            if (file_exists($sqlFile)) {
                $sqlContent = file_get_contents($sqlFile);
                $pdo->exec($sqlContent);
            }
        } catch (PDOException $ex) {
            die("Database setup failed: " . $ex->getMessage());
        }
    } else {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Auto-initialize password_resets table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_type` ENUM('user', 'company') NOT NULL DEFAULT 'user',
        `account_id` INT(11) NOT NULL,
        `identity` VARCHAR(150) NOT NULL,
        `account_name` VARCHAR(200) NOT NULL,
        `account_email` VARCHAR(150) NOT NULL,
        `status` ENUM('Pending', 'Approved', 'Rejected', 'Completed') NOT NULL DEFAULT 'Pending',
        `reset_token` VARCHAR(64) NULL,
        `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `approved_at` DATETIME NULL,
        `completed_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        INDEX `idx_status` (`status`),
        INDEX `idx_account` (`user_type`, `account_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Exception $e) {
    // Ignore if table cannot be created or already exists
}

// Auto-add companyid and vacancyid to calendar table if not existing
try {
    $calCols = $pdo->query("SHOW COLUMNS FROM calendar LIKE 'companyid'")->fetchAll();
    if (empty($calCols)) {
        $pdo->exec("ALTER TABLE calendar ADD COLUMN companyid INT(11) NULL AFTER userid");
    }
    $calVacCols = $pdo->query("SHOW COLUMNS FROM calendar LIKE 'vacancyid'")->fetchAll();
    if (empty($calVacCols)) {
        $pdo->exec("ALTER TABLE calendar ADD COLUMN vacancyid INT(11) NULL AFTER companyid");
    }
} catch (Exception $e) {
    // Ignore if columns cannot be checked or added
}

