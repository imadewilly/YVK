<?php

/**
 * Database Configuration Example
 * Copy this file to database.php and update with your hosting credentials
 */

function getDatabaseConnection(): PDO
{
    // LOCAL DEVELOPMENT (XAMPP)
    // $host = 'localhost';
    // $db   = 'yvk_store';
    // $user = 'root';
    // $pass = '';
    
    // PRODUCTION (HOSTING)
    // Update these values with your hosting database credentials
    $host = 'localhost'; // Usually 'localhost' or provided by hosting
    $db   = 'your_database_name'; // Database name from hosting
    $user = 'your_database_user'; // Database username from hosting
    $pass = 'your_database_password'; // Database password from hosting
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Don't expose database credentials in production
        error_log('Database connection failed: ' . $e->getMessage());
        die('Database connection failed. Please contact administrator.');
    }
}

