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
            enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (student_id, course_id),
            FOREIGN KEY (student_id) REFERENCES student(studentid) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES course(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
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

    // 5. Seed default courses
    echo "Step 5: Seeding default courses...\n";
    
    // Get module IDs
    $stmtMod = $pdo->query("SELECT id, name FROM module");
    $modules = $stmtMod->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Find matching modules
    $webDevId = array_search('Web Development', $modules);
    $dataScienceId = array_search('Data Science', $modules);
    $aiId = array_search('Artificial Intelligence', $modules);

    if ($webDevId) {
        $stmtCourse = $pdo->prepare("INSERT INTO course (module_id, title, description) VALUES (:mid, :title, :desc)");
        
        // Course 1
        $stmtCourse->execute([
            ':mid' => $webDevId,
            ':title' => 'Modern HTML5 & CSS3 Masterclass',
            ':desc' => 'Learn the foundation of web design. Master Semantic HTML5, CSS Grid, Flexbox, transitions, and dark-mode designs from scratch.'
        ]);
        $c1Id = $pdo->lastInsertId();
        
        $stmtLesson = $pdo->prepare("INSERT INTO lesson (course_id, title, content, video_url, order_num) VALUES (:cid, :title, :content, :video, :order)");
        $stmtLesson->execute([
            ':cid' => $c1Id,
            ':title' => 'Introduction to HTML5 and Semantic Elements',
            ':content' => 'HTML5 semantic elements provide structural meaning to your web layout. Elements like header, nav, main, section, article, and footer are crucial for SEO and accessibility.',
            ':video' => 'https://www.youtube.com/embed/UB1O30zR-EE',
            ':order' => 1
        ]);
        
        $stmtLesson->execute([
            ':cid' => $c1Id,
            ':title' => 'CSS Grid & Flexbox Layouts',
            ':content' => 'Understand the core concepts of CSS Flexbox and Grid. Flexbox is designed for one-dimensional layouts (row or column), whereas Grid is designed for two-dimensional layouts.',
            ':video' => 'https://www.youtube.com/embed/jV8B24rSN5o',
            ':order' => 2
        ]);
        
        // Course 2
        $stmtCourse->execute([
            ':mid' => $webDevId,
            ':title' => 'Full-Stack JavaScript Developer Bootcamp',
            ':desc' => 'Go from beginner to advanced JavaScript developer. Learn modern ES6+, Node.js, Express, databases, and dynamic single-page web applications.'
        ]);
        $c2Id = $pdo->lastInsertId();
        $stmtLesson->execute([
            ':cid' => $c2Id,
            ':title' => 'JavaScript ES6+ Syntax & Core Concepts',
            ':content' => 'Learn Arrow Functions, Destructuring, Spread/Rest operators, Template Literals, Promises, and Async/Await in modern JavaScript.',
            ':video' => 'https://www.youtube.com/embed/W6NZfCO5SIk',
            ':order' => 1
        ]);
    }

    if ($dataScienceId) {
        $stmtCourse = $pdo->prepare("INSERT INTO course (module_id, title, description) VALUES (:mid, :title, :desc)");
        $stmtCourse->execute([
            ':mid' => $dataScienceId,
            ':title' => 'Python for Data Analysis and Visualization',
            ':desc' => 'Understand numpy arrays, pandas dataframes, matplolib plotting, and data cleaning pipelines to drive data insights.'
        ]);
        $c3Id = $pdo->lastInsertId();
        
        $stmtLesson = $pdo->prepare("INSERT INTO lesson (course_id, title, content, video_url, order_num) VALUES (:cid, :title, :content, :video, :order)");
        $stmtLesson->execute([
            ':cid' => $c3Id,
            ':title' => 'Getting Started with Pandas DataFrames',
            ':content' => 'Pandas is the leading data analysis library in Python. Learn how to import CSV files, filter rows, handle missing values, and calculate summary statistics.',
            ':video' => 'https://www.youtube.com/embed/vmEHCJof1kU',
            ':order' => 1
        ]);
    }

    if ($aiId) {
        $stmtCourse = $pdo->prepare("INSERT INTO course (module_id, title, description) VALUES (:mid, :title, :desc)");
        $stmtCourse->execute([
            ':mid' => $aiId,
            ':title' => 'Introduction to Neural Networks and Deep Learning',
            ':desc' => 'Dive into artificial intelligence! Build a neural network from the ground up, understand perceptrons, forward propagation, and backpropagation.'
        ]);
        $c4Id = $pdo->lastInsertId();
        
        $stmtLesson = $pdo->prepare("INSERT INTO lesson (course_id, title, content, video_url, order_num) VALUES (:cid, :title, :content, :video, :order)");
        $stmtLesson->execute([
            ':cid' => $c4Id,
            ':title' => 'Perceptrons and Gradient Descent',
            ':content' => 'A perceptron is the basic unit of a neural network. Gradient Descent is the mathematical optimization technique used to minimize the cost function by adjusting weights.',
            ':video' => 'https://www.youtube.com/embed/IHZwWFHWa-w',
            ':order' => 1
        ]);
    }

    // 6. Enroll existing students in at least one course so they have something in "My Learning"
    echo "Step 6: Enrolling existing students in default courses...\n";
    $students = $pdo->query("SELECT studentid FROM student")->fetchAll(PDO::FETCH_COLUMN);
    $courses = $pdo->query("SELECT id FROM course")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($students) && !empty($courses)) {
        $stmtEnroll = $pdo->prepare("INSERT INTO student_course (student_id, course_id) VALUES (:sid, :cid) ON DUPLICATE KEY UPDATE student_id=student_id");
        foreach ($students as $sid) {
            // Enroll every student in the first course
            $stmtEnroll->execute([':sid' => $sid, ':cid' => $courses[0]]);
            echo "Enrolled student $sid in course ID $courses[0]\n";
        }
    }

    echo "\nLMS Database Migration completed successfully!\n";

} catch (PDOException $e) {
    die("\nLMS Migration failed with error: " . $e->getMessage() . "\n");
}
?>
