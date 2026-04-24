<?php
/**
 * db.php
 * 
 * This file is responsible for establishing a secure connection to the MySQL database
 * using PHP Data Objects (PDO). It defines the connection parameters and handles
 * potential connection errors gracefully.
 */

// Database connection parameters
$host = 'localhost'; // The hostname or IP address of the database server
$dbname = 'student_registration'; // The name of the database to connect to
$username = 'root'; // The database username
$password = ''; // The database password (Default XAMPP password is empty)

try {
    // Create a new PDO instance to connect to MySQL database
    // charset=utf8mb4 ensures proper encoding for all characters including emojis
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    
    // Set the PDO error mode to exception for better error handling and debugging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
} catch (PDOException $e) {
    // If connection fails, display an error message and terminate the script
    // Note: In a production environment, avoid showing raw exception messages to the user
    die("Database connection failed: " . $e->getMessage());
}
?>
