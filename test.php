<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<pre style="font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0;font-size:13px">';
echo "=== ArtisanConnect NG -- Deep Error Trace ===\n\n";

// Test 1: config.php
echo "--- 1. config.php ---\n";
ob_start();
try {
    require_once __DIR__ . '/config.php';
    ob_end_clean();
    echo "config.php:       OK\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "config.php:       FAILED\n";
    echo "  Error:  " . $e->getMessage() . "\n";
    echo "  Line:   " . $e->getLine() . "\n";
    echo "  File:   " . $e->getFile() . "\n";
    die('</pre>');
}

// Test 2: css() function
echo "\n--- 2. css() function ---\n";
try {
    $css = css();
    echo "css():            OK (" . strlen($css) . " bytes)\n";
} catch (Throwable $e) {
    echo "css():            FAILED -- " . $e->getMessage() . " on line " . $e->getLine() . "\n";
}

// Test 3: head() function
echo "\n--- 3. head() function ---\n";
ob_start();
try {
    head('Test Page');
    $out = ob_get_clean();
    echo "head():           OK (" . strlen($out) . " bytes output)\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "head():           FAILED -- " . $e->getMessage() . " on line " . $e->getLine() . "\n";
}

// Test 4: DB via db()
echo "\n--- 4. db() connection ---\n";
try {
    $cnt = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "db():             OK -- users: $cnt\n";
    $cnt2 = db()->query("SELECT COUNT(*) FROM artisan_profiles")->fetchColumn();
    echo "artisan_profiles: $cnt2 rows\n";
    $cnt3 = db()->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    echo "bookings:         $cnt3 rows\n";
} catch (Throwable $e) {
    echo "db():             FAILED -- " . $e->getMessage() . "\n";
}

// Test 5: index.php
echo "\n--- 5. index.php ---\n";
ob_start();
try {
    // Reset superglobals to simulate clean GET request
    $_GET  = array();
    $_POST = array();
    include __DIR__ . '/index.php';
    $out = ob_get_clean();
    echo "index.php:        OK (" . strlen($out) . " bytes output)\n";
    // Check for obvious error strings in output
    if (stripos($out, 'fatal error') !== false)   echo "  WARNING: 'Fatal error' found in output!\n";
    if (stripos($out, 'parse error') !== false)   echo "  WARNING: 'Parse error' found in output!\n";
    if (stripos($out, 'undefined')   !== false)   echo "  WARNING: 'Undefined' found in output!\n";
    if (strpos($out, '<html') !== false)          echo "  HTML:    <html> tag present -- looks good\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "index.php:        FAILED\n";
    echo "  Error:  " . $e->getMessage() . "\n";
    echo "  Line:   " . $e->getLine() . "\n";
    echo "  File:   " . $e->getFile() . "\n";
}

// Test 6: auth.php
echo "\n--- 6. auth.php ---\n";
ob_start();
try {
    $_GET  = array('tab' => 'login');
    $_POST = array();
    include __DIR__ . '/auth.php';
    $out = ob_get_clean();
    echo "auth.php:         OK (" . strlen($out) . " bytes output)\n";
    if (strpos($out, '<html') !== false) echo "  HTML:    <html> tag present -- looks good\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "auth.php:         FAILED\n";
    echo "  Error:  " . $e->getMessage() . "\n";
    echo "  Line:   " . $e->getLine() . "\n";
    echo "  File:   " . $e->getFile() . "\n";
}

// Test 7: Session
echo "\n--- 7. Session ---\n";
echo "Status:           " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Inactive") . "\n";
echo "Session ID:       " . session_id() . "\n";

// Test 8: File permissions
echo "\n--- 8. Uploads ---\n";
$dirs = array('uploads', 'uploads/portfolio', 'uploads/avatar');
foreach ($dirs as $d) {
    $path = __DIR__ . '/' . $d;
    echo str_pad($d, 22) . (is_dir($path) ? "exists " : "MISSING") . (is_writable($path) ? " writable" : " NOT writable") . "\n";
}

echo "\n\nDELETE test.php from server after reading this!\n";
echo '</pre>';
