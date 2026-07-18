<?php
/**
 * migrate_lms.php
 * 
 * Migration script to create LMS tables (course, lesson, student_course, lesson_completion)
 * and seed initial course and lesson content.
 */
header('Content-Type: text/plain');
require_once 'db.php';

try {
    echo "Starting LMS Database Migration...\n\n";

    // 1. Create course table
    echo "Step 1: Creating course table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS course (
            id INT AUTO_INCREMENT PRIMARY KEY,
            module_id INT NOT NULL,
            teacher_id VARCHAR(50) DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (module_id) REFERENCES module(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES teacher(teacherid) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Add teacher_id column dynamically if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE course ADD COLUMN teacher_id VARCHAR(50) DEFAULT NULL AFTER module_id");
        $pdo->exec("ALTER TABLE course ADD CONSTRAINT fk_course_teacher FOREIGN KEY (teacher_id) REFERENCES teacher(teacherid) ON DELETE SET NULL");
        echo "Dynamically added teacher_id column to course table.\n";
    } catch (PDOException $e) {
        // Suppress if already exists
    }
    echo "Course table is ready.\n\n";

    // 2. Create lesson table
    echo "Step 2: Creating lesson table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lesson (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT DEFAULT NULL,
            video_url VARCHAR(255) DEFAULT NULL,
            attachment VARCHAR(255) DEFAULT NULL,
            order_num INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Lesson table is ready.\n\n";

    // 3. Create student_course (enrollment) table
    echo "Step 3: Creating student_course (enrollment) table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_course (
            student_id VARCHAR(50) NOT NULL,
            course_id INT NOT NULL,
            status VARCHAR(20) DEFAULT 'approved',
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (student_id, course_id),
            FOREIGN KEY (student_id) REFERENCES student(studentid) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Add status column dynamically if it doesn't exist
    try {
        $pdo->exec("ALTER TABLE student_course ADD COLUMN status VARCHAR(20) DEFAULT 'approved' AFTER course_id");
        echo "Dynamically added status column to student_course table.\n";
    } catch (PDOException $e) {
        // Suppress if already exists
    }
    echo "Student course enrollment table is ready.\n\n";

    // 4. Create lesson_completion table
    echo "Step 4: Creating lesson_completion table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lesson_completion (
            student_id VARCHAR(50) NOT NULL,
            lesson_id INT NOT NULL,
            completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (student_id, lesson_id),
            FOREIGN KEY (student_id) REFERENCES student(studentid) ON DELETE CASCADE,
            FOREIGN KEY (lesson_id) REFERENCES lesson(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Lesson completion table is ready.\n\n";

    // 5. Seed default courses (removed non-relevant courses)
    echo "Step 5: Seeding default courses skipped (curriculum is managed in migrate_curriculum.php).\n";

    echo "\nLMS Database Migration completed successfully!\n";

} catch (PDOException $e) {
    die("\nLMS Migration failed with error: " . $e->getMessage() . "\n");
}
?>
