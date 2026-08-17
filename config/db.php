<?php
$host   = getenv('DB_HOST') ?: 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port   = getenv('DB_PORT') ?: '4000';
$dbname = getenv('DB_NAME') ?: 'elysian_success';
$user   = getenv('DB_USER') ?: '6SwLSc9PBJx7qjm.root';
$pass   = getenv('DB_PASS') ?: 'b69oxALF6wMOGkNk';

// Locate system SSL CA bundle in Vercel Linux environment
$ca_certs = [
    '/etc/ssl/certs/ca-certificates.crt',
    '/etc/pki/tls/certs/ca-bundle.crt',
    '/etc/ssl/cert.pem',
];

$ssl_ca_file = null;
foreach ($ca_certs as $cert) {
    if (file_exists($cert)) {
        $ssl_ca_file = $cert;
        break;
    }
}

// Resolve SSL constants across PHP versions (PHP 8.5+ compatible)
$ssl_ca_key = defined('Pdo\Mysql::ATTR_SSL_CA')
    ? \Pdo\Mysql::ATTR_SSL_CA
    : (defined('PDO::MYSQL_ATTR_SSL_CA') ? PDO::MYSQL_ATTR_SSL_CA : 1002);

$ssl_verify_key = defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
    ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT
    : (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') ? PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT : 1014);

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    $ssl_ca_key                  => $ssl_ca_file ?: '',
    $ssl_verify_key              => false,
];

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}