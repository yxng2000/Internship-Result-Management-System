<?php
session_start();
require_once 'config.php';

$error = "";

// if already logged in, redirect to respective dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'assessor') {
        header("Location: assessor_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
        exit();
    }
}

// process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Please enter username and password.";
    } else {
        $isValidFormat = false;

        if ($username === 'admin') {
            $isValidFormat = true;
        } elseif (preg_match('/^as_\d{4}$/', $username)) {
            $isValidFormat = true;
        } elseif (preg_match('/^S\d{4}$/', $username)) {
            $isValidFormat = true;
        }

        if (!$isValidFormat) {
            $error = "Invalid username format.";
        } else {
            $conn = getConnection();

            $stmt = $conn->prepare("
                SELECT user_id, username, password, role, full_name, student_id, status
                FROM users
                WHERE username = ?
                LIMIT 1
            ");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if (!$user) {
                $error = "Username does not exist.";
            } elseif ($user['status'] !== 'active') {
                $error = "This account is inactive.";
            } elseif (md5($password) !== $user['password']) {
                $error = "Invalid password.";
            } else {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['student_id'] = $user['student_id'];

                if ($user['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                    exit();
                } elseif ($user['role'] === 'assessor') {
                    header("Location: assessor_dashboard.php");
                    exit();
                } elseif ($user['role'] === 'student') {
                    header("Location: student_dashboard.php");
                    exit();
                } else {
                    $error = "Invalid user role.";
                }
            }

            $stmt->close();
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | IRMSYS</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Syne', sans-serif;
    }

    body {
      background: #0a0c14;
      color: #ffffff;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .topbar {
      height: 72px;
      display: flex;
      align-items: center;
      padding: 0 32px;
      background: #111523;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    .logo {
      font-size: 18px;
      font-weight: 700;
      color: #66a3ff;
      letter-spacing: 0.5px;
    }

    .page-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px;
    }

    .login-card {
      width: 100%;
      max-width: 460px;
      background: #121726;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 20px 50px rgba(0,0,0,0.28);
    }

    .login-header {
      margin-bottom: 24px;
    }

    .login-header h1 {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 8px;
    }

    .login-header p {
      color: rgba(255,255,255,0.6);
      font-size: 14px;
      line-height: 1.5;
    }

    .note-box {
      background: rgba(102,163,255,0.08);
      border: 1px solid rgba(102,163,255,0.18);
      color: #b9d2ff;
      border-radius: 14px;
      padding: 14px 16px;
      font-size: 13px;
      margin-bottom: 20px;
      line-height: 1.5;
    }

    .error-box {
      background: rgba(255, 123, 123, 0.08);
      border: 1px solid rgba(255, 123, 123, 0.22);
      color: #ff9c9c;
      border-radius: 14px;
      padding: 14px 16px;
      font-size: 13px;
      margin-bottom: 20px;
      line-height: 1.5;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 18px;
    }

    label {
      font-size: 13px;
      color: rgba(255,255,255,0.72);
      font-weight: 500;
    }

    input {
      width: 100%;
      background: #0f1422;
      border: 1px solid rgba(255,255,255,0.08);
      color: #ffffff;
      border-radius: 14px;
      padding: 14px 15px;
      font-size: 14px;
      outline: none;
      transition: 0.2s;
    }

    input:focus {
      border-color: #4a7dff;
      box-shadow: 0 0 0 3px rgba(74,125,255,0.12);
    }

    .btn-primary {
      width: 100%;
      border: none;
      border-radius: 14px;
      padding: 14px 22px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
      background: linear-gradient(135deg, #4a7dff, #6699ff);
      color: white;
      margin-top: 8px;
    }

    .btn-primary:hover {
      opacity: 0.93;
      transform: translateY(-1px);
    }

    .login-footer {
      margin-top: 18px;
      color: rgba(255,255,255,0.45);
      font-size: 12px;
      text-align: center;
      line-height: 1.5;
    }

    @media (max-width: 600px) {
      .topbar {
        padding: 0 20px;
      }

      .page-wrapper {
        padding: 20px;
      }

      .login-card {
        padding: 22px;
      }

      .login-header h1 {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>

  <header class="topbar">
    <div class="logo">IRMSYS</div>
  </header>

  <main class="page-wrapper">
    <div class="login-card">
      <div class="login-header">
        <h1>Login</h1>
        <p>Sign in to access the Internship Result Management System.</p>
      </div>

      <?php if (!empty($error)): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <div class="form-group">
          <label for="login_input">Username</label>
          <input
            type="text"
            id="login_input"
            name="username"
            placeholder="Enter your username"
            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
            required
          >
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            required
          >
        </div>

        <button type="submit" class="btn-primary">Login</button>
      </form>

      <div class="login-footer">
        Internship Result Management System
      </div>
    </div>
  </main>

</body>
</html>