<?php
session_start();

// Database connection settings
$host = 'localhost';
$db   = 'db_name';
$user = 'user_name';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$alertType = '';
$alertMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Retrieve and sanitize input
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :username OR name = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $userRow = $stmt->fetch();

        if ($userRow) {
            // Verify hashed password
            if (password_verify($password, $userRow['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $userRow['id'];
                $_SESSION['user_name'] = $userRow['name'];
                $_SESSION['user_role'] = $userRow['role'];

                // Update the last_login timestamp
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :userId");
                $updateStmt->execute(['userId' => $userRow['id']]);

                // Redirect based on role
                if ($userRow['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                    exit();
                } elseif ($userRow['role'] === 'manager') {
                    header("Location: manager_dashboard.php");
                    exit();
                } elseif ($userRow['role'] === 'worker') {
                    header("Location: worker_dashboard.php");
                    exit();
                } else {
                    $alertType = 'error';
                    $alertMessage = 'Unknown user role.';
                }
            } else {
                $alertType = 'error';
                $alertMessage = 'Invalid credentials. Please try again.';
            }
        } else {
            $alertType = 'error';
            $alertMessage = 'User not found.';
        }
    } else {
        $alertType = 'error';
        $alertMessage = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Divi Poultry - Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link href="https://fonts.googleapis.com/css2?family=Afacad+Flux:wght@100..1000&family=Inconsolata:wght@200..900&family=Playfair:ital,opsz,wght@0,5..1200,300..900;1,5..1200,300..900&family=Quattrocento+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <!-- Internal CSS -->
  <style>
    * {
      box-sizing: border-box;
    }
    body, html {
      height: 100%;
      margin: 0;
      font-family: "Afacad Flux", sans-serif;
    }
    body {
      background: url('https://poultry.divi.co.ke/includes/indexbg.jpg') no-repeat center center fixed;
      background-size: cover;
      overflow-x: hidden;
    }
    .login-container {
      background: rgba(255, 255, 255, 0.95);
      max-width: 400px;
      margin: 80px auto;
      padding: 40px;
      border-radius: 8px;
      box-shadow: 0px 0px 10px 0px #000;
      text-align: center;
    }
    .login-container h2 {
      margin-bottom: 30px;
      color: #333;
    }
    .login-container label {
      display: block;
      margin-bottom: 5px;
      color: #555;
      text-align: left;
    }
    .login-container input[type="text"],
    .login-container input[type="password"] {
      width: 100%;
      padding: 12px;
      margin-bottom: 20px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    .login-container button {
      width: 100%;
      padding: 12px;
      background-color: #28a745;
      border: none;
      color: #fff;
      font-size: 16px;
      border-radius: 4px;
      cursor: pointer;
    }
    .login-container button:hover {
      background-color: #218838;
    }
    .version-info {
      margin-top: 20px;
      font-size: 14px;
      color: #333;
    }
    .version-info span {
      display: block;
    }
    @media (max-width: 480px) {
      .login-container {
        margin: 20px;
        padding: 20px;
      }
    }
  </style>
 
<link rel="manifest" href="manifest.json">

</head>
<body>
<div style="color: #fff; font-size: 40px; margin: 0 auto; text-align: center;">
  <h6>Email: Kebopoultry@gmail.com</h6>
  <button id="installBtn" style="display: none;">Install App</button>

</div> 
<div class="login-container">
  <h2>KEBO POULTRY</h2>
  <form method="post" action="">
    <label for="username">Username or Email:</label>
    <input type="text" name="username" id="username" placeholder="Enter your username or email" required>
    
    <label for="password">Password:</label>
    <input type="password" name="password" id="password" placeholder="Enter your password" required>
    
    <button type="submit" name="login">Login</button>
  </form>
  
  <div class="version-info">
    <span><strong>Version:</strong> 1.2.4</span>
    <span>Developed by Moses Kiplangat</span>
    <span>Divi IT Solutions</span>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  <?php if (!empty($alertMessage)): ?>
    Swal.fire({
      icon: '<?php echo $alertType; ?>',
      title: '<?php echo ($alertType === "error") ? "Error" : "Success"; ?>',
      text: '<?php echo $alertMessage; ?>',
      confirmButtonColor: '#3085d6'
    });
  <?php endif; ?>
</script>
 <script>
  if ("serviceWorker" in navigator) {
  window.addEventListener("load", () => {
    navigator.serviceWorker.register("sw.js")
      .then(reg => console.log("Service Worker registered!", reg))
      .catch(err => console.log("Service Worker registration failed:", err));
  });
}
</script>
<script src="install.js"></script>
</body>
</html>
