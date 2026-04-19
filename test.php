<?php
// ArtisanConnect NG — Server Diagnostic
// Upload this, visit it in browser, then DELETE it after fixing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<pre style="font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0;font-size:13px">';
echo "=== ArtisanConnect NG — Server Diagnostic ===\n\n";

// PHP Version
echo "PHP Version:        " . PHP_VERSION . "\n";
echo "PHP SAPI:           " . php_sapi_name() . "\n\n";

// Required Extensions
$extensions = ['pdo', 'pdo_mysql', 'session', 'fileinfo', 'mbstring', 'json'];
echo "--- Extensions ---\n";
foreach ($extensions as $ext) {
    echo str_pad($ext, 20) . (extension_loaded($ext) ? "✓ OK" : "✗ MISSING") . "\n";
}

// Functions
echo "\n--- Functions ---\n";
$funcs = ['session_start', 'password_hash', 'password_verify', 'random_bytes', 'ini_set'];
foreach ($funcs as $fn) {
    echo str_pad($fn, 20) . (function_exists($fn) ? "✓ OK" : "✗ MISSING") . "\n";
}

// ini_set restrictions
echo "\n--- ini_set Tests ---\n";
$tests = ['session.cookie_httponly', 'session.use_strict_mode', 'display_errors'];
foreach ($tests as $k) {
    $r = @ini_set($k, 1);
    echo str_pad($k, 30) . ($r !== false ? "✓ Allowed" : "✗ Restricted") . "\n";
}

// Session test
echo "\n--- Session ---\n";
if (session_status() === PHP_SESSION_NONE) {
    $s = @session_start();
    echo "session_start():    " . ($s ? "✓ OK" : "✗ FAILED") . "\n";
} else {
    echo "session_start():    ✓ Already active\n";
}

// DB Connection test
echo "\n--- Database ---\n";
$host = 'localhost';
$name = 'artisanconnect';
$user = 'your_db_user';   // ← Replace with real creds to test DB
$pass = 'your_db_pass';   // ← Replace with real creds to test DB
try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
    echo "DB Connection:      ✓ OK\n";
    $v = $pdo->query("SELECT VERSION()")->fetchColumn();
    echo "MySQL Version:      $v\n";
} catch (PDOException $e) {
    echo "DB Connection:      ✗ FAILED\n";
    echo "Error:              " . $e->getMessage() . "\n";
}

// Upload folder
echo "\n--- Directories ---\n";
$dirs = [
    __DIR__ . '/uploads',
    __DIR__ . '/uploads/portfolio',
    __DIR__ . '/uploads/avatar',
];
foreach ($dirs as $d) {
    $exists  = is_dir($d);
    $writable = $exists && is_writable($d);
    echo str_pad(basename($d) . '/', 20) . ($exists ? "✓ Exists " : "✗ Missing") . ($writable ? " ✓ Writable" : " ✗ Not Writable") . "\n";
}

echo "\n--- Server Info ---\n";
echo "SERVER_SOFTWARE:    " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n";
echo "DOCUMENT_ROOT:      " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "\n";

echo "\n✓ Diagnostic complete. DELETE this file after reviewing.\n";
echo '</pre>';
