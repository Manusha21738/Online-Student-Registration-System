<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userid = $_SESSION['user_id'];
$course_id = (int)($_GET['course_id'] ?? 0);

if ($course_id <= 0) {
    die("Invalid Course ID.");
}

// 1. Fetch student details
$stmtStudent = $pdo->prepare("SELECT * FROM student WHERE studentid = :sid");
$stmtStudent->execute([':sid' => $userid]);
$student = $stmtStudent->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student record not found.");
}

// 2. Verify course enrollment and fetch course details
$stmtCourse = $pdo->prepare("
    SELECT c.*, m.name as module_name, t.fullname as teacher_name 
    FROM course c
    JOIN student_course sc ON c.id = sc.course_id
    JOIN module m ON c.module_id = m.id
    LEFT JOIN teacher t ON c.teacher_id = t.teacherid
    WHERE sc.student_id = :sid AND c.id = :cid
");
$stmtCourse->execute([':sid' => $userid, ':cid' => $course_id]);
$course = $stmtCourse->fetch(PDO::FETCH_ASSOC);

if (!$course) {
    die("You are not enrolled in this course.");
}

// 3. Verify course completion
// Total lessons
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM lesson WHERE course_id = :cid");
$stmtTotal->execute([':cid' => $course_id]);
$total_lessons = $stmtTotal->fetchColumn();

// Completed lessons
$stmtCompleted = $pdo->prepare("
    SELECT COUNT(*) FROM lesson_completion lc
    JOIN lesson l ON lc.lesson_id = l.id
    WHERE lc.student_id = :sid AND l.course_id = :cid
");
$stmtCompleted->execute([':sid' => $userid, ':cid' => $course_id]);
$completed_lessons = $stmtCompleted->fetchColumn();

if ($total_lessons == 0 || $completed_lessons < $total_lessons) {
    die("Course not completed yet. Progress: " . ($total_lessons > 0 ? round(($completed_lessons / $total_lessons) * 100) : 0) . "%");
}

// 4. Fetch last completion date for certificate issue date
$stmtDate = $pdo->prepare("
    SELECT MAX(lc.completed_at) 
    FROM lesson_completion lc
    JOIN lesson l ON lc.lesson_id = l.id
    WHERE lc.student_id = :sid AND l.course_id = :cid
");
$stmtDate->execute([':sid' => $userid, ':cid' => $course_id]);
$completion_timestamp = $stmtDate->fetchColumn();
$issue_date = $completion_timestamp ? date('F d, Y', strtotime($completion_timestamp)) : date('F d, Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - <?php echo htmlspecialchars($course['title']); ?></title>
    <!-- Fonts for elegant certificate -->
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700;800&family=Outfit:wght@400;500;600&family=Playfair+Display:ital,wght@1,500;1,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0f172a;
            --cert-bg: #fafaf9;
            --border-gold: #c5a880;
            --text-dark: #1c1917;
            --text-gold: #854d0e;
        }

        /* Dark mode for page wrapper on screen */
        body {
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            font-family: 'Outfit', sans-serif;
            color: #f1f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }

        .actions-bar {
            width: 100%;
            max-width: 900px;
            margin: 20px auto 10px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            box-sizing: border-box;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-outline {
            border: 1px solid #475569;
            background: transparent;
            color: #f1f5f9;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.05);
            border-color: #94a3b8;
        }

        .btn-primary {
            background: #10b981;
            border: 1px solid #10b981;
            color: white;
        }

        .btn-primary:hover {
            background: #059669;
            border-color: #059669;
        }

        /* Certificate Wrapper styling */
        .certificate-wrapper {
            margin: 20px auto 40px auto;
            padding: 20px;
            background: #1e293b;
            border-radius: 12px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
        }

        .certificate-container {
            width: 840px;
            height: 590px;
            background-color: var(--cert-bg);
            background-image: radial-gradient(circle, rgba(255, 255, 255, 0.8) 0%, rgba(245, 245, 244, 0.9) 100%);
            color: var(--text-dark);
            box-sizing: border-box;
            border: 20px solid var(--border-gold);
            outline: 3px solid #78350f;
            outline-offset: -12px;
            padding: 50px 60px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            position: relative;
            text-align: center;
            box-shadow: inset 0 0 100px rgba(133, 77, 14, 0.05);
        }

        /* Corner accents for the certificate */
        .corner-accent {
            position: absolute;
            width: 30px;
            height: 30px;
            border: 2px solid var(--text-gold);
        }
        .top-left { top: 12px; left: 12px; border-right: none; border-bottom: none; }
        .top-right { top: 12px; right: 12px; border-left: none; border-bottom: none; }
        .bottom-left { bottom: 12px; left: 12px; border-right: none; border-top: none; }
        .bottom-right { bottom: 12px; right: 12px; border-left: none; border-top: none; }

        .cert-header {
            font-family: 'Cinzel', serif;
            font-size: 20px;
            letter-spacing: 4px;
            color: var(--text-gold);
            font-weight: 700;
            margin-top: 10px;
        }

        .cert-title {
            font-family: 'Cinzel', serif;
            font-size: 40px;
            font-weight: 800;
            letter-spacing: 2px;
            margin: 10px 0;
            background: linear-gradient(135deg, #1c1917 0%, #78350f 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
        }

        .cert-subtitle {
            font-size: 15px;
            color: #57534e;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 25px;
            position: relative;
        }

        .cert-subtitle::after {
            content: '';
            display: block;
            width: 60px;
            height: 1px;
            background: var(--border-gold);
            margin: 10px auto 0 auto;
        }

        .student-name {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            font-weight: 700;
            font-style: italic;
            color: #0f172a;
            margin: 15px 0 5px 0;
            border-bottom: 2px solid #e7e5e4;
            padding-bottom: 5px;
            min-width: 320px;
        }

        .cert-body {
            font-size: 14.5px;
            line-height: 1.6;
            color: #44403c;
            max-width: 600px;
            margin: 10px auto 30px auto;
        }

        .course-title {
            font-weight: 700;
            color: #0f172a;
            font-size: 17px;
        }

        .cert-footer {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 10px;
            padding: 0 20px;
            box-sizing: border-box;
        }

        .footer-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 200px;
        }

        .signature-line {
            width: 100%;
            border-top: 1px solid #78716c;
            margin-bottom: 8px;
        }

        .signature-title {
            font-size: 12px;
            color: #78716c;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .signature-name {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            font-weight: 600;
            font-style: italic;
            color: #1c1917;
            margin-bottom: 2px;
        }

        .gold-seal {
            width: 70px;
            height: 70px;
            background: radial-gradient(circle, #facc15 0%, #ca8a04 100%);
            border-radius: 50%;
            border: 3px double #fef08a;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transform: rotate(-10deg);
        }

        .seal-ribbon {
            position: absolute;
            width: 18px;
            height: 45px;
            background: #b45309;
            top: 50px;
            box-shadow: 1px 1px 3px rgba(0,0,0,0.2);
            z-index: -1;
        }
        .ribbon-left { left: 16px; transform: rotate(15deg); }
        .ribbon-right { right: 16px; transform: rotate(-15deg); }

        .seal-star {
            color: white;
            font-size: 28px;
            line-height: 1;
        }

        /* CSS for printing */
        @media print {
            body {
                background-color: white;
                color: black;
                margin: 0;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .certificate-wrapper {
                margin: 0;
                padding: 0;
                background: transparent;
                box-shadow: none;
                border-radius: 0;
            }

            .certificate-container {
                width: 100%;
                max-width: 100%;
                height: 100vh;
                border: 20px solid var(--border-gold);
                outline: 3px solid #78350f;
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Top Actions bar -->
    <div class="actions-bar no-print">
        <a href="profile.php?tab=certificates" class="btn btn-outline">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Back to Dashboard
        </a>
        <button onclick="window.print();" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print / Save PDF
        </button>
    </div>

    <!-- Certificate Render Area -->
    <div class="certificate-wrapper">
        <div class="certificate-container">
            <!-- Corner borders -->
            <div class="corner-accent top-left"></div>
            <div class="corner-accent top-right"></div>
            <div class="corner-accent bottom-left"></div>
            <div class="corner-accent bottom-right"></div>

            <div class="cert-header">VitsEDU ACADEMY</div>
            
            <div style="display: flex; flex-direction: column; align-items: center;">
                <div class="cert-title">Certificate of Completion</div>
                <div class="cert-subtitle">This is proudly presented to</div>
                <div class="student-name"><?php echo htmlspecialchars($student['fullname']); ?></div>
            </div>

            <div class="cert-body">
                for successfully meeting all academic requirements and outstandingly completing the curriculum of the professional course of study in
                <div class="course-title" style="margin-top: 8px; font-size: 19px; color: var(--text-gold);"><?php echo htmlspecialchars($course['title']); ?></div>
                <div style="font-size: 12px; color: #78716c; margin-top: 5px;">Module under: <?php echo htmlspecialchars($course['module_name']); ?></div>
            </div>

            <div class="cert-footer">
                <div class="footer-item">
                    <div class="signature-name"><?php echo htmlspecialchars($course['teacher_name'] ?? 'VitsEDU Faculty'); ?></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">Instructor</div>
                </div>

                <div class="gold-seal">
                    <div class="seal-ribbon ribbon-left"></div>
                    <div class="seal-ribbon ribbon-right"></div>
                    <span class="seal-star">★</span>
                </div>

                <div class="footer-item">
                    <div class="signature-name" style="font-family: 'Outfit', sans-serif; font-size: 14.5px; font-style: normal; font-weight: 500;"><?php echo $issue_date; ?></div>
                    <div class="signature-line"></div>
                    <div class="signature-title">Date Issued</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
