<?php
$host   = getenv('DB_HOST') ?: 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port   = getenv('DB_PORT') ?: '4000';
$dbname = getenv('DB_NAME') ?: 'elysian_success';
$user   = getenv('DB_USER') ?: '6SwLSc9PBJx7qjm.root';
$pass   = getenv('DB_PASS') ?: 'b69oxALF6wMOGkNk';

try {
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA       => true,
    ];

    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}