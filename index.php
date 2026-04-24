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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="bg-mesh"></div>

    <div class="container">
        <div class="glass-panel">
            <div class="header">
                <h1>Student <span>Portal</span></h1>
                <p>Register for your academic journey</p>
            </div>

            <?php
            // Display error or success messages from URL parameters
            if (isset($_GET['error'])) {
                echo '<div class="message error">' . htmlspecialchars($_GET['error']) . '</div>';
            }
            if (isset($_GET['success'])) {
                echo '<div class="message success">' . htmlspecialchars($_GET['success']) . '</div>';
                echo '<a href="index.php" class="btn-secondary">Register Another Student</a>';
            } else {
            ?>

            <!-- Registration Form with Floating Labels -->
            <form action="register.php" method="POST">
                <div class="form-group">
                    <input type="text" id="fullname" name="fullname" class="form-control" required placeholder=" " value="<?php echo $fullname; ?>">
                    <label for="fullname">Full Name</label>
                </div>

                <div class="form-group">
                    <input type="text" id="studentid" name="studentid" class="form-control" required placeholder=" " value="<?php echo $studentid; ?>">
                    <label for="studentid">Student ID</label>
                </div>

                <div class="form-group">
                    <input type="text" id="programid" name="programid" class="form-control" required placeholder=" " value="<?php echo $programid; ?>">
                    <label for="programid">Program ID</label>
                </div>

                <div class="form-group">
                    <input type="email" id="email" name="email" class="form-control" required placeholder=" " value="<?php echo $email; ?>">
                    <label for="email">Email Address</label>
                </div>

                <div class="form-group">
                    <input type="number" id="yearofregister" name="yearofregister" class="form-control" required min="2000" max="2100" placeholder=" " value="<?php echo $yearofregister; ?>">
                    <label for="yearofregister">Year of Registration</label>
                </div>

                <button type="submit" class="btn-submit">Complete Registration</button>
            </form>
            <?php } ?>
        </div>
    </div>

    <script>
        // Show loading spinner when form is submitted
        document.querySelector('form')?.addEventListener('submit', function() {
            const btn = document.querySelector('.btn-submit');
            btn.innerHTML = 'Sending Verification Email... <span class="spinner"></span>';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.85';
            btn.style.transform = 'scale(0.98)';
        });
    </script>
</body>
</html>
