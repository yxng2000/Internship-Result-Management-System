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
                $stmt->close();
            }

            $conn->commit();
            header("Location: user_management.php?success=User added successfully");
            exit();
        } catch (Exception $e) {
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Add User | IRMSYS</title>
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
      display: flex;
      min-height: 100vh;
    }

    .sidebar {
      width: 240px;
      background: #111523;
      border-right: 1px solid rgba(255,255,255,0.06);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 24px 0;
    }

    .logo {
      font-size: 18px;
      font-weight: 700;
      color: #66a3ff;
      padding: 0 24px 24px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
    }

    .nav-section {
      padding-top: 18px;
    }

    .nav-title {
      font-size: 11px;
      letter-spacing: 2px;
      color: rgba(255,255,255,0.35);
      padding: 0 24px 14px;
      text-transform: uppercase;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      color: rgba(255,255,255,0.75);
      text-decoration: none;
      padding: 14px 24px;
      transition: 0.2s;
    }

    .nav-item:hover,
    .nav-item.active {
      background: rgba(74, 125, 255, 0.12);
      color: #66a3ff;
      border-left: 3px solid #4a7dff;
      padding-left: 21px;
    }

    .admin-box {
      border-top: 1px solid rgba(255,255,255,0.06);
      padding: 20px 24px 0;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .admin-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #738bff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
    }

    .admin-info small {
      display: block;
      color: rgba(255,255,255,0.5);
      font-size: 12px;
    }

    .main {
      flex: 1;
      padding: 36px;
    }

    .topbar {
      margin-bottom: 28px;
    }

    .topbar h1 {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .topbar p {
      color: rgba(255,255,255,0.6);
      font-size: 14px;
    }

    .form-wrapper {
      max-width: 900px;
      background: #121726;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 20px;
      padding: 28px;
    }

    .section-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 18px;
      color: #ffffff;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 18px;
      margin-bottom: 22px;
    }

    .full-width {
      grid-column: 1 / -1;
    }

    .field-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    label {
      font-size: 13px;
      color: rgba(255,255,255,0.72);
      font-weight: 500;
    }

    .required {
      color: #ff7b7b;
    }

    input,
    select {
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

    input:focus,
    select:focus {
      border-color: #4a7dff;
      box-shadow: 0 0 0 3px rgba(74,125,255,0.12);
    }

    .student-fields,
    .assessor-fields {
      margin-top: 8px;
      margin-bottom: 8px;
    }

    .note-box {
      background: rgba(102,163,255,0.08);
      border: 1px solid rgba(102,163,255,0.18);
      color: #b9d2ff;
      border-radius: 14px;
      padding: 14px 16px;
      font-size: 13px;
      margin-bottom: 22px;
      line-height: 1.5;
    }

    .error-box {
      background: rgba(255,123,123,0.08);
      border: 1px solid rgba(255,123,123,0.2);
      color: #ffb0b0;
      border-radius: 14px;
      padding: 14px 16px;
      font-size: 13px;
      margin-bottom: 22px;
      line-height: 1.5;
    }

    .button-row {
      display: flex;
      gap: 14px;
      margin-top: 28px;
      flex-wrap: wrap;
    }

    .btn-primary,
    .btn-secondary {
      border: none;
      border-radius: 14px;
      padding: 14px 22px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
      text-decoration: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, #4a7dff, #6699ff);
      color: white;
    }

    .btn-primary:hover {
      opacity: 0.93;
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: #1a2132;
      color: #d5def7;
      border: 1px solid rgba(255,255,255,0.08);
    }

    .btn-secondary:hover {
      background: #212a3f;
    }

    .helper-text {
      font-size: 12px;
      color: rgba(255,255,255,0.45);
      margin-top: 4px;
    }

    @media (max-width: 1000px) {
      .sidebar {
        display: none;
      }

      .main {
        padding: 24px;
      }
    }

    @media (max-width: 700px) {
      .form-grid {
        grid-template-columns: 1fr;
      }

      .form-wrapper {
        padding: 20px;
      }
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
      <div class="admin-avatar"><?= h(getInitials($_SESSION['full_name'])) ?></div>
      <div class="admin-info">
        <div><?= h($_SESSION['full_name']) ?></div>
        <small>Administrator</small>
      </div>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <h1>Add User</h1>
      <p>Create a new student or assessor account</p>
    </div>

    <div class="form-wrapper">
      <div class="note-box">
        Fill in the required details below. The fields shown will change based on the selected user role.
      </div>

      <?php if ($error !== ''): ?>
        <div class="error-box" id="formErrorBox"><?= h($error) ?></div>
      <?php else: ?>
        <div class="error-box" id="formErrorBox" style="display:none;"></div>
      <?php endif; ?>

      <form id="addUserForm" action="add_user.php" method="POST">
        <div class="section-title">Basic Information</div>

        <div class="form-grid">
          <div class="field-group">
            <label for="full_name">Full Name <span class="required">*</span></label>
            <input type="text" id="full_name" name="full_name" placeholder="Enter full name" value="<?= h($full_name) ?>" required>
          </div>

          <div class="field-group">
            <label for="role">Role <span class="required">*</span></label>
            <select id="role" name="role" required onchange="toggleRoleFields()">
              <option value="">Select role</option>
              <option value="student" <?= $role === 'student' ? 'selected' : '' ?>>Student</option>
              <option value="assessor" <?= $role === 'assessor' ? 'selected' : '' ?>>Assessor</option>
            </select>
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
            <label for="password">Password <span class="required">*</span></label>
            <input type="password" id="password" name="password" placeholder="Enter password" required>
            <div class="helper-text">Password must be at least 8 characters.</div>
          </div>

          <div class="field-group">
            <label for="confirm_password">Confirm Password <span class="required">*</span></label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
          </div>
        </div>

        <div class="section-title">Role Details</div>

        <div class="student-fields" id="studentFields" style="display:none;">
          <div class="form-grid">
            <div class="field-group">
              <label for="student_id">Student ID <span class="required">*</span></label>
              <input type="text" id="student_id" name="student_id" pattern="^S\d{4}$" placeholder="e.g. S0021" value="<?= h($student_id) ?>" oninput="this.value = this.value.toUpperCase()">
              <div class="helper-text">Student login username will be the same as the student ID.</div>
            </div>

            <div class="field-group">
              <label for="programme">Programme <span class="required">*</span></label>
              <select id="programme" name="programme">
                <option value="Engineering" <?= $programme === 'Engineering' ? 'selected' : '' ?>>Engineering</option>
                <option value="Arts and Design" <?= $programme === 'Arts and Design' ? 'selected' : '' ?>>Arts and Design</option>
                <option value="Computer Science" <?= $programme === 'Computer Science' ? 'selected' : '' ?>>Computer Science</option>
                <option value="Finance" <?= $programme === 'Finance' ? 'selected' : '' ?>>Finance</option>
              </select>
            </div>
          </div>
        </div>

        <div class="assessor-fields" id="assessorFields" style="display:none;">
          <div class="form-grid">
            <div class="field-group">
              <label for="assessor_username">Assessor Username <span class="required">*</span></label>
              <input type="text" id="assessor_username" name="assessor_username" pattern="^as_\d{4}$" placeholder="e.g. as_1001" value="<?= h($assessor_username) ?>" oninput="this.value = this.value.toLowerCase()">
              <div class="helper-text">Must follow the format as_XXXX.</div>
            </div>

            <div class="field-group">
              <label for="assessor_programme">Programme <span class="required">*</span></label>
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

        <div class="button-row">
          <button type="submit" class="btn-primary">Save User</button>
          <a href="user_management.php" class="btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
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

      studentFields.style.display = "none";
      assessorFields.style.display = "none";

      studentId.required = false;
      programme.required = false;
      assessorUsername.required = false;
      assessorProgramme.required = false;

      if (role === "student") {
        studentFields.style.display = "block";
        studentId.required = true;
        programme.required = true;
      } else if (role === "assessor") {
        assessorFields.style.display = "block";
        assessorUsername.required = true;
        assessorProgramme.required = true;
      }
    }

    function showFormError(message) {
      const box = document.getElementById("formErrorBox");
      box.textContent = message;
      box.style.display = "block";
      box.scrollIntoView({ behavior: "smooth", block: "center" });
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