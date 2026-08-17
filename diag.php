<?php
header('Content-Type: text/plain');
echo "=== VERCEL TO TIDB CLOUD DIAGNOSTICS ===\n\n";

// 1. PHP & Extension Check
echo "[1] PHP & EXTENSIONS:\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PDO Loaded: " . (extension_loaded('pdo') ? 'YES' : 'NO') . "\n";
echo "PDO_MySQL Loaded: " . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . "\n";
echo "OpenSSL Extension: " . (extension_loaded('openssl') ? 'YES' : 'NO') . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled') . "\n\n";

// 2. Environment Variables Check
echo "[2] ENVIRONMENT VARIABLES:\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'Fallback used') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: 'Fallback used') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ? 'Defined' : 'Fallback used') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: 'Fallback used') . "\n\n";

// 3. CA Bundle Download Test
echo "[3] CA CERTIFICATE STORAGE TEST:\n";
$ca_path = sys_get_temp_dir() . '/cacert.pem';
if (!file_exists($ca_path)) {
    echo "Downloading CA bundle to $ca_path...\n";
    $data = @file_get_contents('https://curl.se/ca/cacert.pem');
    if ($data) {
        file_put_contents($ca_path, $data);
        echo "Download SUCCESS! Size: " . strlen($data) . " bytes\n";
    } else {
        echo "Download FAILED! file_get_contents could not fetch certificate.\n";
    }
} else {
    echo "Certificate exists. Size: " . filesize($ca_path) . " bytes\n";
}
echo "\n";

// 4. Test Different Connection Strategies
echo "[4] DRIVER SSL CONNECTION TESTS:\n";

$host   = getenv('DB_HOST') ?: 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port   = getenv('DB_PORT') ?: '4000';
$dbname = getenv('DB_NAME') ?: 'elysian_success';
$user   = getenv('DB_USER') ?: '6SwLSc9PBJx7qjm.root';
$pass   = getenv('DB_PASS') ?: 'b69oxALF6wMOGkNk';

// Test 1: Standard PDO with CA File
echo "Test 1 (PDO + CA File): ";
try {
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => $ca_path,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, $opts);
    echo "SUCCESS!\n";
} catch (Exception $e) {
    echo "FAILED -> " . $e->getMessage() . "\n";
}

// Test 2: PDO with DSN Flags
echo "Test 2 (PDO DSN SSL Mode): ";
try {
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4;sslmode=verify-ca;sslca=$ca_path";
    $pdo = new PDO($dsn, $user, $pass, $opts);
    echo "SUCCESS!\n";
} catch (Exception $e) {
    echo "FAILED -> " . $e->getMessage() . "\n";
}

// Test 3: MySQLi Driver
echo "Test 3 (MySQLi SSL): ";
if (extension_loaded('mysqli')) {
    try {
        $conn = mysqli_init();
        mysqli_ssl_set($conn, NULL, NULL, file_exists($ca_path) ? $ca_path : NULL, NULL, NULL);
        if (@mysqli_real_connect($conn, $host, $user, $pass, $dbname, (int)$port, NULL, MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT)) {
            echo "SUCCESS!\n";
        } else {
            echo "FAILED -> " . mysqli_connect_error() . "\n";
        }
    } catch (Exception $e) {
        echo "FAILED -> " . $e->getMessage() . "\n";
    }
} else {
    echo "MySQLi Extension not installed.\n";
}