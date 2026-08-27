<?php
// diag.php — Database connectivity diagnostics for Vercel/TiDB Cloud.
// Visit this page directly in a browser after deploying to see exactly
// where a connection attempt fails, instead of just the generic error
// config/db.php shows on every other page.
header('Content-Type: text/plain');
echo "=== VERCEL TO TIDB CLOUD DIAGNOSTICS ===\n\n";

echo "[1] PHP & EXTENSIONS:\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PDO Loaded: " . (extension_loaded('pdo') ? 'YES' : 'NO') . "\n";
echo "PDO_MySQL Loaded: " . (extension_loaded('pdo_mysql') ? 'YES' : 'NO') . "\n";
echo "OpenSSL Extension: " . (extension_loaded('openssl') ? 'YES' : 'NO') . "\n";
echo "OpenSSL Version: " . (defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'n/a') . "\n\n";

echo "[2] ENVIRONMENT VARIABLES:\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: 'not set — using fallback') . "\n";
echo "DB_PORT: " . (getenv('DB_PORT') ?: 'not set — using fallback') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ? 'set' : 'not set — using fallback') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: 'not set — using fallback') . "\n\n";

echo "[3] CA CERTIFICATE BUNDLE:\n";
$ca_path = __DIR__ . '/config/cacert.pem';
if (file_exists($ca_path)) {
    echo "Bundled CA file found at $ca_path (" . filesize($ca_path) . " bytes)\n\n";
} else {
    echo "MISSING at $ca_path — this is required for the connection to work.\n\n";
}

echo "[4] CONNECTION TEST (via config/db.php):\n";
try {
    // @ suppresses a harmless session_set_save_handler() warning specific to
    // this page: the header() call above makes headers_sent() report true
    // by the time config/db.php tries to register the DB session handler,
    // which real pages never hit since they require config/db.php first.
    // It doesn't affect the connection test result either way.
    @require __DIR__ . '/config/db.php';
    $ver = $pdo->query('SELECT VERSION() as v')->fetch();
    echo "SUCCESS — connected. Server version: " . $ver['v'] . "\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables visible: " . count($tables) . "\n";
} catch (Throwable $t) {
    echo "FAILED: " . $t->getMessage() . "\n";
}
