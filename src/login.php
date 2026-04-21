<?php
session_start();
require_once 'config.php';

$error = "";

function redirectByRole($role) {
    if ($role === 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    }

    if ($role === 'lecturer' || $role === 'supervisor') {
        header("Location: assessor_dashboard.php");
        exit();
    }

    if ($role === 'student') {
        header("Location: student_dashboard.php");
        exit();
    }
}

// if already logged in, redirect to respective dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    redirectByRole($_SESSION['role']);
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
        } elseif (preg_match('/^lec_\d{4}$/', $username)) {
            $isValidFormat = true;
        } elseif (preg_match('/^sup_\d{4}$/', $username)) {
            $isValidFormat = true;
        } elseif (preg_match('/^S\d{4}$/', $username)) {
            $isValidFormat = true;
        }

        if (!$isValidFormat) {
            $error = "Invalid username format.";
        } else {
            $conn = getConnection();

            $stmt = $conn->prepare("
                SELECT user_id, username, password, role, full_name, student_id, programme, company_name, status
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
                $_SESSION['programme'] = $user['programme'];
                $_SESSION['company_name'] = $user['company_name'];

                redirectByRole($user['role']);
                $error = "Invalid user role.";
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

    .test-accounts {
      margin-top: 18px;
      padding: 14px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      background: rgba(255,255,255,0.02);
    }

    .test-accounts-title {
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--accent);
      margin-bottom: 12px;
    }

    .test-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }

    .test-item {
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px;
    }

    .test-role {
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 6px;
      color: var(--text);
    }

    .test-cred {
      font-size: 11.5px;
      color: var(--muted);
      line-height: 1.6;
      word-break: break-word;
    }

    .test-cred span {
      color: var(--text);
      font-family: var(--mono);
      font-size: 11.5px;
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

      .test-grid {
        grid-template-columns: 1fr;
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

      <div class="test-accounts">
        <div class="test-accounts-title">Test Accounts</div>
        <div class="test-grid">
          <div class="test-item">
            <div class="test-role">Admin</div>
            <div class="test-cred">Username: <span>admin</span></div>
            <div class="test-cred">Password: <span>admin123</span></div>
          </div>

          <div class="test-item">
            <div class="test-role">Lecturer</div>
            <div class="test-cred">Username: <span>lec_1001</span></div>
            <div class="test-cred">Password: <span>lina1234</span></div>
          </div>

          <div class="test-item">
            <div class="test-role">Supervisor</div>
            <div class="test-cred">Username: <span>sup_2001</span></div>
            <div class="test-cred">Password: <span>intel123</span></div>
          </div>

          <div class="test-item">
            <div class="test-role">Student</div>
            <div class="test-cred">Username: <span>S0021</span></div>
            <div class="test-cred">Password: <span>stud0021</span></div>
          </div>
        </div>
      </div>

      <div class="login-footer">Secure access • Internship Result Management</div>
    </div>
  </div>
</body>
</html>