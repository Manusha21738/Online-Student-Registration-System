<?php
/**
 * migrate_ol_ict.php
 * 
 * Migration script to:
 * 1. Alter the course table to support price and paid status.
 * 2. Create the "Sri Lankan O/L ICT" module.
 * 3. Seed 4 separate courses (Logic Gates, HTML, Algorithms, Python).
 * 4. Seed lessons under each course.
 */
header('Content-Type: text/plain');
require_once 'db.php';

try {
    echo "This migration is deprecated. All curriculum is now unified in migrate_curriculum.php.\n";
} catch (PDOException $e) {
    die("\nMigration failed with error: " . $e->getMessage() . "\n");
}
?>
