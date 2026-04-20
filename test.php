<?php
require_once 'config.php';  // uses the real credentials from config.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<pre style="font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0;font-size:13px">';
echo "=== ArtisanConnect NG — DB Diagnostic ===\n\n";

echo "DB_HOST:  " . DB_HOST . "\n";
echo "DB_NAME:  " . DB_NAME . "\n";
echo "DB_USER:  " . DB_USER . "\n";
echo "DB_PASS:  " . str_repeat('*', strlen(DB_PASS)) . "\n\n";

// Test 1: raw TCP socket
echo "--- Test 1: TCP Socket to DB_HOST:3306 ---\n";
$sock = @fsockopen(DB_HOST, 3306, $errno, $errstr, 5);
if ($sock) {
    echo "TCP Connection:     ✓ Port 3306 reachable\n";
    fclose($sock);
} else {
    echo "TCP Connection:     ✗ FAILED — $errno: $errstr\n";
    echo "  → Your host may block outbound 3306. Try port 3307 below.\n";
}

// Test 2: PDO with port 3306
echo "\n--- Test 2: PDO Connection (port 3306) ---\n";
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=3306;dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    echo "PDO (3306):         ✓ CONNECTED\n";
    echo "MySQL Version:      " . $pdo->query("SELECT VERSION()")->fetchColumn() . "\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found:       " . implode(', ', $tables) . "\n";
} catch (PDOException $e) {
    echo "PDO (3306):         ✗ FAILED\n";
    echo "Error:              " . $e->getMessage() . "\n";
}

// Test 3: PDO with port 3307 (some shared hosts use this)
echo "\n--- Test 3: PDO Connection (port 3307) ---\n";
try {
    $pdo2 = new PDO(
        'mysql:host=' . DB_HOST . ';port=3307;dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    echo "PDO (3307):         ✓ CONNECTED\n";
    echo "MySQL Version:      " . $pdo2->query("SELECT VERSION()")->fetchColumn() . "\n";
} catch (PDOException $e) {
    echo "PDO (3307):         ✗ FAILED — " . $e->getMessage() . "\n";
}

// Test 4: config.php db() function
echo "\n--- Test 4: config.php db() singleton ---\n";
try {
    $result = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "db() function:      ✓ WORKS — users table has $result row(s)\n";
} catch (Throwable $e) {
    echo "db() function:      ✗ FAILED — " . $e->getMessage() . "\n";
}

echo "\n--- Session ---\n";
echo "Session status:     " . (session_status() === PHP_SESSION_ACTIVE ? "✓ Active" : "✗ Inactive") . "\n";

echo "\n✓ Done. DELETE this file after reviewing!\n";
echo '</pre>';
