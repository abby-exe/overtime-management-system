<?php
session_start();
ob_start(); // Start output buffering

// Include the config file to get the hashed security password
include('config.php');

// Check if the logged-in user is an Admin
if ($_SESSION['role'] !== 'Admin') {
    header('Location: employee_login.php'); // Redirect to login if not an Admin
    exit();
}

// Check for security password session variable
if (!isset($_SESSION['security_verified'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['security_password'])) {
        // Verify the entered password against the hashed password
        if (password_verify($_POST['security_password'], $admin_security_password_hash)) {
            $_SESSION['security_verified'] = true; // Set session variable to verify security
            header('Location: admin.php'); // Redirect to avoid form resubmission
            exit();
        } else {
            echo "<p style='color:red;'>Incorrect security password. Access denied.</p>";
            exit();
        }
    } else {
        // Show the security password form if not already submitted
        echo '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Security Check</title>
            <link rel="stylesheet" href="styles.css">
            <style>
                body {
                    background-color: #f4f4f4;
                    font-family: Arial, sans-serif;
                    text-align: center;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .login-box {
                    background-color: white;
                    padding: 20px;
                    border-radius: 10px;
                    box-shadow: 0px 0px 10px 0px rgba(0, 0, 0, 0.1);
                }
                .login-box h2 {
                    margin-bottom: 20px;
                    color: #003b5c;
                }
                input[type="password"],
                input[type="submit"] {
                    width: 100%;
                    padding: 10px;
                    margin-top: 10px;
                    margin-bottom: 20px;
                    border-radius: 5px;
                    border: 1px solid #ccc;
                }
                input[type="submit"] {
                    background-color: #29ABE2;
                    color: white;
                    cursor: pointer;
                    border: none;
                }
                input[type="submit"]:hover {
                    background-color: #1d89b2;
                }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>Enter Security Password</h2>
                <form action="" method="post">
                    <label for="security_password">Security Password:</label><br>
                    <input type="password" id="security_password" name="security_password" required><br><br>
                    <input type="submit" value="Submit">
                </form>
            </div>
        </body>
        </html>
        ';
        ob_end_flush(); // Flush the output buffer and send output
        exit();
    }
}

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ottest";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Basic SQL Injection Protection
    function clean_input($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Handling the addition of a new department
        if (isset($_POST['add_department'])) {
            $new_department = clean_input($_POST['new_department']);
            
            // Check if the department already exists in the departments table
            $stmt = $conn->prepare("SELECT COUNT(*) FROM departments WHERE department_name = :department_name");
            $stmt->bindParam(':department_name', $new_department);
            $stmt->execute();
            $count = $stmt->fetchColumn();
        
            if ($count == 0) {
                // Insert the new department into the departments table
                $stmt = $conn->prepare("INSERT INTO departments (department_name) VALUES (:department_name)");
                $stmt->bindParam(':department_name', $new_department);
                $stmt->execute();
                echo "<p class='centered-message'>Department added successfully!</p>";
                header("Location: admin.php"); // Reload the page to refresh the department list
                exit();
            } else {
                echo "<p class='centered-message' style='color: red;'>Department already exists!</p>";
            }
        }        

        // Handle create, update, and delete operations
        if (isset($_POST['create'])) {
            $name = clean_input($_POST['full_name']);
            $ic_number = clean_input($_POST['ic_number']);
            $email = clean_input($_POST['email']);
            $department = clean_input($_POST['department']);
            $role = clean_input($_POST['role']);
            $hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);

            $stmt = $conn->prepare("INSERT INTO users (name, ic_number, email, password, department, role) VALUES (:name, :ic_number, :email, :password, :department, :role)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':ic_number', $ic_number);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':role', $role);
            $stmt->execute();
            echo "<p class='centered-message'>Account created successfully!</p>";
        } elseif (isset($_POST['update'])) {
            $user_id = clean_input($_POST['user_id']);
            $name = clean_input($_POST['full_name']);
            $ic_number = clean_input($_POST['ic_number']);
            $email = clean_input($_POST['email']);
            $department = clean_input($_POST['department']);
            $role = clean_input($_POST['role']);

            if (!empty($_POST['password'])) {
                $hashed_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
                $password_sql = "password = :password,";
            } else {
                $password_sql = "";
            }

            $stmt = $conn->prepare("UPDATE users SET name = :name, ic_number = :ic_number, email = :email, $password_sql department = :department, role = :role WHERE id = :id");
            $stmt->bindParam(':id', $user_id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':ic_number', $ic_number);
            $stmt->bindParam(':email', $email);
            if (!empty($_POST['password'])) {
                $stmt->bindParam(':password', $hashed_password);
            }
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':role', $role);
            $stmt->execute();
            echo "<p class='centered-message'>Account updated successfully!</p>";
        } elseif (isset($_POST['delete'])) {
            $user_id = clean_input($_POST['user_id']);
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user_id);
            $stmt->execute();
            echo "<p class='centered-message'>Account deleted successfully!</p>";
        }
    }

    // Fetch all users to display in the admin panel
    $stmt = $conn->prepare("SELECT * FROM users WHERE name != 'Department'");
    $stmt->execute();
    $users = $stmt->fetchAll();

    // Fetch all unique departments for the dropdown from the departments table
    $stmt = $conn->prepare("SELECT department_name FROM departments ORDER BY department_name");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch(PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Panel</title>
    <link rel="icon" href="images/favicon.ico">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-image: url('images/adminbg.png'); /* Set the path to your background image */
            background-size: cover; /* Ensure the background image covers the entire screen */
            background-position: center; /* Center the background image */
            background-repeat: no-repeat; /* Prevent the image from repeating */
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

        .admin-panel-box {
            background-color: rgba(255, 255, 255, 0.9); /* White background with transparency */
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
            margin: 20px auto 60px auto;
            text-align: left;
            box-sizing: border-box;
            overflow-x: auto; /* Allow horizontal scrolling for tables on small screens */
        }

        .admin-panel-box h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #003b5c;
        }

        label {
            font-weight: bold;
            color: #003b5c;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        .password-container {
            position: relative;
        }

        .password-container input[type="password"] {
            padding-right: 40px;
        }

        .password-container .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
        }

        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #e4002b;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
        }

        input[type="submit"]:hover {
            background-color: #c70039;
        }

        header {
            background-color: #29ABE2;
            padding: 10px; /* Reduced padding for smaller header */
            color: #ffffff;
            text-align: center;
            width: 100%;
            position: relative;
            font-size: 1.2rem; /* Adjust font size for header */
        }

        footer {
            background-color: #29ABE2;
            padding: 10px; /* Reduced padding for smaller footer */
            color: #ffffff;
            text-align: center;
            width: 100%;
            position: relative;
            bottom: 0;
            margin-top: auto;
            box-sizing: border-box;
            font-size: 0.8rem; /* Adjust font size for footer */
        }

        .back-button {
            position: fixed;
            bottom: 20px;
            left: 20px;
            padding: 10px 20px;
            background-color: #003b5c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: background-color 0.3s ease;
            z-index: 10;
        }

        .back-button:hover {
            background-color: #002d45;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 0.8rem; /* Adjust font size for better fit */
        }

        table, th, td {
            border: 1px solid #ccc;
        }

        th, td {
            padding: 10px;
            text-align: center; /* Center align table headers and data */
        }

        th {
            background-color: #f2f2f2;
        }

        /* Responsive styling */
        @media only screen and (max-width: 600px) {
            table, th, td {
                display: table-cell;  /* Ensure cells stay in a grid format */
                width: auto;          /* Adjust cell width */
            }

            th, td {
                padding: 8px;
                font-size: 0.9rem;     /* Adjust font size */
                text-align: center;
            }

            th {
                background-color: #29ABE2;
                color: white;
            }

            td {
                border: 1px solid #ccc;
            }

            /* Ensure table is scrollable horizontally on small screens */
            .admin-panel-box {
                overflow-x: auto;
            }
        }

/* Modal styles */
.modal {
    display: none; /* Hidden by default */
    position: fixed; /* Stay in place */
    z-index: 1; /* Sit on top */
    left: 0;
    top: 0;
    width: 100%; /* Full width */
    height: 100%; /* Full height */
    overflow: auto; /* Enable scroll if needed */
    background-color: rgba(0, 0, 0, 0.4); /* Black background with opacity */
}

.modal-content {
    background-color: #fefefe;
    margin: 10% auto; /* Center the modal */
    padding: 20px;
    border: 1px solid #888;
    width: 80%; /* Adjust to a wider width for smaller screens */
    max-width: 500px; /* Set a max-width for larger screens */
    border-radius: 10px;
    text-align: center;
    box-sizing: border-box; /* Ensure padding doesn't add to the width */
}

/* Close button */
.close-button {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close-button:hover,
.close-button:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}

/* Open modal button */
.open-modal-button {
    padding: 10px 20px;
    background-color: #29ABE2;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    display: inline-block;
    margin: 10px 0; /* Add some margin for better spacing */
}

.open-modal-button:hover {
    background-color: #1d89b2;
}

/* Responsive adjustments */
@media only screen and (max-width: 768px) {
    .modal-content {
        width: 90%; /* Increase width on smaller screens */
        padding: 15px; /* Adjust padding */
    }
    
    .open-modal-button {
        padding: 8px 16px; /* Adjust padding for smaller screens */
        font-size: 14px; /* Adjust font size */
    }
}

@media only screen and (max-width: 480px) {
    .modal-content {
        width: 95%; /* Even wider on very small screens */
        padding: 10px; /* Reduce padding further */
    }

    .open-modal-button {
        padding: 6px 12px; /* Adjust padding */
        font-size: 12px; /* Adjust font size */
    }
    
    .close-button {
        font-size: 24px; /* Reduce close button size */
    }
}
    </style>
</head>
<body>
    <header>
        <img src="images/logo.png" alt="Solara Logo" class="logo">
        <h1>Solara Overtime Management System</h1>
    </header>

    <div class="admin-panel-box">
        <h2>Admin Control Panel</h2>

        <form action="" method="post">
            <input type="hidden" id="user_id" name="user_id">
            <label for="full_name">Full Name:</label>
            <input type="text" id="full_name" name="full_name" required>
            <label for="email">Company Email:</label>
            <input type="email" id="email" name="email" required>
            <label for="ic_number">Malaysian IC Number:</label>
            <input type="text" id="ic_number" name="ic_number" required>
            <label for="department">Department:</label>           
<select id="department" name="department" required>
    <option value="">Select Department</option>
    <?php foreach ($departments as $department): ?>
        <option value="<?php echo $department; ?>"><?php echo $department; ?></option>
    <?php endforeach; ?>
</select>
            <label for="role">Role:</label>
            <select id="role" name="role" required>
                <option value="Admin">Admin</option>
                <option value="HR">HR</option>
                <option value="Team Lead">Team Lead</option>
                <option value="User">User</option>
            </select>
            <label for="password">Password:</label>
            <div class="password-container">
                <input type="password" id="password" name="password" required>
                <i class="fas fa-eye toggle-password" onclick="togglePassword('password')"></i>
            </div>
            <input type="submit" name="create" value="Create Account">
            <input type="submit" name="update" value="Update Account">
            <input type="submit" name="delete" value="Delete Account">
        </form>

        <h3>Current Users</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>IC Number</th>
                <th>Department</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr>
                <td data-label="ID"><?php echo $user['id']; ?></td>
                <td data-label="Name"><?php echo $user['name']; ?></td>
                <td data-label="Email"><?php echo $user['email']; ?></td>
                <td data-label="IC Number"><?php echo $user['ic_number']; ?></td>
                <td data-label="Department"><?php echo $user['department']; ?></td>
                <td data-label="Role"><?php echo $user['role']; ?></td>
                <td data-label="Action">
                    <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo $user['name']; ?>', '<?php echo $user['email']; ?>', '<?php echo $user['ic_number']; ?>', '<?php echo $user['department']; ?>', '<?php echo $user['role']; ?>')">Edit</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <!-- Button to open the modal -->
        <button id="openModal" class="open-modal-button">Add New Department</button>

    </div>

<!-- Modal structure -->
<div id="departmentModal" class="modal">
    <div class="modal-content">
        <span class="close-button">&times;</span>
        <h2>Add New Department</h2>
        <form id="addDepartmentForm" action="" method="post">
            <label for="new_department">Department Name:</label>
            <input type="text" id="new_department" name="new_department" placeholder="Type department name" required>
            <input type="submit" value="Add Department" name="add_department">
        </form>
    </div>
</div>

    <button class="back-button" onclick="location.href='employee_login.php'">← Back</button>
    <footer>
        &copy; 2024 Solara Systems (M) Sdn.Bhd
    </footer>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ensure this function is globally accessible since it's called directly from HTML
        window.togglePassword = function(fieldId) {
            var passwordField = document.getElementById(fieldId);
            var icon = passwordField.nextElementSibling;
            if (passwordField.type === "password") {
                passwordField.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                passwordField.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        };

        // Function to edit user details
        window.editUser = function(id, name, email, ic_number, department, role) {
            document.getElementById("user_id").value = id;
            document.getElementById("full_name").value = name;
            document.getElementById("email").value = email;
            document.getElementById("ic_number").value = ic_number;
            document.getElementById("department").value = department;
            document.getElementById("role").value = role;
            document.querySelector("input[name='update']").style.display = 'inline-block';
            document.querySelector("input[name='delete']").style.display = 'inline-block';
            document.querySelector("input[name='create']").style.display = 'none';
        };

        // Hide update and delete buttons on initial load
        document.querySelector("input[name='update']").style.display = 'none';
        document.querySelector("input[name='delete']").style.display = 'none';

        // Modal related functionality
        var modal = document.getElementById("departmentModal");
        var openModalBtn = document.getElementById("openModal");
        var closeModalBtn = document.querySelector(".close-button");

        // Check if modal elements exist before attaching event listeners
        if (modal && openModalBtn && closeModalBtn) {
            // Open the modal when the button is clicked
            openModalBtn.onclick = function() {
                modal.style.display = "block";
            };

            // Close the modal when the close button is clicked
            closeModalBtn.onclick = function() {
                modal.style.display = "none";
            };

            // Close the modal when clicking outside of the modal content
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            };
        }

        // Disable right-click context menu
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });

        // Disable common keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Block the F12 key
            if (e.key === "F12") {
                e.preventDefault();
            }
            
            // Block Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C, and Ctrl+Shift+K
            if ((e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J" || e.key === "C" || e.key === "K")) ||
                (e.ctrlKey && e.key === "U") || // Block Ctrl+U (View Source)
                (e.ctrlKey && e.key === "S")) { // Block Ctrl+S (Save Page)
                e.preventDefault();
            }
        });
    });
</script>

</body>
</html>
<?php
ob_end_flush(); // Flush the output buffer and send output
?>