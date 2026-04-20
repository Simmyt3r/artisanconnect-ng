<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo '<pre style="font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0;font-size:13px">';
echo "=== Deep Error Trace ===\n\n";

// Capture any fatal errors from including each file
$files = ['config.php', 'index.php', 'auth.php', 'profile.php', 'dashboard.php', 'messages.php', 'admin.php'];

foreach ($files as $f) {
    echo "--- Testing: $f ---\n";
    $output = shell_exec("php -d display_errors=1 -d error_reporting=32767 -l " . escapeshellarg(__DIR__ . '/' . $f) . " 2>&1");
    echo $output . "\n";
}

// Test config.php inclusion specifically
echo "--- Including config.php ---\n";
ob_start();
try {
    require_once __DIR__ . '/config.php';
    echo "config.php:   OK\n";
} catch (Throwable $e) {
    echo "config.php:   FAILED: " . $e->getMessage() . " on line " . $e->getLine() . "\n";
}
ob_end_clean();

// Test CSS function specifically
echo "\n--- Testing css() function ---\n";
try {
    $css = css();
    echo "css():        OK (" . strlen($css) . " bytes)\n";
} catch (Throwable $e) {
    echo "css():        FAILED: " . $e->getMessage() . "\n";
}

// Test head() function
echo "\n--- Testing head() function ---\n";
ob_start();
try {
    head('Test');
    echo "\nhead():       OK\n";
} catch (Throwable $e) {
    echo "\nhead():       FAILED: " . $e->getMessage() . " on line " . $e->getLine() . "\n";
}
ob_end_clean();

// Check for PHP fatal error handler
echo "\n--- PHP Error Log (last 20 lines) ---\n";
$logFile = ini_get('error_log');
echo "Error log:    " . ($logFile ?: 'not set') . "\n";
if ($logFile && file_exists($logFile)) {
    $lines = array_slice(file($logFile), -20);
    foreach ($lines as $line) echo $line;
} else {
    echo "(no log file accessible)\n";
}

echo "\nDELETE this file after debugging!\n";
echo '</pre>';
