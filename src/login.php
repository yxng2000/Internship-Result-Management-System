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
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    :root {
      --bg:        #0e0f13;
      --surface:   #16181f;
      --surface2:  #1e2029;
      --border:    #2a2d38;
      --accent:    #4f8ef7;
      --accent2:   #7c6af7;
      --text:      #e8eaf0;
      --muted:     #6b7080;
      --danger:    #e05555;
      --radius:    10px;
      --font:      'Syne', sans-serif;
      --mono:      'DM Mono', monospace;
    }

    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px;
      background:
        radial-gradient(circle at top left, rgba(79,142,247,0.08), transparent 28%),
        radial-gradient(circle at bottom right, rgba(124,106,247,0.08), transparent 30%),
        var(--bg);
      color: var(--text);
      font-family: var(--font);
    }

    .login-shell {
      width: 100%;
      max-width: 440px;
    }

    .login-brand {
      text-align: center;
      margin-bottom: 18px;
    }

    .login-brand .system {
      font-size: 13px;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--accent);
      font-weight: 700;
      margin-bottom: 8px;
    }

    .login-brand h1 {
      font-size: 28px;
      font-weight: 700;
      letter-spacing: -0.02em;
      margin-bottom: 6px;
    }

    .login-brand p {
      font-size: 13px;
      color: var(--muted);
      line-height: 1.6;
    }

    .login-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 26px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.22);
    }

    .helper-box,
    .error-box {
      border-radius: var(--radius);
      padding: 12px 14px;
      font-size: 13px;
      line-height: 1.55;
      margin-bottom: 16px;
    }

    .helper-box {
      background: rgba(79,142,247,0.08);
      border: 1px solid rgba(79,142,247,0.18);
      color: #bfd3ff;
    }

    .error-box {
      background: rgba(224,85,85,0.08);
      border: 1px solid rgba(224,85,85,0.22);
      color: #ffaaaa;
    }

    .form-group {
      margin-bottom: 16px;
    }

    label {
      display: block;
      margin-bottom: 8px;
      font-size: 12px;
      font-weight: 600;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--muted);
    }

    input {
      width: 100%;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      color: var(--text);
      font-family: var(--font);
      font-size: 14px;
      padding: 12px 14px;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
    }

    input::placeholder {
      color: var(--muted);
    }

    input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(79,142,247,0.12);
      background: #202330;
    }

    .login-btn {
      width: 100%;
      border: none;
      border-radius: var(--radius);
      background: var(--accent);
      color: #fff;
      font-family: var(--font);
      font-size: 13.5px;
      font-weight: 600;
      padding: 12px 16px;
      cursor: pointer;
      transition: all 0.15s;
      margin-top: 4px;
    }

    .login-btn:hover {
      background: #3d7ef5;
      transform: translateY(-1px);
    }

    .login-footer {
      margin-top: 16px;
      text-align: center;
      font-size: 11.5px;
      color: var(--muted);
      font-family: var(--mono);
      letter-spacing: 0.04em;
    }

    @media (max-width: 520px) {
      body {
        padding: 20px;
      }

      .login-card {
        padding: 20px;
      }

      .login-brand h1 {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>
  <div class="login-shell">
    <div class="login-brand">
      <div class="system">IRMSYS</div>
      <h1>Welcome back</h1>
      <p>Sign in to continue to the Internship Result Management System.</p>
    </div>

    <div class="login-card">
      <div class="helper-box">
        Use your assigned username and password to access the correct dashboard.
      </div>

      <?php if (!empty($error)): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <div class="form-group">
          <label for="username">Username</label>
          <input
            type="text"
            id="username"
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

        <button type="submit" class="login-btn">Sign In</button>
      </form>

      <div class="login-footer">Secure access • Internship Result Management</div>
    </div>
  </div>
</body>
</html>