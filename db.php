<?php
// db.php - Database connection setup

$host = 'localhost';
$dbname = 'student_registration';
$username = 'root';
$password = ''; // Default XAMPP password is empty

try {
    // Create a new PDO instance to connect to MySQL
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set the PDO error mode to exception for better error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    // If connection fails, display an error message and terminate the script
    die("Database connection failed: " . $e->getMessage());
}
?>
