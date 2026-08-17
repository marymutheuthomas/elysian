<?php
$host   = getenv('DB_HOST') ?: 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port   = getenv('DB_PORT') ?: '4000';
$dbname = getenv('DB_NAME') ?: 'elysian_success';
$user   = getenv('DB_USER') ?: '6SwLSc9PBJx7qjm.root';
$pass   = getenv('DB_PASS') ?: 'b69oxALF6wMOGkNk';

// Store the CA bundle in Vercel's writable temporary directory
$ca_path = sys_get_temp_dir() . '/cacert.pem';

// Download Mozilla's official root CA bundle if missing or incomplete
if (!file_exists($ca_path) || filesize($ca_path) < 1000) {
    $ca_data = @file_get_contents('https://curl.se/ca/cacert.pem');
    if ($ca_data !== false) {
        file_put_contents($ca_path, $ca_data);
    }
}

// Resolve PHP SSL attributes across PHP versions
$ssl_ca_key = defined('Pdo\Mysql::ATTR_SSL_CA')
    ? \Pdo\Mysql::ATTR_SSL_CA
    : (defined('PDO::MYSQL_ATTR_SSL_CA') ? PDO::MYSQL_ATTR_SSL_CA : 1002);

$ssl_verify_key = defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
    ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT
    : (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') ? PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT : 1014);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    $ssl_ca_key                  => $ca_path,
    $ssl_verify_key              => false,
];

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}