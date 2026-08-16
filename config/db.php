<?php

$host = '127.0.0.1'; // MUST be 127.0.0.1, NOT localhost
$port = '3307';      
$db   = 'elysian_success';
$user = 'root';
$pass = '1234'; // Replace with your actual MySQL password

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed. Details: " . $e->getMessage());
}