<?php
$host   = getenv('DB_HOST') ?: 'gateway01.us-east-1.prod.aws.tidbcloud.com';
$port   = getenv('DB_PORT') ?: '4000';
$dbname = getenv('DB_NAME') ?: 'elysian_success';
$user   = getenv('DB_USER') ?: '6SwLSc9PBJx7qjm.root';
$pass   = getenv('DB_PASS') ?: 'b69oxALF6wMOGkNk';

// Auto-generate CA cert bundle in temporary storage if missing
$ca_path = sys_get_temp_dir() . '/tidb_ca.pem';

if (!file_exists($ca_path)) {
    $ca_content = <<<EOT
-----BEGIN CERTIFICATE-----
MIIFazCCA1OgAwIBAgIRAIIQz7DSQONZRGPgu2OCiwAwDQYJKoZIhvcNAQELBQAw
TzELMAkGA1UEBhMCVVMxKTAnBgNVBAoTIEludGVybmV0IFNlY3VyaXR5IFJlc2Vh
cmNoIEdyb3VwMRUwEwYDVQQDEwxJU1JHIFJvb3QgWDEwHhcNMTUwNjA0MTEwNDM4
WhcNMzUwNjA0MTEwNDM4WjBPMQswCQYDVQQGEwJVUzEpMCcGA1UEChMgSW50ZXJu
ZXQgU2VjdXJpdHkgUmVzZWFyY2ggR3JvdXAxFTATBgNVBAMTDElTUkcgUm9vdCBY
MTCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAK3oJer3L23tfvL/ICgJ
WLB32oZlmTO4554khRTV15uXKh88fRX3C4vvVoFZhEWCpdA25C72AQKAucDHvT8c
B93vvPXA250SiUUOHpmX4ipnd1241FZ67B76bA786QsC3/iHI6R6g7I2A71OyoXJ
X2XSpfR+5J+lX6n6G8/yU+s5I8+s7I2A71OyoXJX2XSpfR+5J+lX6n6G8/yU+s5I8
-----END CERTIFICATE-----
-----BEGIN CERTIFICATE-----
MIIDQTCCAimgAwIBAgITBmyfz5m/jAo54vB4ikPmljZbyjANBgkqhkiG9w0BAQsF
ADA5MQswCQYDVQQGEwJVUzEPMA0GA1UEChMGQW1hem9uMRkwFwYDVQQDExBBbWF6
b24gUm9vdCBDQSAxMB4XDTE1MDUyNjAwMDAwMFoXDTM4MDUyNjAwMDAwMFowOTEL
MAkGA1UEBhMCVVMxDTALBgNVBAoTBEFtYXpvbjEZMBcGA1UEAxMQQW1hem9uIFJv
b3QgQ0EgMTCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALJ4gHHKeNXj
ca9HgFB0fW7Y14h29Jlo91ghYPl0hA20J423uUTY3yZ29i40YpA74I8C3A4z6eR9
-----END CERTIFICATE-----
EOT;
    file_put_contents($ca_path, $ca_content);
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
    $ssl_ca_key                  => $ca_path,
    $ssl_verify_key              => false,
];

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}