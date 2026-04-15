<?php
session_start();
require_once 'config.php';

$conn = getConnection();
$error = '';

// 已登录就直接去 dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Please enter username and password.";
    } else {
        $stmt = $conn->prepare("
            SELECT user_id, username, password, role, full_name
            FROM users
            WHERE username = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // 如果你的数据库 password 是明文
        if ($user && $password === $user['password']) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid username or password.";
        }

        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Internship System</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --navy:   #0f1b2d;
      --ink:    #1a2e45;
      --teal:   #0d7377;
      --gold:   #e8a838;
      --cream:  #f7f3ec;
      --white:  #ffffff;
      --muted:  #6b7a8d;
      --border: #d8dce3;
      --shadow: 0 4px 24px rgba(15,27,45,0.10);
      --danger: #c0392b;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: linear-gradient(135deg, #f7f3ec 0%, #eef4f4 100%);
      color: var(--navy);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .topbar {
      background: var(--navy);
      padding: 0 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 62px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    .topbar-brand {
      font-family: 'DM Serif Display', serif;
      color: var(--white);
      font-size: 1.1rem;
    }

    .topbar-brand span {
      color: var(--gold);
    }

    .login-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }

    .login-card {
      width: 100%;
      max-width: 430px;
      background: var(--white);
      border-radius: 18px;
      box-shadow: var(--shadow);
      overflow: hidden;
      border: 1px solid rgba(15,27,45,0.05);
    }

    .login-header {
      background: linear-gradient(135deg, var(--teal) 0%, var(--ink) 100%);
      color: var(--white);
      padding: 2rem 2rem 1.6rem;
      text-align: center;
    }

    .login-header h1 {
      font-family: 'DM Serif Display', serif;
      font-size: 2rem;
      margin-bottom: 0.35rem;
    }

    .login-header p {
      color: rgba(255,255,255,0.78);
      font-size: 0.92rem;
    }

    .login-body {
      padding: 2rem;
    }

    .form-group {
      margin-bottom: 1.1rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.45rem;
      font-size: 0.78rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--muted);
    }

    .form-group input {
      width: 100%;
      padding: 0.85rem 1rem;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.95rem;
      color: var(--navy);
      background: var(--cream);
      transition: all 0.2s ease;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--teal);
      background: var(--white);
      box-shadow: 0 0 0 3px rgba(13,115,119,0.08);
    }

    .error-box {
      margin-bottom: 1rem;
      background: #fff1ef;
      color: var(--danger);
      border: 1px solid #f3c4bd;
      padding: 0.85rem 1rem;
      border-radius: 10px;
      font-size: 0.9rem;
    }

    .btn-login {
      width: 100%;
      border: none;
      border-radius: 10px;
      background: var(--teal);
      color: var(--white);
      padding: 0.9rem 1rem;
      font-size: 0.95rem;
      font-weight: 600;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      transition: background 0.2s ease, transform 0.15s ease;
    }

    .btn-login:hover {
      background: #0a5e62;
    }

    .btn-login:active {
      transform: translateY(1px);
    }

    .login-footer {
      margin-top: 1rem;
      text-align: center;
      font-size: 0.84rem;
      color: var(--muted);
    }

    .login-footer strong {
      color: var(--navy);
    }

    @media (max-width: 520px) {
      .login-card {
        max-width: 100%;
      }

      .login-header,
      .login-body {
        padding: 1.4rem;
      }

      .login-header h1 {
        font-size: 1.7rem;
      }
    }
  </style>
</head>
<body>

  <nav class="topbar">
    <div class="topbar-brand">Internship <span>Results</span> System</div>
  </nav>

  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-header">
        <h1>Welcome Back</h1>
        <p>Sign in to access the internship management dashboard</p>
      </div>

      <div class="login-body">
        <?php if ($error): ?>
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

          <button type="submit" class="btn-login">Login</button>
        </form>

        <div class="login-footer">
          Please log in with your <strong>staff account</strong>.
        </div>
      </div>
    </div>
  </div>

</body>
</html>