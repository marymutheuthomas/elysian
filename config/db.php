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

// Retry a few times before giving up. This won't help if there's genuinely
// no network path to TiDB (e.g. your machine's own DNS/internet is down —
// no amount of retrying from inside PHP fixes that), but it does ride out
// brief, transient blips — a DNS resolver hiccup, a momentary gateway
// timeout — instead of failing the whole page on the first stumble.
$max_attempts = 3;
$last_exception = null;
for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
    try {
        // sslmode=REQUIRED makes mysqlnd refuse the connection outright if SSL
        // can't be negotiated, instead of silently falling back to plaintext
        // (which is what let this fail server-side at TiDB instead of here).
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4;sslmode=REQUIRED", $user, $pass, $options);
        $last_exception = null;
        break;
    } catch (PDOException $e) {
        $last_exception = $e;
        if ($attempt < $max_attempts) {
            usleep(300000); // 300ms
        }
    }
}
if ($last_exception) {
    die("Database connection failed: " . $last_exception->getMessage());
}

// Store PHP sessions in the database instead of local disk. On Vercel,
// each request can land on a different serverless container with its own
// ephemeral filesystem, so a locally-saved session file may not exist on
// the next request — the student/mentor gets silently logged out mid-use.
// This must run before any session_start() call, which every entry point
// already does after requiring this file.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sessions` (
        `id` VARCHAR(128) NOT NULL PRIMARY KEY,
        `data` TEXT NOT NULL,
        `last_activity` INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    require_once __DIR__ . '/../includes/db_session_handler.php';
    $session_lifetime = (int) ini_get('session.gc_maxlifetime');
    session_set_save_handler(new DbSessionHandler($pdo, $session_lifetime), true);
} catch (Throwable $t) {
    // If this ever fails (e.g. restricted DB privileges), fall back to
    // PHP's default file-based sessions rather than breaking every page.
}