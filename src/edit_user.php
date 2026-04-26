<?php
require_once 'auth.php';
requireRole('admin');
require_once 'config.php';

$error = '';
$success = '';

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function getInitials($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper($part[0]);
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return $initials ?: 'AD';
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    header('Location: user_management.php?error=Invalid user selected');
    exit();
}

$conn = getConnection();

$stmt = $conn->prepare(
    "SELECT u.user_id, u.username, u.password, u.full_name, u.role, u.programme AS user_programme,
            u.email, u.student_id, u.status,
            s.programme AS student_programme, s.full_name AS student_full_name,
            s.email AS student_email, s.status AS student_status
     FROM users u
     LEFT JOIN students s ON u.student_id = s.student_id
     WHERE u.user_id = ?
     LIMIT 1"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    $conn->close();
    header('Location: user_management.php?error=User not found');
    exit();
}

$full_name = $user['role'] === 'student' ? ($user['student_full_name'] ?: $user['full_name']) : $user['full_name'];
$role = $user['role'];
$email = $user['role'] === 'student' ? ($user['student_email'] ?: $user['email']) : $user['email'];
$status = $user['role'] === 'student' ? ($user['student_status'] ?: $user['status']) : $user['status'];
$username = $user['username'];
$student_id = $user['student_id'] ?? '';
$programme = $user['role'] === 'student' ? ($user['student_programme'] ?: $user['user_programme']) : ($user['user_programme'] ?? '');
$password = '';
$confirm_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Role is locked during edit to avoid cross-table migration issues.
    if ($role === 'student') {
        $student_id = strtoupper(trim($_POST['student_id'] ?? $student_id));
        $programme = trim($_POST['programme'] ?? '');
        $username = $student_id;
    } elseif ($role === 'assessor') {
        $username = strtolower(trim($_POST['assessor_username'] ?? $username));
        $programme = trim($_POST['assessor_programme'] ?? '');
    } else {
        $username = trim($_POST['username'] ?? $username);
        $programme = '';
    }

    if ($full_name === '' || $email === '' || $status === '') {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $error = 'Invalid status selected.';
    } elseif ($password !== '' && strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== '' && $password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        if ($role === 'student') {
            if ($student_id === '' || $programme === '') {
                $error = 'Please complete all student fields.';
            } elseif (!preg_match('/^S\d{4}$/', $student_id)) {
                $error = 'Student ID must follow the format S0021.';
            }
        } elseif ($role === 'assessor') {
            if ($username === '' || $programme === '') {
                $error = 'Please complete all assessor fields.';
            } elseif (!preg_match('/^as_\d{4}$/', $username)) {
                $error = 'Assessor username must follow the format as_1001.';
            }
        } elseif ($role === 'admin') {
            if ($username === '') {
                $error = 'Username is required.';
            }
        }
    }

    if ($error === '') {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1');
            $stmt->bind_param('si', $email, $user_id);
            $stmt->execute();
            $existingEmail = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existingEmail) {
                throw new Exception('This email is already used by another account.');
            }

            $stmt = $conn->prepare('SELECT user_id FROM users WHERE username = ? AND user_id <> ? LIMIT 1');
            $stmt->bind_param('si', $username, $user_id);
            $stmt->execute();
            $existingUsername = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existingUsername) {
                throw new Exception('This username already exists.');
            }

            if ($role === 'student') {
                $stmt = $conn->prepare('SELECT student_id FROM students WHERE email = ? AND student_id <> ? LIMIT 1');
                $stmt->bind_param('ss', $email, $student_id);
                $stmt->execute();
                $existingStudentEmail = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existingStudentEmail) {
                    throw new Exception('This student email already exists.');
                }

                $stmt = $conn->prepare(
                    'UPDATE students
                     SET full_name = ?, programme = ?, email = ?, status = ?
                     WHERE student_id = ?'
                );
                $stmt->bind_param('sssss', $full_name, $programme, $email, $status, $student_id);
                $stmt->execute();
                $stmt->close();

                if ($password !== '') {
                    $hashedPassword = md5($password);
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, password = ?, full_name = ?, programme = ?, email = ?, status = ?
                         WHERE user_id = ?'
                    );
                    $stmt->bind_param('ssssssi', $username, $hashedPassword, $full_name, $programme, $email, $status, $user_id);
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, full_name = ?, programme = ?, email = ?, status = ?
                         WHERE user_id = ?'
                    );
                    $stmt->bind_param('sssssi', $username, $full_name, $programme, $email, $status, $user_id);
                }
                $stmt->execute();
                $stmt->close();
            } elseif ($role === 'assessor') {
                if ($password !== '') {
                    $hashedPassword = md5($password);
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, password = ?, full_name = ?, programme = ?, email = ?, status = ?
                         WHERE user_id = ?'
                    );
                    $stmt->bind_param('ssssssi', $username, $hashedPassword, $full_name, $programme, $email, $status, $user_id);
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, full_name = ?, programme = ?, email = ?, status = ?
                         WHERE user_id = ?'
                    );
                    $stmt->bind_param('sssssi', $username, $full_name, $programme, $email, $status, $user_id);
                }
                $stmt->execute();
                $stmt->close();
            } else {
                if ($password !== '') {
                    $hashedPassword = md5($password);
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, password = ?, full_name = ?, email = ?, status = ?
                         WHERE user_id = ?'
                    );
                    $stmt->bind_param('sssssi', $username, $hashedPassword, $full_name, $email, $status, $user_id);
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, full_name = ?, email = ?, status = ?
                         WHERE user_id = ?'
                    );
                    $stmt->bind_param('ssssi', $username, $full_name, $email, $status, $user_id);
                }
                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            header('Location: user_management.php?success=User updated successfully');
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Edit User | IRMSYS</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Syne', sans-serif; }
    body { background: #0a0c14; color: #ffffff; display: flex; min-height: 100vh; }
    .sidebar {
      width: 240px; background: #111523; border-right: 1px solid rgba(255,255,255,0.06);
      display: flex; flex-direction: column; justify-content: space-between; padding: 24px 0;
    }
    .logo {
      font-size: 18px; font-weight: 700; color: #66a3ff;
      padding: 0 24px 24px; border-bottom: 1px solid rgba(255,255,255,0.06);
    }
    .nav-section { padding-top: 18px; }
    .nav-title {
      font-size: 11px; letter-spacing: 2px; color: rgba(255,255,255,0.35);
      padding: 0 24px 14px; text-transform: uppercase;
    }
    .nav-item {
      display: flex; align-items: center; gap: 12px; color: rgba(255,255,255,0.75);
      text-decoration: none; padding: 14px 24px; transition: 0.2s;
    }
    .nav-item:hover, .nav-item.active {
      background: rgba(74, 125, 255, 0.12); color: #66a3ff;
      border-left: 3px solid #4a7dff; padding-left: 21px;
    }
    .admin-box {
      border-top: 1px solid rgba(255,255,255,0.06); padding: 20px 24px 0;
      display: flex; align-items: center; gap: 12px;
    }
    .admin-avatar {
      width: 40px; height: 40px; border-radius: 50%; background: #738bff;
      display: flex; align-items: center; justify-content: center; font-weight: 700;
    }
    .admin-info small { color: rgba(255,255,255,0.55); }
    .main { flex: 1; padding: 34px 40px; }
    .topbar h1 { font-size: 32px; font-weight: 700; margin-bottom: 6px; }
    .topbar p { color: rgba(255,255,255,0.65); margin-bottom: 36px; }
    .form-wrapper {
      background: linear-gradient(180deg, #12172a 0%, #0f1430 100%);
      border: 1px solid rgba(255,255,255,0.06); border-radius: 28px; padding: 34px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.28);
    }
    .note-box, .error-box {
      padding: 18px 22px; border-radius: 18px; margin-bottom: 28px; font-size: 15px;
      line-height: 1.6;
    }
    .note-box { background: rgba(111,144,255,0.12); border: 1px solid rgba(111,144,255,0.22); color: #cddbff; }
    .error-box { background: rgba(255,102,102,0.10); border: 1px solid rgba(255,102,102,0.22); color: #ffb4b4; }
    .section-title { font-size: 18px; font-weight: 700; margin: 18px 0 22px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
    .field-group { display: flex; flex-direction: column; gap: 9px; }
    .field-group.full-width { grid-column: 1 / -1; }
    label { font-size: 14px; color: rgba(255,255,255,0.88); }
    .required { color: #ff9b9b; }
    input, select {
      width: 100%; padding: 16px 18px; border-radius: 16px;
      border: 1px solid rgba(255,255,255,0.08); background: #0c1125; color: #fff;
      font-size: 15px; outline: none; transition: 0.2s;
    }
    input:focus, select:focus { border-color: rgba(111,144,255,0.85); box-shadow: 0 0 0 3px rgba(111,144,255,0.12); }
    input[readonly] {
      background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.72); cursor: not-allowed;
    }
    .helper-text { color: rgba(255,255,255,0.5); font-size: 13px; line-height: 1.5; }
    .button-row { display: flex; justify-content: flex-end; gap: 10px; margin-top: 34px; }
    .btn-primary, .btn-secondary {
      display: inline-flex; align-items: center; justify-content: center;
      width: 136px; min-width: 136px; height: 42px; padding: 0 16px;
      border-radius: 10px; text-decoration: none; font-size: 13.5px; font-weight: 600;
      transition: all 0.15s ease; cursor: pointer; border: 1px solid transparent;
      font-family: var(--font);
    }
    .btn-primary {
      background: rgba(79,142,247,0.12);
      color: #4f8ef7;
      border-color: rgba(79,142,247,0.25);
    }
    .btn-primary:hover {
      background: rgba(79,142,247,0.18);
      border-color: rgba(79,142,247,0.42);
      color: #78a8ff;
      transform: translateY(-1px);
    }
    .btn-secondary {
      background: rgba(255,255,255,0.04);
      color: rgba(232,234,240,0.82);
      border-color: rgba(255,255,255,0.08);
    }
    .btn-secondary:hover { background: rgba(255,255,255,0.08); color: #fff; }
    @media (max-width: 900px) {
      body { flex-direction: column; }
      .sidebar { width: 100%; }
      .main { padding: 24px; }
    }
    @media (max-width: 700px) {
      .form-grid { grid-template-columns: 1fr; }
      .form-wrapper { padding: 20px; }
    }
  </style>
</head>
<body>
  <aside class="sidebar">
    <div>
      <div class="logo">IRMSYS</div>
      <div class="nav-section">
        <div class="nav-title">Admin Panel</div>
        <a href="admin_dashboard.php" class="nav-item">Dashboard</a>
        <a href="user_management.php" class="nav-item active">User Management</a>
        <a href="internship_list.php" class="nav-item">Internship Mgmt</a>
        <a href="view_results.php" class="nav-item">Results</a>
      </div>
    </div>

    <div class="admin-box">
      <div class="admin-avatar"><?= h(getInitials($_SESSION['full_name'] ?? 'Administrator')) ?></div>
      <div class="admin-info">
        <div><?= h($_SESSION['full_name'] ?? 'Administrator') ?></div>
        <small>Administrator</small>
      </div>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <h1>Edit User</h1>
      <p>Update account details for the selected user</p>
    </div>

    <div class="form-wrapper">
      <div class="note-box">
        Role and student ID are locked during edit. For student accounts, the username follows the student ID. Leave the password fields empty if you do not want to change the password.
      </div>

      <?php if ($error !== ''): ?>
        <div class="error-box"><?= h($error) ?></div>
      <?php endif; ?>

      <form id="editUserForm" action="edit_user.php?id=<?= (int)$user_id ?>" method="POST">
        <div class="section-title">Basic Information</div>

        <div class="form-grid">
          <div class="field-group">
            <label for="full_name">Full Name <span class="required">*</span></label>
            <input type="text" id="full_name" name="full_name" placeholder="Enter full name" value="<?= h($full_name) ?>" required>
          </div>

          <div class="field-group">
            <label for="role_display">Role</label>
            <input type="text" id="role_display" value="<?= h(ucfirst($role)) ?>" readonly>
          </div>

          <div class="field-group">
            <label for="email">Email <span class="required">*</span></label>
            <input type="email" id="email" name="email" placeholder="Enter email address" value="<?= h($email) ?>" required>
          </div>

          <div class="field-group">
            <label for="status">Status <span class="required">*</span></label>
            <select id="status" name="status" required>
              <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>

          <div class="field-group">
            <label for="password">New Password</label>
            <input type="password" id="password" name="password" placeholder="Leave blank to keep current password">
            <div class="helper-text">Only enter a new password if you want to reset it.</div>
          </div>

          <div class="field-group">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password">
            <div class="helper-text">Password must be at least 8 characters.</div>
          </div>
        </div>

        <div class="section-title">Role Details</div>

        <?php if ($role === 'student'): ?>
          <div class="form-grid">
            <div class="field-group">
              <label for="student_id">Student ID</label>
              <input type="text" id="student_id" name="student_id" value="<?= h($student_id) ?>" readonly>
              <div class="helper-text">Locked because it is linked to users and internship records.</div>
            </div>

            <div class="field-group">
              <label for="student_username">Username</label>
              <input type="text" id="student_username" value="<?= h($student_id) ?>" readonly>
              <div class="helper-text">Student username always follows the student ID.</div>
            </div>

            <div class="field-group full-width">
              <label for="programme">Programme <span class="required">*</span></label>
              <select id="programme" name="programme" required>
                <option value="">Select programme</option>
                <option value="Engineering" <?= $programme === 'Engineering' ? 'selected' : '' ?>>Engineering</option>
                <option value="Arts and Design" <?= $programme === 'Arts and Design' ? 'selected' : '' ?>>Arts and Design</option>
                <option value="Computer Science" <?= $programme === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                <option value="Finance" <?= $programme === 'Finance' ? 'selected' : '' ?>>Finance</option>
              </select>
            </div>
          </div>
        <?php elseif ($role === 'assessor'): ?>
          <div class="form-grid">
            <div class="field-group">
              <label for="assessor_username">Assessor Username <span class="required">*</span></label>
              <input type="text" id="assessor_username" name="assessor_username" pattern="^as_\d{4}$" placeholder="e.g. as_1001" value="<?= h($username) ?>" required oninput="this.value = this.value.toLowerCase()">
              <div class="helper-text">Must follow the format as_1001.</div>
            </div>

            <div class="field-group">
              <label for="assessor_programme">Programme <span class="required">*</span></label>
              <select id="assessor_programme" name="assessor_programme" required>
                <option value="">Select programme</option>
                <option value="Engineering" <?= $programme === 'Engineering' ? 'selected' : '' ?>>Engineering</option>
                <option value="Arts and Design" <?= $programme === 'Arts and Design' ? 'selected' : '' ?>>Arts and Design</option>
                <option value="Computer Science" <?= $programme === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                <option value="Finance" <?= $programme === 'Finance' ? 'selected' : '' ?>>Finance</option>
              </select>
            </div>
          </div>
        <?php else: ?>
          <div class="form-grid">
            <div class="field-group full-width">
              <label for="username">Username</label>
              <input type="text" id="username" name="username" value="<?= h($username) ?>" readonly>
              <div class="helper-text">Admin username is kept read-only here for safety.</div>
            </div>
          </div>
        <?php endif; ?>

        <div class="button-row">
          <a href="user_management.php" class="btn-secondary">Cancel</a>
          <button type="submit" class="btn-primary">Save Changes</button>
        </div>
      </form>
    </div>
  </main>

  <script>
    document.getElementById('editUserForm').addEventListener('submit', function (e) {
      const password = document.getElementById('password').value.trim();
      const confirmPassword = document.getElementById('confirm_password').value.trim();

      if (password !== '' && password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters.');
        return;
      }

      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match.');
        return;
      }
    });
  </script>
</body>
</html>