<?php
$host   = getenv('DB_HOST') ?: 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port   = getenv('DB_PORT') ?: '4000';
$dbname = getenv('DB_NAME') ?: 'elysian_success';
$user   = getenv('DB_USER') ?: '6SwLSc9PBJx7qjm.root';
$pass   = getenv('DB_PASS') ?: 'b69oxALF6wMOGkNk';

// CA bundle is committed to the repo (config/cacert.pem) rather than fetched
// at request time. The previous approach downloaded it into sys_get_temp_dir()
// on every cold start; if that fetch silently failed (blocked/slow egress,
// no network yet during Vercel's PHP init), PDO::MYSQL_ATTR_SSL_CA ended up
// pointing at a file that didn't exist. mysqlnd doesn't error on that — it
// just skips SSL entirely, which is exactly what produced TiDB's "insecure
// transport" rejection. A bundled file removes that failure mode completely:
// it's part of the deployed source, so it's always present.
$ca_path = __DIR__ . '/cacert.pem';
if (!file_exists($ca_path)) {
    die("Database connection failed: CA bundle missing at $ca_path — expected config/cacert.pem to be committed to the repo.");
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
    // sslmode=REQUIRED makes mysqlnd refuse the connection outright if SSL
    // can't be negotiated, instead of silently falling back to plaintext
    // (which is what let this fail server-side at TiDB instead of here).
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4;sslmode=REQUIRED", $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}