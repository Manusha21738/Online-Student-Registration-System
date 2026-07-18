<?php
require_once 'db.php';

try {
    echo "--- Users Table Structure ---\n";
    $stmt = $pdo->query("SHOW CREATE TABLE users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($result);
    
    echo "\n--- Count of Users ---\n";
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Role: {$row['role']}, Count: {$row['count']}\n";
    }
    
    echo "\n--- Programme Table ---\n";
    $stmt = $pdo->query("SELECT * FROM programme");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Name: {$row['name']}, Duration: {$row['duration']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
