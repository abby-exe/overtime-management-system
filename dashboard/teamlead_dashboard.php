<?php
session_start();
ob_start(); // Start output buffering

// Ensure the user is logged in and has a Team Lead role
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'Team Lead') {
    header('Location: ../teamlead_login.php'); // Redirect to login if not logged in as a Team Lead
    exit();
}

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ottest";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch the logged-in team lead's details using a prepared statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT id, name, ic_number, email, department FROM users WHERE email = :email AND role = 'Team Lead'");
    $stmt->bindParam(':email', $_SESSION['email'], PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $teamLeadId = $user['id'];
        $teamLeadName = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
        $teamLeadIC = htmlspecialchars($user['ic_number'], ENT_QUOTES, 'UTF-8');
        $teamLeadEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
        $teamLeadDepartment = htmlspecialchars($user['department'], ENT_QUOTES, 'UTF-8');
    } else {
        echo "<p>Error: User details not found.</p>";
        exit();
    }

} catch(PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    exit();
}

// Handle OT approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_ot_status'])) {
    $otId = $_POST['ot_id'];
    $status = $_POST['status'];
    $reason = $_POST['status'] === 'Rejected' ? $_POST['rejection_reason'] : '';

    $updateStmt = $conn->prepare("UPDATE ot_submissions SET status = :status, rejection_reason = :reason, approved_by = :approved_by WHERE id = :id");
    $updateStmt->bindParam(':status', $status);
    $updateStmt->bindParam(':reason', $reason);
    $updateStmt->bindParam(':approved_by', $teamLeadId, PDO::PARAM_INT);
    $updateStmt->bindParam(':id', $otId, PDO::PARAM_INT);
    $updateStmt->execute();

    // Redirect to avoid form resubmission issues
    header('Location: teamlead_dashboard.php');
    exit();
}

// Add date filter
$selectedDate = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';

// Fetch OT submissions for the team lead's department
$otStmt = $conn->prepare("SELECT ot.*, u.name FROM ot_submissions ot 
                          JOIN users u ON ot.user_id = u.id 
                          WHERE u.department = :department AND ot.status = 'Pending'" . ($selectedDate ? " AND ot.date = :date" : ""));
$otStmt->bindParam(':department', $teamLeadDepartment, PDO::PARAM_STR);
if ($selectedDate) {
    $otStmt->bindParam(':date', $selectedDate);
}
$otStmt->execute();
$otSubmissions = $otStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="includes/images/favicon.ico">
    <title>Team Lead Dashboard</title>
    <link rel="stylesheet" href="includes/styles.css">
    <style>
        body {
            background: url('includes/images/dashbg.jpg') no-repeat center center fixed; /* Inline background image */
            background-size: cover; /* Ensure it fits all screen sizes */
            color: #003b5c;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            text-align: center;
            overflow-x: hidden;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7); /* White overlay with reduced opacity */
            z-index: -1; /* Ensure it is behind all content */
        }

        /* OT Submissions Table */
        .ot-submissions-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
            color: #003b5c;
        }

        .ot-submissions-table th, .ot-submissions-table td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }

        .ot-submissions-table th {
            background-color: #29ABE2; /* Header with your theme color */
            color: #ffffff; /* White text */
        }

        .ot-submissions-table tr:nth-child(even) {
            background-color: #f2f2f2; /* Light grey for even rows */
        }

        .ot-submissions-table tr:hover {
            background-color: #d3eaf2; /* Light blue on hover */
        }

        /* Centering the actions in the table cell */
        .action-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .action-buttons form {
            margin: 0 5px; /* Add margin between forms */
        }

        .action-buttons input[type="text"] {
            margin: 0 5px; /* Add margin between the rejection reason input and buttons */
            flex-grow: 1;
        }

        .approve-btn, .reject-btn {
            padding: 5px 10px;
            border-radius: 5px;
            border: none;
            color: white;
            cursor: pointer;
        }

        .approve-btn {
            background-color: #4CAF50; /* Green */
        }

        .reject-btn {
            background-color: #f44336; /* Red */
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }

            .action-buttons form {
                margin: 5px 0;
            }

            .approve-btn, .reject-btn {
                width: 100%; /* Full width on smaller screens */
                margin-bottom: 5px; /* Margin between buttons on small screens */
            }

            .action-buttons input[type="text"] {
                width: 100%; /* Full width on smaller screens */
                margin: 5px 0; /* Margin on smaller screens */
            }
        }

        /* Style for the date filter input */
        .date-filter {
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-start; /* Align to the left */
            padding-left: 1px; /* Optional: Add padding if you want to align it further to the left */
        }

        .date-filter input[type="date"] {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .date-filter button {
            background-color: #29ABE2;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
        }

        .date-filter button:hover {
            background-color: #1d89b2;
        }

        .employee-info-slider.active {
            left: 0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <button class="hamburger-button" onclick="openSlider()">&#9776;</button> <!-- Hamburger button -->
        <img src="includes/images/logo.png" alt="Company Logo" class="logo"> <!-- Add your logo image here -->
        <h1>Overtime Management System</h1>
    </header>

    <!-- Team Lead Information Slider -->
    <div id="employeeInfoSlider" class="employee-info-slider">
        <button class="back-button" onclick="closeSlider()">&#8678;</button> <!-- Back arrow to close the slider -->
        <div class="employee-info-content">
            <h2>Team Lead's Dashboard</h2>
            <p><strong>Name:</strong> <?php echo $teamLeadName; ?></p>
            <p><strong>IC Number:</strong> <?php echo $teamLeadIC; ?></p>
            <p><strong>Email:</strong> <?php echo $teamLeadEmail; ?></p>
            <p><strong>Department:</strong> <?php echo $teamLeadDepartment; ?></p>
            <button class="logout-button" onclick="logout()">Logout</button> <!-- Logout button -->
        </div>
    </div>

    <!-- Main Content -->
    <main class="dashboard-main">
        <h2>Welcome, <?php echo $teamLeadName; ?></h2>
        <p>Here you can view your team's work hours, accept or reject their requests.</p>

        <!-- Date Filter -->
    <div class="date-filter">
    <form method="get" action="">
        <input type="date" name="filter_date" value="<?php echo htmlspecialchars($selectedDate); ?>">
        <button type="submit">Filter</button>
        <?php if ($selectedDate): ?>
            <button type="button" onclick="window.location.href='teamlead_dashboard.php';">Remove Filter</button>
        <?php endif; ?>
    </form>
    </div>


        <!-- Display OT Submissions -->
        <h3>Team Members' OT Submissions</h3>
        <table class="ot-submissions-table">
            <tr>
                <th>Employee Name</th>
                <th>Date</th>
                <th>Time In</th>
                <th>Time Out</th>
                <th>Site</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php if (!empty($otSubmissions)): ?>
                <?php foreach ($otSubmissions as $submission): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($submission['name']); ?></td>
                        <td><?php echo htmlspecialchars($submission['date']); ?></td>
                        <td><?php echo htmlspecialchars($submission['time_in']); ?></td>
                        <td><?php echo htmlspecialchars($submission['time_out']); ?></td>
                        <td><?php echo htmlspecialchars($submission['site']); ?></td>
                        <td><?php echo htmlspecialchars($submission['status']); ?></td>
                        <td>
                            <div class="action-buttons">
                                <form action="" method="post">
                                    <input type="hidden" name="ot_id" value="<?php echo $submission['id']; ?>">
                                    <input type="hidden" name="status" value="Approved">
                                    <button type="submit" name="update_ot_status" class="approve-btn">Approve</button>
                                </form>
                                <form action="" method="post">
                                    <input type="hidden" name="ot_id" value="<?php echo $submission['id']; ?>">
                                    <input type="hidden" name="status" value="Rejected">
                                    <input type="text" name="rejection_reason" placeholder="Reason for rejection" required>
                                    <button type="submit" name="update_ot_status" class="reject-btn">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7">No OT submissions found.</td>
                </tr>
            <?php endif; ?>
        </table>
    </main>

    <!-- Footer -->
    <footer>
        &copy; 2024 Solara Systems (M) Sdn.Bhd
    </footer>

    <script>
        // Disable right-click
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });

        // Disable common keyboard shortcuts
        document.onkeydown = function(e) {
            if (e.key === "F12" || 
                (e.ctrlKey && e.shiftKey && e.key === "I") || 
                (e.ctrlKey && e.shiftKey && e.key === "J") || 
                (e.ctrlKey && e.key === "U") || 
                (e.ctrlKey && e.key === "S")) {
                return false;
            }
        };

        // Toggle the Employee Info Slider
        function openSlider() {
            document.getElementById('employeeInfoSlider').classList.add('active');
        }

        function closeSlider() {
            document.getElementById('employeeInfoSlider').classList.remove('active');
        }

        // Logout function
        function logout() {
            window.location.href = '../index.html'; // Redirect to the index.html page
        }
    </script>
</body>
</html>
<?php ob_end_flush(); ?>
