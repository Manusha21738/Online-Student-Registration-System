<?php
/**
 * migrate_db.php
 * 
 * Database migration script to setup Teacher & Module tables,
 * remove the student foreign key constraint from users table,
 * and seed default module & admin accounts.
 */
header('Content-Type: text/plain');
require_once 'db.php';

try {
    echo "Starting Database Migration...\n\n";

    // 1. Remove foreign key constraint from users table dynamically
    echo "Step 1: Removing foreign key constraints on users table...\n";
    $stmt = $pdo->prepare("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = 'users' 
          AND REFERENCED_TABLE_NAME = 'student'
    ");
    $stmt->execute([':dbname' => $dbname]);
    $constraints = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($constraints as $constraint) {
        $pdo->exec("ALTER TABLE users DROP FOREIGN KEY `$constraint`");
        echo "Dropped foreign key constraint: $constraint\n";
    }
    echo "Foreign key constraint checks completed.\n\n";

    // 2. Create module table
    echo "Step 2: Creating module table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS module (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Module table is ready.\n\n";

    // 3. Create teacher table
    echo "Step 3: Creating teacher table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacherid VARCHAR(50) UNIQUE NOT NULL,
            fullname VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            qualifications TEXT DEFAULT NULL,
            nic VARCHAR(50) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            is_approved TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Add columns if table already exists
    try {
        $pdo->exec("ALTER TABLE teacher ADD COLUMN qualifications TEXT DEFAULT NULL AFTER email");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE teacher ADD COLUMN nic VARCHAR(50) DEFAULT NULL AFTER qualifications");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE teacher ADD COLUMN address TEXT DEFAULT NULL AFTER nic");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE teacher ADD COLUMN phone VARCHAR(50) DEFAULT NULL AFTER address");
    } catch (PDOException $e) {}
    echo "Teacher table is ready.\n\n";

    // 4. Create teacher_module table
    echo "Step 4: Creating teacher_module table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_module (
            teacher_id VARCHAR(50) NOT NULL,
            module_id INT NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            PRIMARY KEY (teacher_id, module_id),
            FOREIGN KEY (teacher_id) REFERENCES teacher(teacherid) ON DELETE CASCADE,
            FOREIGN KEY (module_id) REFERENCES module(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Teacher-module junction table is ready.\n\n";

    // 5. Seed default modules
    echo "Step 5: Seeding default modules...\n";
    $modules = [
        'ICT',
        'Mathematics'
    ];
    $stmtModule = $pdo->prepare("INSERT INTO module (name) VALUES (:name) ON DUPLICATE KEY UPDATE name=name");
    foreach ($modules as $mod) {
        $stmtModule->execute([':name' => $mod]);
        echo "Seeded module: $mod\n";
    }
    echo "Module seeding completed.\n\n";

    // 6. Seed default admin account
    echo "Step 6: Seeding default admin account...\n";
    $stmtAdminCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = 'admin'");
    $stmtAdminCheck->execute();
    if ($stmtAdminCheck->fetchColumn() == 0) {
        $hashed_password = password_hash('AdminPassword123', PASSWORD_DEFAULT);
        $insertAdmin = $pdo->prepare("INSERT INTO users (user_id, password, role) VALUES ('admin', :password, 'admin')");
        $insertAdmin->execute([':password' => $hashed_password]);
        echo "Seeded default admin (Username: admin, Password: AdminPassword123)\n";
    } else {
        echo "Admin account already exists.\n";
    }
    echo "\nDatabase Migration completed successfully!\n";

} catch (PDOException $e) {
    die("\nMigration failed with error: " . $e->getMessage() . "\n");
}
?>
