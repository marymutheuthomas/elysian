<?php
$host   = getenv('DB_HOST') ?: 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port   = getenv('DB_PORT') ?: '4000';
$dbname = getenv('DB_NAME') ?: 'elysian_success';
$user   = getenv('DB_USER') ?: '6SwLSc9PBJx7qjm.root';
$pass   = getenv('DB_PASS') ?: 'b69oxALF6wMOGkNk';

try {
    // Resolve PHP 8.5+ constant compatibility
    $ssl_verify_key = defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') 
        ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT 
        : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        $ssl_verify_key              => false, // Forces SSL transport without requiring a local ca.pem path
    ];

    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}