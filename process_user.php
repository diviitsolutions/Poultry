<?php
session_start();
require_once 'db.php';

// Set header to return JSON
header("Content-Type: application/json");

// Enable detailed error reporting (for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to ensure required columns exist in the users table
function ensureColumnsExist($pdo)
{
    $columns = [
        'full_name' => "VARCHAR(255) NOT NULL",
        'email'     => "VARCHAR(255) NOT NULL UNIQUE",
        'username'  => "VARCHAR(255) NOT NULL UNIQUE",
        'password'  => "VARCHAR(255) NOT NULL",
        'role'      => "VARCHAR(50) NOT NULL DEFAULT 'worker'",
        'created_at'=> "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($columns as $column => $definition) {
        $checkColumn = $pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
        $checkColumn->execute([$column]);
        if ($checkColumn->rowCount() === 0) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
        }
    }
}

ensureColumnsExist($pdo);

// Get referring URL for error messages if needed (not used in JSON responses)
// $redirectUrl = $_SERVER['HTTP_REFERER'] ?? 'admin_dashboard.php?section=usersSection';

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role'] ?? 'worker');

    if ($fullName && $email && $username && $password) {
        try {
            // Check for duplicate email or username
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email OR username = :username");
            $stmt->execute(['email' => $email, 'username' => $username]);
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    "status" => "error", 
                    "message" => "Email or Username already exists!"
                ]);
                exit();
            }

            // Insert new user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, username, password, role, created_at) VALUES (:full_name, :email, :username, :password, :role, NOW())");
            $stmt->execute([
                'full_name' => $fullName,
                'email'     => $email,
                'username'  => $username,
                'password'  => $hashedPassword,
                'role'      => $role
            ]);

            $userId = $pdo->lastInsertId();
            echo json_encode([
                "status" => "success", 
                "message" => "User added successfully with ID: $userId"
            ]);
            exit();
        } catch (PDOException $e) {
            echo json_encode([
                "status" => "error", 
                "message" => "Database Error: " . $e->getMessage()
            ]);
            exit();
        }
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "Please fill all fields!"
        ]);
        exit();
    }
}

// Handle Edit User (only full_name and username are editable)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id       = trim($_POST['id'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');

    if ($id && $fullName && $username) {
        try {
            // Check for duplicate username (excluding the current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
            $stmt->execute(['username' => $username, 'id' => $id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    "status" => "error", 
                    "message" => "Username already exists!"
                ]);
                exit();
            }

            // Update user details
            $stmt = $pdo->prepare("UPDATE users SET full_name = :full_name, username = :username WHERE id = :id");
            $stmt->execute(['full_name' => $fullName, 'username' => $username, 'id' => $id]);

            echo json_encode([
                "status" => "success", 
                "message" => "User updated successfully!"
            ]);
            exit();
        } catch (PDOException $e) {
            echo json_encode([
                "status" => "error", 
                "message" => "Database Error: " . $e->getMessage()
            ]);
            exit();
        }
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "Please fill all fields!"
        ]);
        exit();
    }
}

// Handle Delete User via GET parameter
if (isset($_GET['delete'])) {
    $id = trim($_GET['delete']);
    if ($id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode([
                "status" => "success", 
                "message" => "User deleted successfully!"
            ]);
            exit();
        } catch (PDOException $e) {
            echo json_encode([
                "status" => "error", 
                "message" => "Error deleting user: " . $e->getMessage()
            ]);
            exit();
        }
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "Invalid user ID!"
        ]);
        exit();
    }
}

// If no condition is met, output a generic error.
echo json_encode([
    "status" => "error", 
    "message" => "Invalid request!"
]);
exit();
?>


