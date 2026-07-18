<?php
/**
 * migrate_curriculum.php
 * 
 * Migration script to:
 * 1. Alter course table to support grade_level, price, and paid status.
 * 2. Alter student_course table to support payment_slip.
 * 3. Seed "ICT" and "Mathematics" modules.
 * 4. Seed structured neutral courses from Grade 6 to Advanced Level.
 * 5. Seed default lessons under each course.
 */
header('Content-Type: text/plain');
require_once 'db.php';

try {
    echo "Starting Neutral Curriculum & Payment Slip Schema Migration...\n\n";

    // 1. Alter Course Table
    echo "Step 1: Checking and altering course table...\n";
    
    // Check if grade_level exists
    $stmtCheckGrade = $pdo->query("SHOW COLUMNS FROM course LIKE 'grade_level'");
    if ($stmtCheckGrade->rowCount() == 0) {
        $pdo->exec("ALTER TABLE course ADD COLUMN grade_level VARCHAR(50) DEFAULT 'Grade 11 (O/L)' AFTER module_id");
        echo "Added 'grade_level' column to course table.\n";
    } else {
        echo "'grade_level' column already exists.\n";
    }

    // Check if is_paid exists
    $stmtCheckPaid = $pdo->query("SHOW COLUMNS FROM course LIKE 'is_paid'");
    if ($stmtCheckPaid->rowCount() == 0) {
        $pdo->exec("ALTER TABLE course ADD COLUMN is_paid TINYINT(1) DEFAULT 0 AFTER description");
        echo "Added 'is_paid' column to course table.\n";
    } else {
        echo "'is_paid' column already exists.\n";
    }

    // Check if price exists
    $stmtCheckPrice = $pdo->query("SHOW COLUMNS FROM course LIKE 'price'");
    if ($stmtCheckPrice->rowCount() == 0) {
        $pdo->exec("ALTER TABLE course ADD COLUMN price DECIMAL(10, 2) DEFAULT 0.00 AFTER is_paid");
        echo "Added 'price' column to course table.\n";
    } else {
        echo "'price' column already exists.\n";
    }

    // 2. Alter student_course Table
    echo "\nStep 2: Checking and altering student_course table...\n";
    $stmtCheckSlip = $pdo->query("SHOW COLUMNS FROM student_course LIKE 'payment_slip'");
    if ($stmtCheckSlip->rowCount() == 0) {
        $pdo->exec("ALTER TABLE student_course ADD COLUMN payment_slip VARCHAR(255) DEFAULT NULL AFTER status");
        echo "Added 'payment_slip' column to student_course table.\n";
    } else {
        echo "'payment_slip' column already exists.\n";
    }

    // 3. Create modules ICT and Mathematics
    echo "\nStep 3: Creating ICT and Mathematics modules...\n";
    
    // Rename old "Sri Lankan O/L ICT" if it exists
    $pdo->exec("UPDATE module SET name = 'ICT' WHERE name = 'Sri Lankan O/L ICT'");
    
    $modules = ['ICT', 'Mathematics'];
    $module_ids = [];
    
    foreach ($modules as $mname) {
        $stmtM = $pdo->prepare("SELECT id FROM module WHERE name = :name");
        $stmtM->execute([':name' => $mname]);
        $id = $stmtM->fetchColumn();
        
        if (!$id) {
            $stmtInsert = $pdo->prepare("INSERT INTO module (name) VALUES (:name)");
            $stmtInsert->execute([':name' => $mname]);
            $id = $pdo->lastInsertId();
            echo "Created module '$mname' (ID: $id)\n";
        } else {
            echo "Module '$mname' already exists (ID: $id)\n";
        }
        $module_ids[$mname] = $id;
    }

    // 4. Define courses and lessons
    $ict_id = $module_ids['ICT'];
    $math_id = $module_ids['Mathematics'];

    $courses_data = [
        // --- GRADE 6 ---
        [
            'module_id' => $ict_id,
            'grade_level' => 'Grade 6',
            'title' => 'Introduction to Computer Systems (Grade 6)',
            'description' => 'Start your tech journey here. Learn basic computer parts, input/output devices, software, and how to use operating systems safely.',
            'is_paid' => 0,
            'price' => 0.00,
            'lessons' => [
                [
                    'title' => 'Understanding Hardware & Software',
                    'content' => "A computer has two main components: hardware and software.\n\n- Hardware is anything you can physically touch (keyboard, monitor, CPU).\n- Software is the code/instructions that run on hardware (web browsers, operating systems, games).",
                    'video_url' => 'https://www.youtube.com/embed/UB1O30zR-EE',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $math_id,
            'grade_level' => 'Grade 6',
            'title' => 'Fractions & Decimals (Grade 6)',
            'description' => 'Build a solid math foundation. Understand proper, improper, and mixed fractions, decimals, and basic arithmetic operations.',
            'is_paid' => 0,
            'price' => 0.00,
            'lessons' => [
                [
                    'title' => 'Introduction to Fractions',
                    'content' => "A fraction represents a part of a whole. It consists of a numerator (top number) and a denominator (bottom number).\n\nLearn to identify proper fractions (numerator < denominator) and improper fractions (numerator >= denominator).",
                    'video_url' => 'https://www.youtube.com/embed/jV8B24rSN5o',
                    'order_num' => 1
                ]
            ]
        ],

        // --- GRADE 7 ---
        [
            'module_id' => $ict_id,
            'grade_level' => 'Grade 7',
            'title' => 'Interactive Scratch Programming (Grade 7)',
            'description' => 'Learn programming logic visually! Create games, animations, and stories using Scratch block coding blocks.',
            'is_paid' => 0,
            'price' => 0.00,
            'lessons' => [
                [
                    'title' => 'Getting Started with Scratch Blocks',
                    'content' => "Scratch uses color-coded blocks to guide logic flow. Move blocks like 'move 10 steps', 'say Hello', or repeat loops to animate sprites.",
                    'video_url' => 'https://www.youtube.com/embed/vmEHCJof1kU',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $math_id,
            'grade_level' => 'Grade 7',
            'title' => 'Introduction to Cartesian Plane & Graphs (Grade 7)',
            'description' => 'Discover coordinate systems. Plot points in the 2D space, understand X and Y axes, and draw basic shapes.',
            'is_paid' => 0,
            'price' => 0.00,
            'lessons' => [
                [
                    'title' => 'Plotting Coordinates (x, y)',
                    'content' => "The coordinate plane is divided into four quadrants by vertical Y-axis and horizontal X-axis. Points are represented by pairs (x, y).",
                    'video_url' => 'https://www.youtube.com/embed/IHZwWFHWa-w',
                    'order_num' => 1
                ]
            ]
        ],

        // --- GRADE 8 ---
        [
            'module_id' => $ict_id,
            'grade_level' => 'Grade 8',
            'title' => 'Fundamentals of Logic Gates (Grade 8)',
            'description' => 'Explore the primary logic units: AND, OR, and NOT gates. Learn logic tables and basic logic combinations.',
            'is_paid' => 0,
            'price' => 0.00,
            'lessons' => [
                [
                    'title' => 'Primary Logic Gates & Truth Tables',
                    'content' => "Logic gates process inputs of 1s (true) and 0s (false).\n\n1. **AND gate**: Outputs 1 only if all inputs are 1.\n2. **OR gate**: Outputs 1 if at least one input is 1.\n3. **NOT gate**: Reverses the input.",
                    'video_url' => 'https://www.youtube.com/embed/gI-qXk70SBo',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $math_id,
            'grade_level' => 'Grade 8',
            'title' => 'Introduction to Matrices & Simple Graphs (Grade 8)',
            'description' => 'Learn how matrices display data grids, and plot simple linear functions on coordinate systems.',
            'is_paid' => 0,
            'price' => 0.00,
            'lessons' => [
                [
                    'title' => 'What is a Matrix?',
                    'content' => "A matrix is a rectangular array of numbers arranged in rows and columns. The dimension is written as rows x columns (e.g. 2x3 matrix).",
                    'video_url' => 'https://www.youtube.com/embed/6qAew7C0w00',
                    'order_num' => 1
                ]
            ]
        ],

        // --- GRADE 9 ---
        [
            'module_id' => $ict_id,
            'grade_level' => 'Grade 9',
            'title' => 'Basic Web Development & HTML (Grade 9)',
            'description' => 'Build simple website templates. Learn header, body, paragraphs, fonts, and inline styles in HTML.',
            'is_paid' => 0,
            'price' => 0.00,
            'lessons' => [
                [
                    'title' => 'HTML File Syntax & Setup',
                    'content' => "Every HTML document starts with <!DOCTYPE html> and contains HTML tags wrapped in nested hierarchies.",
                    'video_url' => 'https://www.youtube.com/embed/kUMe1FH4XXY',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $math_id,
            'grade_level' => 'Grade 9',
            'title' => 'Linear Equations, Matrices & Graphs (Grade 9)',
            'description' => 'Draw straight line graphs of style y = mx + c. Add and subtract matching matrices.',
            'is_paid' => 0,
            'price' => 0.00,
            'lessons' => [
                [
                    'title' => 'Linear Function Graphs',
                    'content' => "A linear graph shows a constant rate of change. Plot points using a table of values for x and y, then connect them with a straight line.",
                    'video_url' => 'https://www.youtube.com/embed/CGLQD11-5u8',
                    'order_num' => 1
                ]
            ]
        ],

        // --- GRADE 10 ---
        [
            'module_id' => $ict_id,
            'grade_level' => 'Grade 10',
            'title' => 'Web Programming with HTML5 (Grade 10)',
            'description' => 'Take your HTML further. Build navigation bars, forms, custom grids, and tables.',
            'is_paid' => 1,
            'price' => 1200.00,
            'lessons' => [
                [
                    'title' => 'HTML Tables & Form Layouts',
                    'content' => "Use <table>, <tr>, and <td> to build grid-based tabular reports, and <form> elements to capture input fields.",
                    'video_url' => 'https://www.youtube.com/embed/o70W0f-gV3w',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $ict_id,
            'grade_level' => 'Grade 10',
            'title' => 'Introduction to Algorithms & Flowcharts (Grade 10)',
            'description' => 'Structure problem-solving pathways using flowchart shape blocks.',
            'is_paid' => 1,
            'price' => 1500.00,
            'lessons' => [
                [
                    'title' => 'Flowchart Symbols & Pathing',
                    'content' => "Understand standard ANSI symbols: Terminal, Process, Decision, and Input/Output.",
                    'video_url' => 'https://www.youtube.com/embed/5F_S9k14tL8',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $math_id,
            'grade_level' => 'Grade 10',
            'title' => 'Drawing Algebraic Graphs (Grade 10)',
            'description' => 'Graph quadratic equations of format y = ax^2 + b and locate their vertex points.',
            'is_paid' => 1,
            'price' => 1000.00,
            'lessons' => [
                [
                    'title' => 'Quadratic Plots & Axis of Symmetry',
                    'content' => "Plot graphs of y = x^2 and analyze how parameters stretch or shift the curve.",
                    'video_url' => 'https://www.youtube.com/embed/kqtD5eraMx8',
                    'order_num' => 1
                ]
            ]
        ],

        // --- GRADE 11 (O/L) ---
        [
            'module_id' => $ict_id,
            'grade_level' => 'Grade 11 (O/L)',
            'title' => 'HTML & Web Design (O/L)',
            'description' => 'Master the Ordinary Level HTML syllabus. Design beautiful layouts and pages using semantic HTML elements.',
            'is_paid' => 1,
            'price' => 1500.00,
            'lessons' => [
                [
                    'title' => 'O/L HTML Exam Core Structure',
                    'content' => "We will study semantic structural blocks: <header>, <nav>, <main>, <section>, <article>, and <footer>.",
                    'video_url' => 'https://www.youtube.com/embed/CGLQD11-5u8',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $ict_id,
            'grade_level' => 'Grade 11 (O/L)',
            'title' => 'Algorithms & Flowcharts (O/L)',
            'description' => 'Exhaustive syllabus coverage of algorithms, nested decisions, and loop counters for examinations.',
            'is_paid' => 1,
            'price' => 2000.00,
            'lessons' => [
                [
                    'title' => 'Loop Controls & Iterative Flowcharts',
                    'content' => "Designing flowcharts to display even numbers, sums, and complex branching checks.",
                    'video_url' => 'https://www.youtube.com/embed/5F_S9k14tL8',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $ict_id,
            'grade_level' => 'Grade 11 (O/L)',
            'title' => 'Python Programming (O/L)',
            'description' => 'Complete Python introduction. Learn variables, syntax structures, loop controls, and logical comparison structures.',
            'is_paid' => 1,
            'price' => 2500.00,
            'lessons' => [
                [
                    'title' => 'Variables and Conditional Loops',
                    'content' => "Master input(), conversion variables, if-elif-else statements, and while loops in Python.",
                    'video_url' => 'https://www.youtube.com/embed/8y212m7H6j8',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $ict_id,
            'grade_level' => 'Grade 11 (O/L)',
            'title' => 'Logic Gates (O/L)',
            'description' => 'Complete digital electronics logic gate syllabus: combinational circuits, algebra laws, and logic output evaluation.',
            'is_paid' => 0,
            'price' => 0.00,
            'lessons' => [
                [
                    'title' => 'Combinational Networks & Truth Tables',
                    'content' => "Solving circuits combining AND, OR, NOT, NAND, NOR, and XOR gates. Constructing complete truth tables.",
                    'video_url' => 'https://www.youtube.com/embed/6qAew7C0w00',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $math_id,
            'grade_level' => 'Grade 11 (O/L)',
            'title' => 'Matrices & Determinants (O/L)',
            'description' => 'Complete matrix algebra: multiplication of matrices, finding determinants, and matrix inverses.',
            'is_paid' => 1,
            'price' => 1500.00,
            'lessons' => [
                [
                    'title' => 'Matrix Multiplication (2x2)',
                    'content' => "Multiply matrices of dimension 2x2. Rows of the first matrix multiplied by columns of the second matrix.",
                    'video_url' => 'https://www.youtube.com/embed/vmEHCJof1kU',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $math_id,
            'grade_level' => 'Grade 11 (O/L)',
            'title' => 'Quadratic Graphs & Solutions (O/L)',
            'description' => 'Learn how to sketch quadratic functions y = ax^2 + bx + c, extract local extrema, and find real roots.',
            'is_paid' => 1,
            'price' => 1500.00,
            'lessons' => [
                [
                    'title' => 'Plotting y = ax^2 + bx + c',
                    'content' => "Draw smooth parabolic curves. Find turning points, max/min values, and x-intercept roots.",
                    'video_url' => 'https://www.youtube.com/embed/IHZwWFHWa-w',
                    'order_num' => 1
                ]
            ]
        ],

        // --- ADVANCED LEVEL ---
        [
            'module_id' => $ict_id,
            'grade_level' => 'Advanced Level (A/L)',
            'title' => 'Digital Systems & Logic Gates (A/L)',
            'description' => 'Advanced digital electronics logic. Learn Karnaugh maps, half/full adders, multiplexers, and flip-flops.',
            'is_paid' => 1,
            'price' => 3000.00,
            'lessons' => [
                [
                    'title' => 'Boolean Simplification & Karnaugh Maps',
                    'content' => "Minimize boolean algebra expressions using Karnaugh maps (2, 3, and 4 variables) to design optimized circuits.",
                    'video_url' => 'https://www.youtube.com/embed/6qAew7C0w00',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $ict_id,
            'grade_level' => 'Advanced Level (A/L)',
            'title' => 'Advanced Python Programming (A/L)',
            'description' => 'Advanced Python: file handling, error safety, database modules, and functional algorithms.',
            'is_paid' => 1,
            'price' => 3500.00,
            'lessons' => [
                [
                    'title' => 'File Systems, JSON & CSV in Python',
                    'content' => "Learn to read and write local files, serialize structure data with JSON packages, and parse spreadsheet records.",
                    'video_url' => 'https://www.youtube.com/embed/8y212m7H6j8',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $math_id,
            'grade_level' => 'Advanced Level (A/L)',
            'title' => 'Advanced Matrices & Determinants (A/L)',
            'description' => 'Explore matrix spaces, systems of linear equations using Cramer\'s rule, and eigenvalues.',
            'is_paid' => 1,
            'price' => 2500.00,
            'lessons' => [
                [
                    'title' => 'Solving Systems of Linear Equations',
                    'content' => "Use determinants (Cramer's Rule) and inverse matrices to solve simultaneous linear equation networks in three variables.",
                    'video_url' => 'https://www.youtube.com/embed/vmEHCJof1kU',
                    'order_num' => 1
                ]
            ]
        ],
        [
            'module_id' => $math_id,
            'grade_level' => 'Advanced Level (A/L)',
            'title' => 'Graphs & Curve Sketching (A/L)',
            'description' => 'Sketch functions using calculus methods: finding gradients, inflection points, asymptotes, and extrema.',
            'is_paid' => 1,
            'price' => 2000.00,
            'lessons' => [
                [
                    'title' => 'Asymptotes and Rational Graphs',
                    'content' => "Locate horizontal, vertical, and oblique asymptotes. Determine function boundaries and draw complete curves.",
                    'video_url' => 'https://www.youtube.com/embed/IHZwWFHWa-w',
                    'order_num' => 1
                ]
            ]
        ]
    ];

    echo "\nStep 4: Seeding Courses & Lessons...\n";
    foreach ($courses_data as $cdata) {
        // Check if course already exists
        $stmtCourseCheck = $pdo->prepare("SELECT id FROM course WHERE title = :title");
        $stmtCourseCheck->execute([':title' => $cdata['title']]);
        $course_id = $stmtCourseCheck->fetchColumn();

        if ($course_id) {
            // Update
            $stmtUpdate = $pdo->prepare("
                UPDATE course 
                SET description = :desc, is_paid = :is_paid, price = :price, module_id = :mid, grade_level = :grade 
                WHERE id = :cid
            ");
            $stmtUpdate->execute([
                ':desc' => $cdata['description'],
                ':is_paid' => $cdata['is_paid'],
                ':price' => $cdata['price'],
                ':mid' => $cdata['module_id'],
                ':grade' => $cdata['grade_level'],
                ':cid' => $course_id
            ]);
            echo "Updated existing course '{$cdata['title']}' (ID: $course_id)\n";
        } else {
            // Insert
            $stmtInsert = $pdo->prepare("
                INSERT INTO course (module_id, grade_level, title, description, is_paid, price) 
                VALUES (:mid, :grade, :title, :desc, :is_paid, :price)
            ");
            $stmtInsert->execute([
                ':mid' => $cdata['module_id'],
                ':grade' => $cdata['grade_level'],
                ':title' => $cdata['title'],
                ':desc' => $cdata['description'],
                ':is_paid' => $cdata['is_paid'],
                ':price' => $cdata['price']
            ]);
            $course_id = $pdo->lastInsertId();
            echo "Created new course '{$cdata['title']}' (ID: $course_id)\n";
        }

        // Seed Lessons
        foreach ($cdata['lessons'] as $ldata) {
            $stmtLessCheck = $pdo->prepare("SELECT id FROM lesson WHERE course_id = :cid AND title = :title");
            $stmtLessCheck->execute([':cid' => $course_id, ':title' => $ldata['title']]);
            $lesson_id = $stmtLessCheck->fetchColumn();

            if ($lesson_id) {
                $stmtLessUpdate = $pdo->prepare("
                    UPDATE lesson 
                    SET content = :content, video_url = :video, order_num = :order 
                    WHERE id = :lid
                ");
                $stmtLessUpdate->execute([
                    ':content' => $ldata['content'],
                    ':video' => $ldata['video_url'],
                    ':order' => $ldata['order_num'],
                    ':lid' => $lesson_id
                ]);
                echo "  - Updated lesson '{$ldata['title']}' (ID: $lesson_id)\n";
            } else {
                $stmtLessInsert = $pdo->prepare("
                    INSERT INTO lesson (course_id, title, content, video_url, order_num) 
                    VALUES (:cid, :title, :content, :video, :order)
                ");
                $stmtLessInsert->execute([
                    ':cid' => $course_id,
                    ':title' => $ldata['title'],
                    ':content' => $ldata['content'],
                    ':video' => $ldata['video_url'],
                    ':order' => $ldata['order_num']
                ]);
                $lesson_id = $pdo->lastInsertId();
                echo "  - Created lesson '{$ldata['title']}' (ID: $lesson_id)\n";
            }
        }
    }

    echo "\nCurriculum and payment configuration updated successfully!\n";

} catch (PDOException $e) {
    die("\nMigration failed with error: " . $e->getMessage() . "\n");
}
?>
