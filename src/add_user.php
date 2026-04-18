<?php
require_once 'auth.php';
requireRole('admin');
require_once 'config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$error = '';
$success = '';

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function getInitials($name) {
    $parts = preg_split('/\s+/', trim($name));
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

$full_name = '';
$role = '';
$email = '';
$status = 'active';
$student_id = '';
$programme = '';
$assessor_username = '';
$assessor_programme = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = trim($_POST['status'] ?? 'active');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $student_id = strtoupper(trim($_POST['student_id'] ?? ''));
    $programme = trim($_POST['programme'] ?? '');
    $assessor_username = strtolower(trim($_POST['assessor_username'] ?? ''));
    $assessor_programme = trim($_POST['assessor_programme'] ?? '');

    if ($role === 'student') {
        $assessor_username = '';
        $assessor_programme = '';
    } elseif ($role === 'assessor') {
        $student_id = '';
        $programme = '';
    }

    if ($full_name === '' || $role === '' || $email === '' || $status === '' || $password === '' || $confirm_password === '') {
        $error = 'Please complete all required fields.';
    } elseif (!in_array($role, ['student', 'assessor'])) {
        $error = 'Invalid role selected.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($status, ['active', 'inactive'])) {
        $error = 'Invalid status selected.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        if ($role === 'student') {
            if ($student_id === '' || $programme === '') {
                $error = 'Please complete all student fields.';
            } elseif (!preg_match('/^S\d{4}$/', $student_id)) {
                $error = 'Student ID must follow the format S0021.';
            }
        } elseif ($role === 'assessor') {
            if ($assessor_username === '' || $assessor_programme === '') {
                $error = 'Please complete all assessor fields.';
            } elseif (!preg_match('/^as_\d{4}$/', $assessor_username)) {
                $error = 'Assessor username must follow the format as_1001.';
            }
        }
    }

    if ($error === '') {
        $conn = getConnection();


function writeActivityLog($conn, $actionType, $targetType, $targetId, $title, $description, $linkUrl = null)
{
    $actionType = mysqli_real_escape_string($conn, $actionType);
    $targetType = mysqli_real_escape_string($conn, $targetType);
    $title = mysqli_real_escape_string($conn, $title);
    $description = mysqli_real_escape_string($conn, $description);
    $linkUrl = $linkUrl !== null ? mysqli_real_escape_string($conn, $linkUrl) : null;

    $targetIdValue = $targetId === null ? 'NULL' : (int)$targetId;
    $linkValue = $linkUrl === null || $linkUrl === '' ? 'NULL' : "'" . $linkUrl . "'";

    $sql = "
        INSERT INTO activity_logs (action_type, target_type, target_id, title, description, link_url)
        VALUES (
            '$actionType',
            '$targetType',
            $targetIdValue,
            '$title',
            '$description',
            $linkValue
        )
    ";

    return mysqli_query($conn, $sql);
}

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $existingEmail = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existingEmail) {
                throw new Exception('This email is already used by another account.');
            }

            $hashedPassword = md5($password);
            // Better security in real projects:
            // $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            if ($role === 'student') {
                $stmt = $conn->prepare("SELECT student_id FROM students WHERE student_id = ? LIMIT 1");
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                $existingStudent = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existingStudent) {
                    throw new Exception('This student ID already exists.');
                }

                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                $existingUsername = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existingUsername) {
                    throw new Exception('This username already exists.');
                }

                $stmt = $conn->prepare("
                    INSERT INTO students (student_id, full_name, programme, email, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssss", $student_id, $full_name, $programme, $email, $status);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("
                    INSERT INTO users (username, password, full_name, role, email, student_id, status, programme)
                    VALUES (?, ?, ?, 'student', ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssssss", $student_id, $hashedPassword, $full_name, $email, $student_id, $status, $programme);
                $stmt->execute();
                $newUserId = (int)$conn->insert_id;
                $stmt->close();
            }

            if ($role === 'assessor') {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
                $stmt->bind_param("s", $assessor_username);
                $stmt->execute();
                $existingUsername = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existingUsername) {
                    throw new Exception('This assessor username already exists.');
                }

                $stmt = $conn->prepare("
                    INSERT INTO users (username, password, full_name, role, email, student_id, status, programme)
                    VALUES (?, ?, ?, 'assessor', ?, NULL, ?, ?)
                ");
                $stmt->bind_param("ssssss", $assessor_username, $hashedPassword, $full_name, $email, $status, $assessor_programme);
                $stmt->execute();
                $newUserId = (int)$conn->insert_id;
                $stmt->close();
            }

            writeActivityLog(
                $conn,
                'add',
                'user',
                $newUserId ?? null,
                'New ' . ucfirst($role) . ' account created',
                $full_name . ' was added to the system and is currently marked as ' . $status . '.',
                'user_management.php'
            );

            $conn->commit();
            $_SESSION['success'] = 'User added successfully.';
            header("Location: user_management.php");
            exit();
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }

        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add User | IRMSYS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:        #0e0f13;
    --surface:   #16181f;
    --surface2:  #1e2029;
    --border:    #2a2d38;
    --accent:    #4f8ef7;
    --accent2:   #7c6af7;
    --text:      #e8eaf0;
    --muted:     #6b7080;
    --success:   #34c97b;
    --warning:   #f0a030;
    --danger:    #e05555;
    --radius:    10px;
    --font:      'Syne', sans-serif;
    --mono:      'DM Mono', monospace;
  }

  body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
  }

  .sidebar {
    width: 220px;
    flex-shrink: 0;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    padding: 24px 0;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    overflow-y: auto;
  }

  .logo {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: var(--accent);
    padding: 0 20px 28px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 16px;
    text-transform: uppercase;
  }

  .logo span { color: var(--text); }

  .nav-label {
    font-size: 10px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--muted);
    padding: 0 20px 8px;
  }

  .nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    font-size: 13.5px;
    font-weight: 500;
    color: var(--muted);
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: all 0.15s;
  }

  .nav-item:hover { color: var(--text); background: var(--surface2); }
  .nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(79,142,247,0.07); }

  .sidebar-footer {
    margin-top: auto;
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
  }

  .sidebar-user {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    min-width: 0;
  }

  .avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
  }

  .user-name {
    font-size: 12px;
    font-weight: 500;
    color: rgba(232, 234, 240, 0.68);
    line-height: 1.3;
    white-space: nowrap;
  }

  .logout-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: transparent;
    color: #ff6b6b;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
    flex-shrink: 0;
  }

  .logout-btn:hover {
    background: rgba(224, 85, 85, 0.08);
    border-color: #e05555;
    color: #ff7b7b;
  }

  .main {
    margin-left: 220px;
    flex: 1;
    padding: 32px 36px;
    max-width: 980px;
  }

  .breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12.5px;
    color: var(--muted);
    margin-bottom: 20px;
  }

  .breadcrumb a {
    color: var(--muted);
    text-decoration: none;
    transition: color 0.15s;
  }

  .breadcrumb a:hover { color: var(--text); }

  .page-header { margin-bottom: 28px; }
  .page-title { font-size: 24px; font-weight: 700; letter-spacing: -0.02em; }
  .page-sub { font-size: 13px; color: var(--muted); margin-top: 4px; }

  .alert {
    border-radius: 8px;
    padding: 12px 14px;
    font-size: 13px;
    margin-bottom: 18px;
    line-height: 1.5;
    opacity: 1;
    transform: translateY(0);
    transition: opacity 0.45s ease, transform 0.45s ease;
  }

  .alert.fade-out {
    opacity: 0;
    transform: translateY(-6px);
    pointer-events: none;
  }

  .alert-info {
    background: rgba(79,142,247,0.06);
    border: 1px solid rgba(79,142,247,0.2);
    color: #c9dbff;
  }

  .alert-error {
    background: rgba(224,85,85,0.08);
    border: 1px solid rgba(224,85,85,0.2);
    color: #ffb5b5;
  }

  .form-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
  }

  .form-section {
    padding: 22px 24px;
    border-bottom: 1px solid var(--border);
  }

  .form-section:last-of-type { border-bottom: none; }

  .section-label {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

  .form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .field.full { grid-column: 1 / -1; }

  label {
    font-size: 12.5px;
    font-weight: 500;
    color: var(--muted);
    letter-spacing: 0.02em;
  }

  .required-star { color: var(--danger); margin-left: 3px; }

  input, select {
    width: 100%;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-family: var(--font);
    font-size: 13.5px;
    padding: 10px 14px;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
  }

  input:focus, select:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(79,142,247,0.12);
  }

  input::placeholder { color: var(--muted); }

  select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7080' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
  }

  .helper-text {
    font-size: 11.5px;
    color: var(--muted);
    min-height: 16px;
    line-height: 1.45;
  }

  .role-panel {
    display: none;
    margin-top: 4px;
  }

  .role-panel.visible { display: block; }

  .form-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px;
    background: var(--surface2);
    border-top: 1px solid var(--border);
    gap: 12px;
    flex-wrap: wrap;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 10px 20px;
    border-radius: var(--radius);
    font-family: var(--font);
    font-size: 13.5px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
    text-decoration: none;
  }

  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: #3d7ef5; }
  .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
  .btn-ghost:hover { color: var(--text); border-color: var(--text); }

  @media (max-width: 900px) {
    .form-grid { grid-template-columns: 1fr; }
  }

  @media (max-width: 760px) {
    .sidebar { display: none; }
    .main { margin-left: 0; padding: 24px 18px; }
  }
</style>
</head>
<body>

<nav class="sidebar">
  <div>
    <div class="logo">IRM<span>sys</span></div>
    <div class="nav-label">Admin Panel</div>
    <a class="nav-item" href="admin_dashboard.php">Dashboard</a>
    <a class="nav-item active" href="user_management.php">User Management</a>
    <a class="nav-item" href="internship_list.php">Internship Mgmt</a>
    <a class="nav-item" href="view_results.php">Results</a>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar">AD</div>
      <div class="user-name"><?= h($_SESSION['full_name'] ?? 'Admin User') ?></div>
    </div>
    <a href="logout.php" class="logout-btn">Log out</a>
  </div>
</nav>

<main class="main">
  <div class="breadcrumb">
    <a href="admin_dashboard.php">Dashboard</a>
    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <a href="user_management.php">User Management</a>
    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span>Add User</span>
  </div>

  <div class="page-header">
    <div class="page-title">Add User</div>
    <div class="page-sub">Create a new student or assessor account</div>
  </div>

  <div class="alert alert-info">
    Fill in the required details below. The role-specific section will update based on the selected user type.
  </div>

  <?php if ($error !== ''): ?>
    <div class="alert alert-error" id="formErrorBox"><?= h($error) ?></div>
  <?php else: ?>
    <div class="alert alert-error" id="formErrorBox" style="display:none;"></div>
  <?php endif; ?>

  <form id="addUserForm" action="add_user.php" method="POST" class="form-card">
    <div class="form-section">
      <div class="section-label">Basic Information</div>
      <div class="form-grid">
        <div class="field">
          <label for="full_name">Full Name <span class="required-star">*</span></label>
          <input type="text" id="full_name" name="full_name" placeholder="Enter full name" value="<?= h($full_name) ?>" required>
        </div>

        <div class="field">
          <label for="role">Role <span class="required-star">*</span></label>
          <select id="role" name="role" required onchange="toggleRoleFields()">
            <option value="">Select role</option>
            <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
            <option value="assessor" <?= $role === 'assessor' ? 'selected' : '' ?>>Assessor</option>
          </select>
        </div>

        <div class="field">
          <label for="email">Email <span class="required-star">*</span></label>
          <input type="email" id="email" name="email" placeholder="Enter email address" value="<?= h($email) ?>" required>
        </div>

        <div class="field">
          <label for="status">Status <span class="required-star">*</span></label>
          <select id="status" name="status" required>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>

        <div class="field">
          <label for="password">Password <span class="required-star">*</span></label>
          <input type="password" id="password" name="password" placeholder="Enter password" required>
          <div class="helper-text">Password must be at least 8 characters.</div>
        </div>

        <div class="field">
          <label for="confirm_password">Confirm Password <span class="required-star">*</span></label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
        </div>
      </div>
    </div>

    <div class="form-section">
      <div class="section-label">Role Details</div>

      <div class="role-panel" id="studentFields">
        <div class="form-grid">
          <div class="field">
            <label for="student_id">Student ID <span class="required-star">*</span></label>
            <input type="text" id="student_id" name="student_id" pattern="^S\d{4}$" placeholder="e.g. S0021" value="<?= h($student_id) ?>" oninput="this.value = this.value.toUpperCase()">
            <div class="helper-text">Student login username will be the same as the student ID.</div>
          </div>

          <div class="field">
            <label for="programme">Programme <span class="required-star">*</span></label>
            <select id="programme" name="programme">
              <option value="">Select programme</option>
              <option value="Engineering" <?= $programme === 'Engineering' ? 'selected' : '' ?>>Engineering</option>
              <option value="Arts and Design" <?= $programme === 'Arts and Design' ? 'selected' : '' ?>>Arts and Design</option>
              <option value="Computer Science" <?= $programme === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
              <option value="Finance" <?= $programme === 'Finance' ? 'selected' : '' ?>>Finance</option>
            </select>
          </div>
        </div>
      </div>

      <div class="role-panel" id="assessorFields">
        <div class="form-grid">
          <div class="field">
            <label for="assessor_username">Assessor Username <span class="required-star">*</span></label>
            <input type="text" id="assessor_username" name="assessor_username" pattern="^as_\d{4}$" placeholder="e.g. as_1001" value="<?= h($assessor_username) ?>" oninput="this.value = this.value.toLowerCase()">
            <div class="helper-text">Must follow the format as_XXXX.</div>
          </div>

          <div class="field">
            <label for="assessor_programme">Programme <span class="required-star">*</span></label>
            <select id="assessor_programme" name="assessor_programme">
              <option value="">Select programme</option>
              <option value="Engineering" <?= $assessor_programme === 'Engineering' ? 'selected' : '' ?>>Engineering</option>
              <option value="Arts and Design" <?= $assessor_programme === 'Arts and Design' ? 'selected' : '' ?>>Arts and Design</option>
              <option value="Computer Science" <?= $assessor_programme === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
              <option value="Finance" <?= $assessor_programme === 'Finance' ? 'selected' : '' ?>>Finance</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="form-footer">
      <a href="user_management.php" class="btn btn-ghost">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Cancel
      </a>
      <button type="submit" class="btn btn-primary">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Save User
      </button>
    </div>
  </form>
</main>

<script>
  function toggleRoleFields() {
    const role = document.getElementById("role").value;
    const studentFields = document.getElementById("studentFields");
    const assessorFields = document.getElementById("assessorFields");
    const studentId = document.getElementById("student_id");
    const programme = document.getElementById("programme");
    const assessorUsername = document.getElementById("assessor_username");
    const assessorProgramme = document.getElementById("assessor_programme");

    studentFields.classList.remove("visible");
    assessorFields.classList.remove("visible");

    studentId.required = false;
    programme.required = false;
    assessorUsername.required = false;
    assessorProgramme.required = false;

    if (role === "student") {
      studentFields.classList.add("visible");
      studentId.required = true;
      programme.required = true;
    } else if (role === "assessor") {
      assessorFields.classList.add("visible");
      assessorUsername.required = true;
      assessorProgramme.required = true;
    }
  }

  function showFormError(message) {
    const box = document.getElementById("formErrorBox");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirm_password");

    box.classList.remove("fade-out");
    box.textContent = message;
    box.style.display = "block";
    box.scrollIntoView({ behavior: "smooth", block: "center" });

    if (passwordInput) passwordInput.value = "";
    if (confirmPasswordInput) confirmPasswordInput.value = "";

    clearTimeout(box.hideTimer);
    clearTimeout(box.removeTimer);

    box.hideTimer = setTimeout(() => {
      box.classList.add("fade-out");
      box.removeTimer = setTimeout(() => {
        box.style.display = "none";
        box.classList.remove("fade-out");
        box.textContent = "";
      }, 450);
    }, 1500);
  }

  toggleRoleFields();

  document.getElementById("addUserForm").addEventListener("submit", function(e) {
    const role = document.getElementById("role").value;
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirm_password").value;

    if (password.length < 8) {
      e.preventDefault();
      showFormError("Password must be at least 8 characters.");
      return;
    }

    if (password !== confirmPassword) {
      e.preventDefault();
      showFormError("Passwords do not match.");
      return;
    }

    if (role === "student") {
      const studentId = document.getElementById("student_id").value.trim();
      const programme = document.getElementById("programme").value.trim();

      if (!studentId || !programme) {
        e.preventDefault();
        showFormError("Please complete all student fields.");
        return;
      }

      if (!/^S\d{4}$/.test(studentId)) {
        e.preventDefault();
        showFormError("Student ID must follow the format S0021.");
        return;
      }
    }

    if (role === "assessor") {
      const assessorUsername = document.getElementById("assessor_username").value.trim();
      const assessorProgramme = document.getElementById("assessor_programme").value.trim();

      if (!assessorUsername || !assessorProgramme) {
        e.preventDefault();
        showFormError("Please complete all assessor fields.");
        return;
      }

      if (!/^as_\d{4}$/.test(assessorUsername)) {
        e.preventDefault();
        showFormError("Assessor username must follow the format as_1001.");
        return;
      }
    }
  });
</script>
</body>
</html>