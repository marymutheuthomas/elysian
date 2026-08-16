<?php

$env_path = __DIR__ . '/../.env';
$env = file_exists($env_path) ? parse_ini_file($env_path) : false;
if (!is_array($env)) {
    $env = [];
}

$host = $env['DB_HOST'] ?? '127.0.0.1'; // MUST be 127.0.0.1, NOT localhost
$port = $env['DB_PORT'] ?? '3307';
$db   = $env['DB_NAME'] ?? 'elysian_success';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '1234';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed. Details: " . $e->getMessage());
}