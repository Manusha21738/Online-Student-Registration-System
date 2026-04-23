<?php
session_start();
$formData = $_SESSION['form_data'] ?? [];
$fullname = htmlspecialchars($formData['fullname'] ?? '');
$studentid = htmlspecialchars($formData['studentid'] ?? '');
$programid = htmlspecialchars($formData['programid'] ?? '');
$email = htmlspecialchars($formData['email'] ?? '');
$yearofregister = htmlspecialchars($formData['yearofregister'] ?? date('Y'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Student Registration</title>
    <!-- Use Google Fonts for modern typography -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        /* CSS Variables for consistent theming */
        :root {
            --primary-color: #6a11cb;
            --secondary-color: #2575fc;
            --bg-color: #0f172a;
            --text-color: #f8fafc;
            --glass-bg: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --error-color: #ef4444;
            --success-color: #10b981;
        }

        /* Basic reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        /* Styling for the body with a dynamic dark mode aesthetic */
        body {
            background: var(--bg-color);
            background-image: radial-gradient(circle at top right, #2575fc 0%, transparent 40%),
                              radial-gradient(circle at bottom left, #6a11cb 0%, transparent 40%);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 500px;
            padding: 2rem;
            position: relative;
            z-index: 10;
        }

        /* Glassmorphism panel for the form */
        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 600;
            background: linear-gradient(to right, #a855f7, #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            color: #cbd5e1;
            font-weight: 400;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            color: var(--text-color);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(37, 117, 252, 0.2);
            background: rgba(15, 23, 42, 0.8);
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            background: linear-gradient(to right, var(--primary-color), var(--secondary-color));
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(37, 117, 252, 0.5);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .message.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--error-color);
        }

        .message.success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--success-color);
        }
        
        /* Floating animations for background */
        .blob {
            position: absolute;
            filter: blur(80px);
            z-index: 1;
            opacity: 0.6;
        }
        .blob-1 {
            width: 300px;
            height: 300px;
            background: var(--primary-color);
            border-radius: 50%;
            top: 10%;
            left: 20%;
            animation: float 10s infinite ease-in-out alternate;
        }
        .blob-2 {
            width: 250px;
            height: 250px;
            background: var(--secondary-color);
            border-radius: 50%;
            bottom: 10%;
            right: 20%;
            animation: float 8s infinite ease-in-out alternate-reverse;
        }
        
        @keyframes float {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
    </style>
</head>
<body>

    <!-- Decorative background blobs -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="container">
        <div class="glass-panel">
            <div class="header">
                <h1>Student Portal</h1>
                <p>Register for your academic journey</p>
            </div>

            <?php
            // Display error or success messages from URL parameters
            if (isset($_GET['error'])) {
                echo '<div class="message error">' . htmlspecialchars($_GET['error']) . '</div>';
            }
            if (isset($_GET['success'])) {
                echo '<div class="message success">' . htmlspecialchars($_GET['success']) . '</div>';
                echo '<a href="index.php" class="btn-submit" style="display: block; text-align: center; text-decoration: none; margin-top: 1.5rem; box-sizing: border-box;">Register Another Student</a>';
            } else {
            ?>

            <!-- Registration Form -->
            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname" class="form-control" required placeholder="e.g. John Doe" value="<?php echo $fullname; ?>">
                </div>

                <div class="form-group">
                    <label for="studentid">Student ID</label>
                    <input type="text" id="studentid" name="studentid" class="form-control" required placeholder="e.g. S1234567" value="<?php echo $studentid; ?>">
                </div>

                <div class="form-group">
                    <label for="programid">Program ID</label>
                    <input type="text" id="programid" name="programid" class="form-control" required placeholder="e.g. CS101" value="<?php echo $programid; ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required placeholder="john@example.com" value="<?php echo $email; ?>">
                </div>

                <div class="form-group">
                    <label for="yearofregister">Year of Registration</label>
                    <input type="number" id="yearofregister" name="yearofregister" class="form-control" required min="2000" max="2100" value="<?php echo $yearofregister; ?>">
                </div>

                <button type="submit" class="btn-submit">Complete Registration</button>
            </form>
            <?php } ?>
        </div>
    </div>

</body>
</html>
