<?php
require_once 'auth.php';
requireRole('admin');
require_once 'config.php';

$conn = getConnection();

$success = '';
$error = '';

$initialRoleFilter = strtolower(trim($_GET['role'] ?? 'all'));
$allowedRoles = ['all', 'student', 'assessor', 'admin'];
if (!in_array($initialRoleFilter, $allowedRoles, true)) {
    $initialRoleFilter = 'all';
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        $error = 'Invalid user selected.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, role, username, student_id, password FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $targetUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$targetUser) {
            $error = 'User not found.';
        } else {
            $conn->begin_transaction();

            try {
                if ($targetUser['role'] === 'student') {
                    $student_id = $targetUser['student_id'] ?? '';
                    if ($student_id === '') {
                        throw new Exception('Student record is missing student ID.');
                    }

                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();

                    $stmt = $conn->prepare("DELETE FROM students WHERE student_id = ?");
                    $stmt->bind_param("s", $student_id);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();
                $success = 'User deleted successfully.';
            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e->getMessage() !== '' ? $e->getMessage() : 'Unable to delete user.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if ($user_id <= 0 || $full_name === '' || $email === '' || $status === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, role, username, student_id, password FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $targetUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$targetUser) {
            $error = 'User not found.';
        } else {
            $role = $targetUser['role'];
            $programme = null;

            if ($role === 'student') {
                $programme = trim($_POST['programme_student'] ?? '');
                if ($programme === '') {
                    $error = 'Please select a programme for the student.';
                }
            } elseif ($role === 'assessor') {
                $programme = trim($_POST['programme_assessor'] ?? '');
                if ($programme === '') {
                    $error = 'Please select a programme for the assessor.';
                }
            }

            if ($error === '' && $password !== '') {
              if (strlen($password) < 8) {
                  $error = 'Password must be at least 8 characters.';
              } elseif ($confirm_password === '') {
                  $error = 'Please confirm the new password.';
              } elseif ($password !== $confirm_password) {
                  $error = 'Passwords do not match.';
              } elseif (md5($password) === $targetUser['password']) {
                  $error = 'New password cannot be the same as the current password.';
              }
            }

            if ($error === '') {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
                $stmt->bind_param("si", $email, $user_id);
                $stmt->execute();
                $duplicate = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($duplicate) {
                    $error = 'This email is already used by another account.';
                }
            }

            if ($error === '') {
                $conn->begin_transaction();

                try {
                    if ($role === 'admin') {
                        if ($password !== '') {
                            $hashedPassword = md5($password);
                            $stmt = $conn->prepare("
                                UPDATE users
                                SET full_name = ?, email = ?, status = ?, password = ?
                                WHERE user_id = ?
                            ");
                            $stmt->bind_param("ssssi", $full_name, $email, $status, $hashedPassword, $user_id);
                        } else {
                            $stmt = $conn->prepare("
                                UPDATE users
                                SET full_name = ?, email = ?, status = ?
                                WHERE user_id = ?
                            ");
                            $stmt->bind_param("sssi", $full_name, $email, $status, $user_id);
                        }
                        $stmt->execute();
                        $stmt->close();

                    } elseif ($role === 'assessor') {
                        if ($password !== '') {
                            $hashedPassword = md5($password);
                            $stmt = $conn->prepare("
                                UPDATE users
                                SET full_name = ?, email = ?, status = ?, programme = ?, password = ?
                                WHERE user_id = ?
                            ");
                            $stmt->bind_param("sssssi", $full_name, $email, $status, $programme, $hashedPassword, $user_id);
                        } else {
                            $stmt = $conn->prepare("
                                UPDATE users
                                SET full_name = ?, email = ?, status = ?, programme = ?
                                WHERE user_id = ?
                            ");
                            $stmt->bind_param("ssssi", $full_name, $email, $status, $programme, $user_id);
                        }
                        $stmt->execute();
                        $stmt->close();

                    } elseif ($role === 'student') {
                        $student_id = $targetUser['student_id'] ?? '';
                        if ($student_id === '') {
                            throw new Exception('Student record is missing student ID.');
                        }

                        if ($password !== '') {
                            $hashedPassword = md5($password);
                            $stmt = $conn->prepare("
                                UPDATE users
                                SET full_name = ?, email = ?, status = ?, programme = ?, password = ?
                                WHERE user_id = ?
                            ");
                            $stmt->bind_param("sssssi", $full_name, $email, $status, $programme, $hashedPassword, $user_id);
                        } else {
                            $stmt = $conn->prepare("
                                UPDATE users
                                SET full_name = ?, email = ?, status = ?, programme = ?
                                WHERE user_id = ?
                            ");
                            $stmt->bind_param("ssssi", $full_name, $email, $status, $programme, $user_id);
                        }
                        $stmt->execute();
                        $stmt->close();

                        $stmt = $conn->prepare("
                            UPDATE students
                            SET full_name = ?, email = ?, programme = ?
                            WHERE student_id = ?
                        ");
                        $stmt->bind_param("ssss", $full_name, $email, $programme, $student_id);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $conn->commit();
                    $success = 'User updated successfully.';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = $e->getMessage() !== '' ? $e->getMessage() : 'Failed to update user.';
                }
            }
        }
    }
}

$sql = "
    SELECT
        user_id,
        username,
        full_name,
        role,
        programme,
        email,
        student_id,
        status,
        created_at
    FROM users
    ORDER BY
        CASE role
            WHEN 'admin' THEN 1
            WHEN 'assessor' THEN 2
            WHEN 'student' THEN 3
            ELSE 4
        END,
        full_name ASC
";

$result = $conn->query($sql);
$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$counts = [
    'total' => 0,
    'student' => 0,
    'assessor' => 0,
    'admin' => 0,
];

$countResult = $conn->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {
        $counts['total'] += (int)$row['total'];
        if (isset($counts[$row['role']])) {
            $counts[$row['role']] = (int)$row['total'];
        }
    }
}

$conn->close();

function getInitials($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 2) {
            break;
        }
    }
    return $initials ?: 'AD';
}

function safeProgramme($programme, $role) {
    if ($role === 'admin') {
        return '-';
    }
    return !empty($programme) ? $programme : '-';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Management</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
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
      flex-shrink: 0;
      min-height: 100vh;
      height: 100vh;
      position: sticky;
      top: 0;
      align-self: flex-start;
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
      min-height: 72px;
      visibility: visible;
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
      flex-shrink: 0;
    }

    .admin-info {
      display: flex;
      flex-direction: column;
      justify-content: center;
      min-width: 0;
    }

    .admin-info small {
      display: block;
      color: rgba(255,255,255,0.5);
      font-size: 12px;
    }

    .main {
      flex: 1;
      padding: 36px;
      min-width: 0;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 28px;
      gap: 16px;
      flex-wrap: wrap;
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

    .btn-primary {
      background: linear-gradient(135deg, #4a7dff, #6699ff);
      color: white;
      border: none;
      border-radius: 14px;
      padding: 14px 22px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
      text-decoration: none;
      display: inline-block;
    }

    .btn-primary:hover {
      opacity: 0.92;
      transform: translateY(-1px);
    }

    .summary-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 28px;
    }

    .card {
      background: #121726;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 18px;
      padding: 18px 20px;
    }

    .card-title {
      font-size: 13px;
      color: rgba(255,255,255,0.45);
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-bottom: 12px;
    }

    .card-value {
      font-size: 42px;
      font-weight: 700;
    }

    .blue { color: #66a3ff; }
    .green { color: #25d366; }
    .orange { color: #ffb347; }
    .purple { color: #8a7dff; }

    .alert {
      border-radius: 14px;
      padding: 14px 16px;
      margin-bottom: 18px;
      font-size: 14px;
      border: 1px solid rgba(255,255,255,0.08);
    }

    .success-alert {
      background: rgba(37, 211, 102, 0.10);
      color: #25d366;
    }

    .error-alert {
      background: rgba(255, 107, 129, 0.10);
      color: #ff6b81;
    }

    .alert {
      transition: opacity 0.5s ease, transform 0.5s ease;
    }

    .alert.fade-out {
      opacity: 0;
      transform: translateY(-6px);
      pointer-events: none;
    }

    .filters {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      margin-bottom: 18px;
    }

    .search-box,
    .filter-select,
    .btn-secondary {
      background: #121726;
      border: 1px solid rgba(255,255,255,0.08);
      color: white;
      border-radius: 14px;
      height: 48px;
      padding: 0 16px;
      font-size: 14px;
    }

    .search-box {
      min-width: 320px;
      flex: 1;
    }

    .filter-select {
      min-width: 160px;
      cursor: pointer;
    }

    .btn-secondary {
      cursor: pointer;
      min-width: 110px;
    }

    .table-wrapper {
      background: #121726;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 18px;
      overflow: hidden;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    thead {
      background: rgba(255,255,255,0.02);
    }

    th, td {
      text-align: left;
      padding: 18px 16px;
      font-size: 14px;
      vertical-align: middle;
    }

    th {
      color: rgba(255,255,255,0.5);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      font-size: 12px;
    }

    tbody tr {
      border-top: 1px solid rgba(255,255,255,0.05);
    }

    tbody tr:hover {
      background: rgba(255,255,255,0.02);
    }

    .user-name {
      font-weight: 600;
      color: #ffffff;
    }

    .user-id {
      font-size: 12px;
      color: rgba(255,255,255,0.45);
      margin-top: 4px;
    }

    .role-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
    }

    .student {
      background: rgba(102,163,255,0.12);
      color: #66a3ff;
    }

    .assessor {
      background: rgba(255,179,71,0.14);
      color: #ffb347;
    }

    .admin {
      background: rgba(138,125,255,0.14);
      color: #8a7dff;
    }

    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
    }

    .active {
      background: rgba(37,211,102,0.12);
      color: #25d366;
    }

    .inactive {
      background: rgba(255,99,132,0.12);
      color: #ff6b81;
    }

    .actions {
      display: flex;
      gap: 10px;
    }

    .icon-btn {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      border: 1px solid rgba(255,255,255,0.08);
      background: #161c2d;
      color: white;
      cursor: pointer;
      transition: 0.2s;
      font-size: 14px;
    }

    .icon-btn:hover {
      background: #1c2336;
      transform: translateY(-1px);
    }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(5, 8, 22, 0.72);
      backdrop-filter: blur(6px);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 24px;
      z-index: 999;
    }

    .modal-overlay.show {
      display: flex;
    }

    .modal-card {
      width: 100%;
      max-width: 900px;
      max-height: 88vh;
      overflow-y: auto;
      background: #121726;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 22px;
      padding: 28px;
      box-shadow: 0 24px 48px rgba(0, 0, 0, 0.35);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 16px;
      margin-bottom: 18px;
    }

    .modal-title {
      font-size: 28px;
      font-weight: 700;
      margin: 0 0 6px;
    }

    .modal-subtitle {
      color: rgba(255,255,255,0.58);
      font-size: 14px;
      margin: 0;
    }

    .close-btn {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,0.08);
      background: #161c2d;
      color: white;
      cursor: pointer;
      font-size: 20px;
    }

    .close-btn:hover {
      background: #1c2336;
    }

    .modal-info-box {
      background: rgba(74, 125, 255, 0.08);
      border: 1px solid rgba(74, 125, 255, 0.18);
      color: #d7ddff;
      border-radius: 16px;
      padding: 14px 16px;
      font-size: 13px;
      margin-bottom: 22px;
      line-height: 1.5;
    }

    .modal-section-title {
      font-size: 22px;
      font-weight: 700;
      margin: 24px 0 16px;
    }

    .modal-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .modal-form-group {
      display: flex;
      flex-direction: column;
    }

    .modal-form-group label {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 10px;
    }

    .modal-form-group input,
    .modal-form-group select {
      width: 100%;
      height: 52px;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,0.08);
      background: #0f1422;
      color: white;
      padding: 0 16px;
      font-size: 14px;
      outline: none;
    }

    .modal-form-group input:focus,
    .modal-form-group select:focus {
      border-color: rgba(102,163,255,0.55);
      box-shadow: 0 0 0 3px rgba(102,163,255,0.12);
    }

    .modal-form-group input[readonly] {
      background: rgba(255,255,255,0.04);
      color: rgba(255,255,255,0.58);
      cursor: not-allowed;
    }

    .modal-hint {
      margin-top: 8px;
      font-size: 12px;
      color: rgba(255,255,255,0.45);
      line-height: 1.4;
    }

    .required {
      color: #ff7d9c;
    }

    .change-password-btn {
      background: transparent;
      color: #66a3ff;
      border: 1px solid rgba(102,163,255,0.26);
      border-radius: 12px;
      padding: 12px 16px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
    }

    .change-password-btn:hover {
      background: rgba(102,163,255,0.08);
    }

    .password-fields {
      display: none;
      margin-top: 16px;
      padding: 18px;
      border-radius: 16px;
      background: rgba(74, 125, 255, 0.05);
      border: 1px solid rgba(74, 125, 255, 0.14);
    }

    .password-fields.show {
      display: block;
    }

    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 28px;
      flex-wrap: wrap;
    }

    .modal-cancel-btn,
    .modal-save-btn {
      border: none;
      border-radius: 14px;
      padding: 14px 22px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
    }

    .modal-cancel-btn {
      background: #1a2132;
      color: #d5def7;
      border: 1px solid rgba(255,255,255,0.08);
    }

    .modal-cancel-btn:hover {
      background: #212a3f;
    }

    .modal-save-btn {
      background: linear-gradient(135deg, #4a7dff, #6699ff);
      color: white;
    }

    .modal-save-btn:hover {
      opacity: 0.93;
      transform: translateY(-1px);
    }

    @media (max-width: 1100px) {
      .summary-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .sidebar {
        display: none;
      }

      .main {
        padding: 24px;
      }
    }

    @media (max-width: 700px) {
      .summary-grid {
        grid-template-columns: 1fr;
      }

      .topbar {
        flex-direction: column;
        gap: 16px;
      }

      .filters {
        flex-direction: column;
      }

      .search-box,
      .filter-select,
      .btn-secondary {
        width: 100%;
      }

      table {
        min-width: 900px;
      }

      .table-wrapper {
        overflow-x: auto;
      }

      .modal-card {
        padding: 20px;
        max-height: 92vh;
      }

      .modal-form-grid {
        grid-template-columns: 1fr;
      }

      .modal-actions {
        flex-direction: column;
      }

      .modal-cancel-btn,
      .modal-save-btn,
      .change-password-btn {
        width: 100%;
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
        <a href="internship_list.html" class="nav-item">Internship Mgmt</a>
        <a href="view_results.html" class="nav-item">Results</a>
      </div>
    </div>

    <div class="admin-box">
      <div class="admin-avatar"><?= htmlspecialchars(getInitials($_SESSION['full_name'] ?? 'Admin')) ?></div>
      <div class="admin-info">
        <div><?= htmlspecialchars($_SESSION['full_name'] ?? 'Administrator') ?></div>
        <small>Administrator</small>
      </div>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1>User Management</h1>
        <p>Manage student, assessor, and admin accounts</p>
      </div>
      <a class="btn-primary" href="add_user.php">+ Add User</a>
    </div>

    <?php if ($success !== ''): ?>
      <div class="alert success-alert"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="alert error-alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="summary-grid">
      <div class="card">
        <div class="card-title">Total Users</div>
        <div class="card-value blue" id="totalUsers"><?= $counts['total'] ?></div>
      </div>
      <div class="card">
        <div class="card-title">Students</div>
        <div class="card-value green" id="studentCount"><?= $counts['student'] ?></div>
      </div>
      <div class="card">
        <div class="card-title">Assessors</div>
        <div class="card-value orange" id="assessorCount"><?= $counts['assessor'] ?></div>
      </div>
      <div class="card">
        <div class="card-title">Admins</div>
        <div class="card-value purple" id="adminCount"><?= $counts['admin'] ?></div>
      </div>
    </section>

    <section class="filters">
      <input
        type="text"
        class="search-box"
        id="searchInput"
        placeholder="Search by name, ID, or email..."
        onkeyup="filterUsers()"
      >

      <select class="filter-select" id="roleFilter" onchange="filterUsers()">
        <option value="all" <?= $initialRoleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
        <option value="student" <?= $initialRoleFilter === 'student' ? 'selected' : '' ?>>Student</option>
        <option value="assessor" <?= $initialRoleFilter === 'assessor' ? 'selected' : '' ?>>Assessor</option>
        <option value="admin" <?= $initialRoleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
      </select>

      <select class="filter-select" id="statusFilter" onchange="filterUsers()">
        <option value="all">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>

      <button class="btn-secondary" type="button" onclick="window.print()">Export</button>
    </section>

    <section class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>User</th>
            <th>Role</th>
            <th>Programme / Department</th>
            <th>Email</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="userTableBody">
        <?php if (!empty($users)): ?>
            <?php foreach ($users as $user): ?>
            <tr data-search="<?= htmlspecialchars(strtolower(
                $user['full_name'] . ' ' .
                $user['username'] . ' ' .
                ($user['student_id'] ?? '') . ' ' .
                ($user['programme'] ?? '') . ' ' .
                $user['email'] . ' ' .
                $user['role'] . ' ' .
                $user['status']
            )) ?>">
                <td>
                  <div class="user-name"><?= htmlspecialchars($user['full_name']) ?></div>
                  <div class="user-id">
                    <?= htmlspecialchars($user['username']) ?>
                    <?php if (!empty($user['student_id'])): ?>
                      / <?= htmlspecialchars($user['student_id']) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <span class="role-badge <?= htmlspecialchars($user['role']) ?>">
                    <?= htmlspecialchars(ucfirst($user['role'])) ?>
                  </span>
                </td>
                <td><?= htmlspecialchars(safeProgramme($user['programme'] ?? '', $user['role'])) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td>
                  <span class="status-badge <?= htmlspecialchars($user['status']) ?>">
                    <?= htmlspecialchars(ucfirst($user['status'])) ?>
                  </span>
                </td>
                <td>
                  <div class="actions">
                    <button
                      class="icon-btn"
                      type="button"
                      onclick="openEditModal(this)"
                      data-user-id="<?= (int)$user['user_id'] ?>"
                      data-full-name="<?= htmlspecialchars($user['full_name']) ?>"
                      data-role="<?= htmlspecialchars($user['role']) ?>"
                      data-email="<?= htmlspecialchars($user['email']) ?>"
                      data-status="<?= htmlspecialchars($user['status']) ?>"
                      data-programme="<?= htmlspecialchars($user['programme'] ?? '') ?>"
                      data-username="<?= htmlspecialchars($user['username']) ?>"
                      data-student-id="<?= htmlspecialchars($user['student_id'] ?? '') ?>"
                    >✎</button>
                    <?php if ($user['username'] !== 'admin'): ?>
                      <button
                        class="icon-btn"
                        type="button"
                        onclick="openDeleteModal(this)"
                        data-user-id="<?= (int)$user['user_id'] ?>"
                        data-full-name="<?= htmlspecialchars($user['full_name']) ?>"
                        data-role="<?= htmlspecialchars($user['role']) ?>"
                      >🗑</button>
                    <?php endif; ?>
                  </div>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
              <td colspan="6" style="padding: 24px; color: rgba(255,255,255,0.6);">No users found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="modal-overlay" id="editUserModal">
      <div class="modal-card">
        <div class="modal-header">
          <div>
            <h2 class="modal-title">Edit User</h2>
            <p class="modal-subtitle">Update selected user account details</p>
          </div>
          <button type="button" class="close-btn" onclick="closeEditModal()">×</button>
        </div>

        <div class="modal-info-box">
          You can edit the user information below. Role and identifier fields are locked to keep related records consistent.
        </div>

        <form method="POST" id="editUserForm">
          <input type="hidden" name="edit_user" value="1">
          <input type="hidden" name="user_id" id="edit_user_id">

          <div class="modal-section-title">Basic Information</div>

          <div class="modal-form-grid">
            <div class="modal-form-group">
              <label for="edit_full_name">Full Name <span class="required">*</span></label>
              <input type="text" id="edit_full_name" name="full_name" required>
            </div>

            <div class="modal-form-group">
              <label for="edit_role">Role</label>
              <input type="text" id="edit_role" readonly>
              <div class="modal-hint">Role cannot be changed here.</div>
            </div>

            <div class="modal-form-group">
              <label for="edit_email">Email <span class="required">*</span></label>
              <input type="email" id="edit_email" name="email" required>
            </div>

            <div class="modal-form-group">
              <label for="edit_status">Status <span class="required">*</span></label>
              <select id="edit_status" name="status" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>

          <div id="assessorFields" style="display:none;">
            <div class="modal-section-title">Role Details</div>
            <div class="modal-form-grid">
              <div class="modal-form-group">
                <label for="edit_username">Assessor Username</label>
                <input type="text" id="edit_username" readonly>
                <div class="modal-hint">Username is locked to avoid login issues.</div>
              </div>

              <div class="modal-form-group">
                <label for="edit_assessor_programme">Programme <span class="required">*</span></label>
                <select id="edit_assessor_programme" name="programme_assessor">
                  <option value="">Select Programme</option>
                  <option value="Engineering">Engineering</option>
                  <option value="Arts and Design">Arts and Design</option>
                  <option value="Computer Science">Computer Science</option>
                  <option value="Finance">Finance</option>
                </select>
              </div>
            </div>
          </div>

          <div id="studentFields" style="display:none;">
            <div class="modal-section-title">Role Details</div>
            <div class="modal-form-grid">
              <div class="modal-form-group">
                <label for="edit_student_id">Student ID</label>
                <input type="text" id="edit_student_id" readonly>
                <div class="modal-hint">Student ID is locked because it may be linked to other records.</div>
              </div>

              <div class="modal-form-group">
                <label for="edit_student_programme">Programme <span class="required">*</span></label>
                <select id="edit_student_programme" name="programme_student">
                  <option value="">Select Programme</option>
                  <option value="Engineering">Engineering</option>
                  <option value="Arts and Design">Arts and Design</option>
                  <option value="Computer Science">Computer Science</option>
                  <option value="Finance">Finance</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-section-title">Password</div>

          <button type="button" class="change-password-btn" onclick="togglePasswordFields()">
            Change Password
          </button>

          <div class="password-fields" id="passwordFields">
            <div class="modal-form-grid">
              <div class="modal-form-group">
                <label for="edit_password">New Password</label>
                <input type="password" id="edit_password" name="password" placeholder="Enter new password">
                <div class="modal-hint">Only fill this if you want to change the password.</div>
              </div>

              <div class="modal-form-group">
                <label for="edit_confirm_password">Confirm New Password</label>
                <input type="password" id="edit_confirm_password" name="confirm_password" placeholder="Re-enter new password">
              </div>
            </div>
          </div>

          <div class="modal-actions">
            <button type="button" class="modal-cancel-btn" onclick="closeEditModal()">Cancel</button>
            <button type="submit" class="modal-save-btn">Save Changes</button>
          </div>
        </form>
      </div>
    </div>

    <div class="modal-overlay" id="deleteUserModal">
      <div class="modal-card" style="max-width: 560px;">
        <div class="modal-header">
          <div>
            <h2 class="modal-title">Delete User</h2>
            <p class="modal-subtitle">This action cannot be undone</p>
          </div>
          <button type="button" class="close-btn" onclick="closeDeleteModal()">×</button>
        </div>

        <div class="modal-info-box" style="background: rgba(255, 107, 129, 0.08); border-color: rgba(255, 107, 129, 0.18); color: #ffb3c1;">
          Are you sure you want to delete this user from the system?
        </div>

        <div class="card" style="margin-bottom: 20px; padding: 18px;">
          <div style="font-size: 14px; color: rgba(255,255,255,0.6); margin-bottom: 8px;">Selected User</div>
          <div style="font-weight: 600; margin-bottom: 6px;" id="delete_user_name">-</div>
          <div style="font-size: 13px; color: rgba(255,255,255,0.45);" id="delete_user_role">-</div>
        </div>

        <form method="POST">
          <input type="hidden" name="delete_user" value="1">
          <input type="hidden" name="user_id" id="delete_user_id">

          <div class="modal-actions">
            <button type="button" class="modal-cancel-btn" onclick="closeDeleteModal()">Cancel</button>
            <button type="submit" class="modal-save-btn" style="background: linear-gradient(135deg, #ff5f7a, #ff7b8f);">Delete User</button>
          </div>
        </form>
      </div>
    </div>
  </main>

  <script>
    function filterUsers() {
      const search = document.getElementById("searchInput").value.toLowerCase().trim();
      const role = document.getElementById("roleFilter").value;
      const status = document.getElementById("statusFilter").value;

      const rows = document.querySelectorAll("#userTableBody tr");

      rows.forEach(row => {
        const text = row.dataset.search || "";
        const roleBadge = row.querySelector(".role-badge");
        const statusBadge = row.querySelector(".status-badge");

        if (!roleBadge || !statusBadge) return;

        const roleText = roleBadge.textContent.toLowerCase().trim();
        const statusText = statusBadge.textContent.toLowerCase().trim();

        const matchesSearch = text.includes(search);
        const matchesRole = (role === "all" || roleText === role);
        const matchesStatus = (status === "all" || statusText === status);

        row.style.display = (matchesSearch && matchesRole && matchesStatus) ? "" : "none";
      });
    }

    function openEditModal(button) {
      const modal = document.getElementById("editUserModal");
      const role = button.dataset.role || "";
      const passwordFields = document.getElementById("passwordFields");

      document.getElementById("edit_user_id").value = button.dataset.userId || "";
      document.getElementById("edit_full_name").value = button.dataset.fullName || "";
      document.getElementById("edit_role").value = role ? role.charAt(0).toUpperCase() + role.slice(1) : "";
      document.getElementById("edit_email").value = button.dataset.email || "";
      document.getElementById("edit_status").value = button.dataset.status || "active";

      document.getElementById("edit_password").value = "";
      document.getElementById("edit_confirm_password").value = "";
      passwordFields.classList.remove("show");

      document.getElementById("assessorFields").style.display = "none";
      document.getElementById("studentFields").style.display = "none";

      if (role === "student") {
        document.getElementById("studentFields").style.display = "block";
        document.getElementById("edit_student_id").value = button.dataset.studentId || "";
        document.getElementById("edit_student_programme").value = button.dataset.programme || "";
      } else if (role === "assessor") {
        document.getElementById("assessorFields").style.display = "block";
        document.getElementById("edit_username").value = button.dataset.username || "";
        document.getElementById("edit_assessor_programme").value = button.dataset.programme || "";
      }

      modal.classList.add("show");
    }

    function closeEditModal() {
      document.getElementById("editUserModal").classList.remove("show");
      document.getElementById("passwordFields").classList.remove("show");
    }

    function togglePasswordFields() {
      document.getElementById("passwordFields").classList.toggle("show");
    }

    document.getElementById('editUserForm').addEventListener('submit', function (e) {
      const passwordBox = document.getElementById('passwordFields');
      const password = document.getElementById('edit_password').value.trim();
      const confirmPassword = document.getElementById('edit_confirm_password').value.trim();

      if (passwordBox.classList.contains('show')) {
        if (password !== '' && password.length < 8) {
          e.preventDefault();
          alert('Password must be at least 8 characters.');
          return;
        }

        if (password !== '' && confirmPassword === '') {
          e.preventDefault();
          alert('Please confirm the new password.');
          return;
        }

        if (password !== '' && password !== confirmPassword) {
          e.preventDefault();
          alert('Passwords do not match.');
          return;
        }
      }
    });

    function openDeleteModal(button) {
      const role = button.dataset.role || '';
      document.getElementById('delete_user_id').value = button.dataset.userId || '';
      document.getElementById('delete_user_name').textContent = button.dataset.fullName || '-';
      document.getElementById('delete_user_role').textContent = role ? role.charAt(0).toUpperCase() + role.slice(1) : '-';
      document.getElementById('deleteUserModal').classList.add('show');
    }

    function closeDeleteModal() {
      document.getElementById('deleteUserModal').classList.remove('show');
    }

    window.addEventListener("click", function (e) {
      const editModal = document.getElementById("editUserModal");
      const deleteModal = document.getElementById("deleteUserModal");
      if (e.target === editModal) {
        closeEditModal();
      }
      if (e.target === deleteModal) {
        closeDeleteModal();
      }
    });


    window.addEventListener("DOMContentLoaded", function () {
      filterUsers();
    });

    setTimeout(() => {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        alert.classList.add('fade-out');
        setTimeout(() => {
          alert.remove();
        }, 500);
      });
    }, 2500);
</script>
</body>
</html>