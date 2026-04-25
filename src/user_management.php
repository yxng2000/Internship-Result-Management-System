<?php
session_start();
require_once 'auth.php';
requireRole('admin');
require_once 'config.php';

$conn = getConnection();
$user_id   = (int)($_SESSION['user_id'] ?? 0);
$userName  = $_SESSION['full_name'] ?? 'Admin User';

if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && !empty($row['full_name'])) {
        $userName = $row['full_name'];
        $_SESSION['full_name'] = $row['full_name']; // 顺便把 session 也更新
    }
}

$parts = explode(' ', trim($userName));
$initials = '';

foreach ($parts as $part) {
    if ($part !== '') {
        $initials .= strtoupper($part[0]);
    }
    if (strlen($initials) >= 2) break; 
}

$pageSuccess = '';
$pageError = '';
$editModalError = '';
$openEditModalAfterSubmit = false;
$editModalPostedData = [];
$editModalOriginalData = [];

$initialRoleFilter = strtolower(trim($_GET['role'] ?? 'all'));
$allowedRoles = ['all', 'student', 'lecturer', 'supervisor', 'admin'];
if (!in_array($initialRoleFilter, $allowedRoles, true)) {
    $initialRoleFilter = 'all';
}

if (isset($_SESSION['success'])) {
    $pageSuccess = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $pageError = $_SESSION['error'];
    unset($_SESSION['error']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id <= 0) {
        $pageError = 'Invalid user selected.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, role, username, student_id, password, full_name FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $targetUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$targetUser) {
            $pageError = 'User not found.';
        } elseif (in_array($targetUser['role'], ['admin', 'supervisor'], true)) {
            $pageError = ucfirst($targetUser['role']) . ' account cannot be deleted.';
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
                writeActivityLog(
                    $conn,
                    'delete',
                    'user',
                    $targetUser['user_id'],
                    'User account deleted',
                    $targetUser['full_name'] . ' (' . ucfirst($targetUser['role']) . ') was removed from the system.',
                    'user_management.php'
                );
                $pageSuccess = 'User deleted successfully.';
            } catch (Throwable $e) {
                $conn->rollback();
                $pageError = $e->getMessage() !== '' ? $e->getMessage() : 'Unable to delete user.';
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

    $openEditModalAfterSubmit = true;
    $editModalPostedData = [
        'user_id' => $user_id,
        'full_name' => $full_name,
        'email' => $email,
        'status' => $status,
        'password' => $password,
        'confirm_password' => $confirm_password,
        'programme_student' => trim($_POST['programme_student'] ?? ''),
        'programme_lecturer' => trim($_POST['programme_lecturer'] ?? ''),
        'company_supervisor' => trim($_POST['company_supervisor'] ?? '')
    ];

    if ($user_id <= 0 || $full_name === '' || $email === '' || $status === '') {
        $editModalError = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $editModalError = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, role, username, student_id, password, full_name, email, status, programme, company_name FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $targetUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$targetUser) {
            $editModalError = 'User not found.';
        } else {
            $editModalOriginalData = [
                'user_id' => (int)$targetUser['user_id'],
                'full_name' => $targetUser['full_name'],
                'role' => $targetUser['role'],
                'email' => $targetUser['email'],
                'status' => $targetUser['status'],
                'programme' => $targetUser['programme'] ?? '',
                'username' => $targetUser['username'] ?? '',
                'student_id' => $targetUser['student_id'] ?? '',
                'company_name' => $targetUser['company_name'] ?? ''
            ];

            $role = $targetUser['role'];
            $programme = null;
            $company_name = null;

            if ($role === 'student') {
                $programme = trim($_POST['programme_student'] ?? '');
                if ($programme === '') {
                    $editModalError = 'Please select a programme for the student.';
                }
            } elseif ($role === 'lecturer') {
                $programme = trim($_POST['programme_lecturer'] ?? '');
                if ($programme === '') {
                    $editModalError = 'Please select a programme for the lecturer.';
                }
            } elseif ($role === 'supervisor') {
                $company_name = $targetUser['company_name'] ?? '';
            }

            if ($editModalError === '' && $password !== '') {
                if (strlen($password) < 8) {
                    $editModalError = 'Password must be at least 8 characters.';
                } elseif ($confirm_password === '') {
                    $editModalError = 'Please confirm the new password.';
                } elseif ($password !== $confirm_password) {
                    $editModalError = 'Passwords do not match.';
                } elseif (md5($password) === $targetUser['password']) {
                    $editModalError = 'New password cannot be the same as the current password.';
                }
            }

            if ($editModalError === '') {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1");
                $stmt->bind_param("si", $email, $user_id);
                $stmt->execute();
                $duplicate = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($duplicate) {
                    $editModalError = 'This email is already used by another account.';
                }
            }

            if ($editModalError === '') {
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

                    } elseif ($role === 'lecturer') {
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

                    } elseif ($role === 'supervisor') {
                        if ($password !== '') {
                            $hashedPassword = md5($password);
                            $stmt = $conn->prepare("
                                UPDATE users
                                SET full_name = ?, email = ?, status = ?, company_name = ?, password = ?
                                WHERE user_id = ?
                            ");
                            $stmt->bind_param("sssssi", $full_name, $email, $status, $company_name, $hashedPassword, $user_id);
                        } else {
                            $stmt = $conn->prepare("
                                UPDATE users
                                SET full_name = ?, email = ?, status = ?, company_name = ?
                                WHERE user_id = ?
                            ");
                            $stmt->bind_param("ssssi", $full_name, $email, $status, $company_name, $user_id);
                        }
                        $stmt->execute();
                        $stmt->close();

                    } elseif ($role === 'student') {
                        $student_id = $targetUser['student_id'] ?? '';
                        if ($student_id === '') {
                            throw new Exception('Student record is missing student ID.');
                        }

                        $oldStatus = $targetUser['status'];
                        $newStatus = $status;

                        $stmt = $conn->prepare("
                            SELECT internship_id, status
                            FROM internships
                            WHERE student_id = ?
                            LIMIT 1
                        ");
                        $stmt->bind_param("s", $student_id);
                        $stmt->execute();
                        $internship = $stmt->get_result()->fetch_assoc();
                        $stmt->close();

                        if ($oldStatus === 'active' && $newStatus === 'inactive') {
                            if ($internship && $internship['status'] === 'completed') {
                                throw new Exception('Completed student cannot be deactivated.');
                            }

                            if ($internship && $internship['status'] === 'pending') {
                                $internship_id = (int)$internship['internship_id'];

                                $stmt = $conn->prepare("DELETE FROM assessments WHERE internship_id = ?");
                                $stmt->bind_param("i", $internship_id);
                                $stmt->execute();
                                $stmt->close();

                                $stmt = $conn->prepare("
                                    UPDATE internships
                                    SET lecturer_id = NULL,
                                        supervisor_id = NULL,
                                        company_name = NULL,
                                        industry = NULL,
                                        start_date = NULL,
                                        end_date = NULL,
                                        notes = '',
                                        status = 'unassigned'
                                    WHERE internship_id = ?
                                ");
                                $stmt->bind_param("i", $internship_id);
                                $stmt->execute();
                                $stmt->close();
                            }
                        }

                        if ($oldStatus === 'inactive' && $newStatus === 'active') {
                            if ($internship) {
                                $stmt = $conn->prepare("
                                    UPDATE internships
                                    SET lecturer_id = NULL,
                                        supervisor_id = NULL,
                                        company_name = NULL,
                                        industry = NULL,
                                        start_date = NULL,
                                        end_date = NULL,
                                        notes = '',
                                        status = 'unassigned'
                                    WHERE student_id = ?
                                ");
                                $stmt->bind_param("s", $student_id);
                                $stmt->execute();
                                $stmt->close();
                            } else {
                                $stmt = $conn->prepare("
                                    INSERT INTO internships
                                    (student_id, lecturer_id, supervisor_id, company_name, industry, start_date, end_date, status, notes)
                                    VALUES (?, NULL, NULL, NULL, NULL, NULL, NULL, 'unassigned', '')
                                ");
                                $stmt->bind_param("s", $student_id);
                                $stmt->execute();
                                $stmt->close();
                            }
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
                    writeActivityLog(
                        $conn,
                        'edit',
                        'user',
                        $user_id,
                        'User account updated',
                        $full_name . ' (' . ucfirst($role) . ') account information was updated successfully.',
                        'user_management.php'
                    );
                    $pageSuccess = 'User updated successfully.';
                    $openEditModalAfterSubmit = false;
                    $editModalPostedData = [];
                    $editModalOriginalData = [];
                } catch (Throwable $e) {
                    $conn->rollback();
                    $editModalError = $e->getMessage() !== '' ? $e->getMessage() : 'Failed to update user.';
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
        company_name,
        email,
        student_id,
        status,
        created_at
    FROM users
    ORDER BY
        CASE role
            WHEN 'admin' THEN 1
            WHEN 'supervisor' THEN 2
            WHEN 'lecturer' THEN 3
            WHEN 'student' THEN 4
            ELSE 5
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
    'lecturer' => 0,
    'supervisor' => 0,
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
    if ($role === 'admin' || $role === 'supervisor') {
        return '-';
    }
    return !empty($programme) ? $programme : '-';
}

function safeDepartmentOrCompany($user) {
    if (($user['role'] ?? '') === 'supervisor') {
        return !empty($user['company_name']) ? $user['company_name'] : '-';
    }
    return safeProgramme($user['programme'] ?? '', $user['role'] ?? '');
}

function writeActivityLog($conn, $actionType, $targetType, $targetId, $title, $description, $linkUrl = null)
{
    $actionType = mysqli_real_escape_string($conn, $actionType);
    $targetType = mysqli_real_escape_string($conn, $targetType);
    $title = mysqli_real_escape_string($conn, $title);
    $description = mysqli_real_escape_string($conn, $description);
    $linkUrl = $linkUrl !== null ? mysqli_real_escape_string($conn, $linkUrl) : null;

    $targetIdValue = $targetId === null ? 'NULL' : (int)$targetId;
    $linkValue = $linkUrl === null ? 'NULL' : "'" . $linkUrl . "'";

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Management | IRMSYS</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg: #0e0f13;
      --surface: #16181f;
      --surface2: #1e2029;
      --border: #2a2d38;
      --accent: #4f8ef7;
      --accent2: #7c6af7;
      --text: #e8eaf0;
      --muted: #6b7080;
      --success: #34c97b;
      --warning: #f0a030;
      --danger: #e05555;
      --danger-soft: #ff8fa3;
      --radius: 10px;
      --font: 'Syne', sans-serif;
      --mono: 'DM Mono', monospace;
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
      color: rgba(232, 234, 240, 0.55);
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
      max-width: calc(100% - 220px);
    }

    .page-header {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }

    .page-title { font-size: 24px; font-weight: 700; letter-spacing: -0.02em; }
    .page-sub { font-size: 13px; color: var(--muted); margin-top: 4px; }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 14px;
      border-radius: var(--radius);
      font-family: var(--font);
      font-size: 12.5px;
      font-weight: 600;
      cursor: pointer;
      border: 1px solid var(--border);
      transition: all 0.15s;
      text-decoration: none;
      color: var(--text);
      background: var(--surface2);
    }

    .btn:hover { background: var(--border); }
    .btn-primary { background: rgba(79,142,247,0.10); color: var(--accent); border-color: rgba(79,142,247,0.25); }
    .btn-primary:hover { background: rgba(79,142,247,0.16); }
    .btn-danger { background: rgba(224,85,85,0.10); color: #ff8d8d; border-color: rgba(224,85,85,0.25); }
    .btn-danger:hover { background: rgba(224,85,85,0.16); }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 14px;
      margin-bottom: 18px;
    }

    .stat-card,
    .panel,
    .table-wrap {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
    }

    .stat-card {
      padding: 16px 18px;
      min-height: 108px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }

    .stat-card::after {
      content: "";
      position: absolute;
      inset: auto -20px -20px auto;
      width: 84px;
      height: 84px;
      border-radius: 999px;
      background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, rgba(255,255,255,0) 68%);
      pointer-events: none;
    }

    .stat-label {
      font-size: 11px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 8px;
    }

    .stat-value {
      font-size: 26px;
      font-weight: 700;
      font-family: var(--mono);
    }

    .blue { color: var(--accent); }
    .green { color: var(--success); }
    .amber { color: var(--warning); }
    .purple { color: var(--accent2); }

    .alert {
      border-radius: var(--radius);
      padding: 13px 16px;
      margin-bottom: 14px;
      font-size: 13px;
      border: 1px solid var(--border);
      transition: opacity 0.5s ease, transform 0.5s ease;
    }

    .success-alert { background: rgba(52,201,123,0.10); color: var(--success); }
    .error-alert { background: rgba(224,85,85,0.10); color: #ff9a9a; }
    .alert.fade-out { opacity: 0; transform: translateY(-6px); pointer-events: none; }

    .panel { padding: 18px; margin-bottom: 18px; }

    .panel-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 16px;
      flex-wrap: wrap;
    }

    .panel-header + .filters {
      margin-top: 2px;
    }

    .panel-title { font-size: 20px; font-weight: 700; letter-spacing: -0.02em; }
    .panel-sub { font-size: 12px; color: var(--muted); margin-top: 2px; }

    .directory-note {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 10px;
    }

    .directory-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: var(--surface2);
      color: var(--muted);
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.02em;
    }

    .directory-chip strong {
      color: var(--text);
      font-weight: 700;
    }

    .filters {
      display: grid;
      grid-template-columns: minmax(280px, 1.4fr) 190px 190px 110px;
      gap: 12px;
      align-items: center;
    }

    .search-box,
    .filter-select,
    .btn-secondary {
      background: var(--surface2);
      border: 1px solid var(--border);
      color: var(--text);
      border-radius: var(--radius);
      height: 42px;
      padding: 0 14px;
      font-size: 13px;
      font-family: var(--font);
      outline: none;
    }

    .search-box::placeholder { color: var(--muted); }
    .search-box:focus,
    .filter-select:focus { border-color: rgba(79,142,247,0.45); }

    .btn-secondary { cursor: pointer; min-width: 100px; }

    .table-wrap {
      overflow: hidden;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13.5px;
    }

    thead th {
      padding: 12px 16px;
      text-align: left;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--muted);
      background: var(--surface2);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }

    tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background 0.12s;
    }

    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: var(--surface2); }
    tbody td { padding: 13px 16px; vertical-align: middle; }

    .user-cell-name { font-weight: 600; color: var(--text); }
    .user-id { font-size: 12px; color: var(--muted); margin-top: 4px; }

    .role-badge,
    .status-badge {
      display: inline-block;
      padding: 5px 11px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .role-badge.student { background: rgba(79,142,247,0.12); color: var(--accent); }
    .role-badge.lecturer { background: rgba(240,160,48,0.12); color: var(--warning); }
    .role-badge.supervisor { background: rgba(255,143,163,0.12); color: var(--danger-soft); }
    .role-badge.admin { background: rgba(124,106,247,0.14); color: var(--accent2); }
    .status-badge.active { background: rgba(52,201,123,0.12); color: var(--success); }
    .status-badge.inactive { background: rgba(224,85,85,0.12); color: var(--danger-soft); }

    .actions {
      display: flex;
      gap: 8px;
      flex-wrap: nowrap;
    }

    .icon-btn {
      width: 34px;
      height: 34px;
      border-radius: 9px;
      border: 1px solid var(--border);
      background: var(--surface2);
      color: var(--text);
      cursor: pointer;
      transition: 0.15s;
      font-size: 13px;
    }

    .icon-btn:hover { background: var(--border); }

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

    .modal-overlay.show { display: flex; }

    .modal-card {
      width: 100%;
      max-width: 900px;
      max-height: 88vh;
      overflow-y: auto;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 26px;
      box-shadow: 0 24px 48px rgba(0, 0, 0, 0.35);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 16px;
      margin-bottom: 18px;
    }

    .modal-title { font-size: 26px; font-weight: 700; margin: 0 0 6px; }
    .modal-subtitle { color: var(--muted); font-size: 13px; margin: 0; }

    .close-btn {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: var(--surface2);
      color: var(--text);
      cursor: pointer;
      font-size: 20px;
    }

    .close-btn:hover { background: var(--border); }

    .modal-info-box {
      background: rgba(79,142,247,0.08);
      border: 1px solid rgba(79,142,247,0.18);
      color: #d7ddff;
      border-radius: 12px;
      padding: 14px 16px;
      font-size: 13px;
      margin-bottom: 22px;
      line-height: 1.5;
    }

    .modal-section-title { font-size: 20px; font-weight: 700; margin: 24px 0 16px; }

    .modal-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
    }

    .modal-form-group { display: flex; flex-direction: column; }
    .modal-form-group label { font-size: 13px; font-weight: 600; margin-bottom: 10px; }

    .modal-form-group input,
    .modal-form-group select {
      width: 100%;
      height: 46px;
      border-radius: 10px;
      border: 1px solid var(--border);
      background: var(--surface2);
      color: var(--text);
      padding: 0 14px;
      font-size: 13px;
      outline: none;
      font-family: var(--font);
    }

    .modal-form-group input:focus,
    .modal-form-group select:focus {
      border-color: rgba(79,142,247,0.55);
      box-shadow: 0 0 0 3px rgba(79,142,247,0.10);
    }

    .modal-form-group input[readonly] {
      background: rgba(255,255,255,0.04);
      color: rgba(255,255,255,0.58);
      cursor: not-allowed;
    }

    .modal-hint {
      margin-top: 8px;
      font-size: 11.5px;
      color: var(--muted);
      line-height: 1.4;
    }

    .required { color: #ff9a9a; }

    .change-password-btn {
      background: transparent;
      color: var(--accent);
      border: 1px solid rgba(79,142,247,0.26);
      border-radius: 10px;
      padding: 11px 15px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: var(--font);
    }

    .change-password-btn:hover { background: rgba(79,142,247,0.08); }

    .password-fields {
      display: none;
      margin-top: 16px;
      padding: 18px;
      border-radius: 12px;
      background: rgba(79,142,247,0.05);
      border: 1px solid rgba(79,142,247,0.14);
    }

    .password-fields.show { display: block; }

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
      border-radius: 10px;
      padding: 12px 18px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      font-family: var(--font);
    }

    .modal-cancel-btn {
      background: var(--surface2);
      color: var(--text);
      border: 1px solid var(--border);
    }

    .modal-cancel-btn:hover { background: var(--border); }

    .modal-save-btn {
      background: rgba(79,142,247,0.12);
      color: var(--accent);
      border: 1px solid rgba(79,142,247,0.25);
    }

    .modal-save-btn:hover { background: rgba(79,142,247,0.18); }

    .delete-card {
      margin-bottom: 20px;
      padding: 18px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 12px;
    }

    .table-empty { padding: 24px; color: var(--muted); }

    @media (max-width: 1380px) {
      .stats-row { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }

    @media (max-width: 1200px) {
      .stats-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .filters { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 860px) {
      .sidebar { display: none; }
      .main { margin-left: 0; max-width: 100%; padding: 20px; }
      .page-header { flex-direction: column; align-items: flex-start; }
      .stats-row, .filters, .modal-form-grid { grid-template-columns: 1fr; }
      .table-wrap { overflow-x: auto; }
      table { min-width: 900px; }
    }
  </style>
</head>
<body>

<nav class="sidebar">
  <div>
    <div class="logo">IRM<span>sys</span></div>

    <div class="nav-label">Admin Panel</div>

    <a class="nav-item" href="admin_dashboard.php">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Dashboard
    </a>
    <a class="nav-item active" href="user_management.php">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      User Management
    </a>
    <a class="nav-item" href="internship_list.php">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
      Internship Mgmt
    </a>
    <a class="nav-item" href="view_results.php">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      Results
    </a>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
      <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
    </div>

    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</nav>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">User Management</div>
      <div class="page-sub">Manage student, lecturer, supervisor, and admin accounts</div>
    </div>
    <a class="btn btn-primary" href="add_user.php">+ Add User</a>
  </div>

  <?php if ($pageSuccess !== ''): ?>
    <div class="alert success-alert"><?= htmlspecialchars($pageSuccess) ?></div>
  <?php endif; ?>

  <?php if ($pageError !== ''): ?>
    <div class="alert error-alert"><?= htmlspecialchars($pageError) ?></div>
  <?php endif; ?>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-label">Total Users</div>
      <div class="stat-value blue" id="totalUsers"><?= $counts['total'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Students</div>
      <div class="stat-value green" id="studentCount"><?= $counts['student'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Lecturers</div>
      <div class="stat-value amber" id="lecturerCount"><?= $counts['lecturer'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Supervisors</div>
      <div class="stat-value amber" id="supervisorCount"><?= $counts['supervisor'] ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Admin</div>
      <div class="stat-value purple" id="adminCount"><?= $counts['admin'] ?></div>
    </div>
  </div>

  <section class="panel" style="padding-bottom:14px;">
    <div class="panel-header">
      <div>
        <div class="panel-title">Directory</div>
        <div class="panel-sub">Search, filter, and maintain user accounts</div>
      </div>
    </div>

    <div class="filters">
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
        <option value="lecturer" <?= $initialRoleFilter === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
        <option value="supervisor" <?= $initialRoleFilter === 'supervisor' ? 'selected' : '' ?>>Supervisor</option>
        <option value="admin" <?= $initialRoleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
      </select>

      <select class="filter-select" id="statusFilter" onchange="filterUsers()">
        <option value="all">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>

      <button class="btn-secondary" type="button" onclick="window.print()">Print</button>
    </div>
  </section>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Role</th>
          <th>Programme / Company</th>
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
                  <div class="user-cell-name"><?= htmlspecialchars($user['full_name']) ?></div>
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
                <td><?= htmlspecialchars(safeDepartmentOrCompany($user)) ?></td>
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
                      data-company-name="<?= htmlspecialchars($user['company_name'] ?? '') ?>"
                      data-username="<?= htmlspecialchars($user['username']) ?>"
                      data-student-id="<?= htmlspecialchars($user['student_id'] ?? '') ?>"
                    >✎</button>
                    <?php if (!in_array($user['role'], ['admin', 'supervisor'], true)): ?>
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
              <td colspan="6" class="table-empty">No users found.</td>
            </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

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

      <?php if ($editModalError !== ''): ?>
        <div class="alert error-alert" id="editModalAlert"><?= htmlspecialchars($editModalError) ?></div>
      <?php endif; ?>

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

        <div id="lecturerFields" style="display:none;">
          <div class="modal-section-title">Role Details</div>
          <div class="modal-form-grid">
            <div class="modal-form-group">
              <label for="edit_username">Lecturer Username</label>
              <input type="text" id="edit_username" readonly>
              <div class="modal-hint">Username is locked to avoid login issues.</div>
            </div>

            <div class="modal-form-group">
              <label for="edit_lecturer_programme">Programme <span class="required">*</span></label>
              <select id="edit_lecturer_programme" name="programme_lecturer">
                <option value="">Select Programme</option>
                <option value="Engineering">Engineering</option>
                <option value="Arts and Design">Arts and Design</option>
                <option value="Computer Science">Computer Science</option>
                <option value="Finance">Finance</option>
              </select>
            </div>
          </div>
        </div>

        <div id="supervisorFields" style="display:none;">
          <div class="modal-section-title">Role Details</div>
          <div class="modal-form-grid">
            <div class="modal-form-group">
              <label for="edit_supervisor_username">Supervisor Username</label>
              <input type="text" id="edit_supervisor_username" readonly>
              <div class="modal-hint">Username is locked to avoid login issues.</div>
            </div>

            <div class="modal-form-group">
              <label for="edit_supervisor_company">Company</label>
              <input type="text" id="edit_supervisor_company" name="company_supervisor" placeholder="Company name" readonly>
              <div class="modal-hint">Company cannot be edited here.</div>
            </div>
          </div>
        </div>

        <div id="studentFields" style="display:none;">
          <div class="modal-section-title">Role Details</div>
          <div class="modal-form-grid">
            <div class="modal-form-group">
              <label for="edit_student_id">Student ID</label>
              <input type="text" id="edit_student_id" readonly>
              <div class="modal-hint">Student ID is locked to avoid login issues.</div>
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

      <div class="modal-info-box" style="background: rgba(224,85,85,0.08); border-color: rgba(224,85,85,0.18); color: #ffb3c1;">
        Are you sure you want to delete this user from the system?
      </div>

      <div class="delete-card">
        <div style="font-size: 13px; color: var(--muted); margin-bottom: 8px;">Selected User</div>
        <div style="font-weight: 600; margin-bottom: 6px;" id="delete_user_name">-</div>
        <div style="font-size: 12px; color: var(--muted);" id="delete_user_role">-</div>
      </div>

      <form method="POST">
        <input type="hidden" name="delete_user" value="1">
        <input type="hidden" name="user_id" id="delete_user_id">

        <div class="modal-actions">
          <button type="button" class="modal-cancel-btn" onclick="closeDeleteModal()">Cancel</button>
          <button type="submit" class="btn btn-danger">Delete User</button>
        </div>
      </form>
    </div>
  </div>
</main>
<script>
  const shouldOpenEditModal = <?= $openEditModalAfterSubmit ? 'true' : 'false' ?>;
  const editModalPostedData = <?= json_encode($editModalPostedData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  const editModalOriginalData = <?= json_encode($editModalOriginalData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

  function removeEditModalAlert() {
    const existing = document.getElementById('editModalAlert');
    if (existing) {
      existing.remove();
    }
  }

  function showEditModalInlineAlert(message) {
    const form = document.getElementById('editUserForm');
    const infoBox = form.previousElementSibling && form.previousElementSibling.classList.contains('alert')
      ? form.previousElementSibling.previousElementSibling
      : form.previousElementSibling;
    removeEditModalAlert();

    const alertBox = document.createElement('div');
    alertBox.className = 'alert error-alert';
    alertBox.id = 'editModalAlert';
    alertBox.textContent = message;

    if (infoBox && infoBox.classList.contains('modal-info-box')) {
      infoBox.insertAdjacentElement('afterend', alertBox);
    } else {
      form.insertAdjacentElement('beforebegin', alertBox);
    }

    setTimeout(() => {
      alertBox.classList.add('fade-out');
      setTimeout(() => {
        alertBox.remove();
        restoreEditFormOriginalData();
      }, 500);
    }, 2500);
  }

  function restoreEditFormOriginalData() {
    if (!editModalOriginalData || !editModalOriginalData.user_id) return;

    document.getElementById("edit_user_id").value = editModalOriginalData.user_id || "";
    document.getElementById("edit_full_name").value = editModalOriginalData.full_name || "";
    document.getElementById("edit_role").value = editModalOriginalData.role
      ? editModalOriginalData.role.charAt(0).toUpperCase() + editModalOriginalData.role.slice(1)
      : "";
    document.getElementById("edit_email").value = editModalOriginalData.email || "";
    document.getElementById("edit_status").value = editModalOriginalData.status || "active";

    document.getElementById("edit_password").value = "";
    document.getElementById("edit_confirm_password").value = "";
    document.getElementById("passwordFields").classList.remove("show");

    document.getElementById("lecturerFields").style.display = "none";
    document.getElementById("supervisorFields").style.display = "none";
    document.getElementById("studentFields").style.display = "none";

    if (editModalOriginalData.role === "student") {
      document.getElementById("studentFields").style.display = "block";
      document.getElementById("edit_student_id").value = editModalOriginalData.student_id || "";
      document.getElementById("edit_student_programme").value = editModalOriginalData.programme || "";
    } else if (editModalOriginalData.role === "lecturer") {
      document.getElementById("lecturerFields").style.display = "block";
      document.getElementById("edit_username").value = editModalOriginalData.username || "";
      document.getElementById("edit_lecturer_programme").value = editModalOriginalData.programme || "";
    } else if (editModalOriginalData.role === "supervisor") {
      document.getElementById("supervisorFields").style.display = "block";
      document.getElementById("edit_supervisor_username").value = editModalOriginalData.username || "";
      document.getElementById("edit_supervisor_company").value = editModalOriginalData.company_name || "";
    }
  }

  function fillEditFormFromPostedData() {
    if (!editModalPostedData || !editModalPostedData.user_id) return;

    document.getElementById("edit_user_id").value = editModalPostedData.user_id || "";
    document.getElementById("edit_full_name").value = editModalPostedData.full_name || "";
    document.getElementById("edit_email").value = editModalPostedData.email || "";
    document.getElementById("edit_status").value = editModalPostedData.status || "active";

    document.getElementById("edit_password").value = editModalPostedData.password || "";
    document.getElementById("edit_confirm_password").value = editModalPostedData.confirm_password || "";

    const role = editModalOriginalData.role || "";
    document.getElementById("edit_role").value = role ? role.charAt(0).toUpperCase() + role.slice(1) : "";

    document.getElementById("lecturerFields").style.display = "none";
    document.getElementById("supervisorFields").style.display = "none";
    document.getElementById("studentFields").style.display = "none";

    if (role === "student") {
      document.getElementById("studentFields").style.display = "block";
      document.getElementById("edit_student_id").value = editModalOriginalData.student_id || "";
      document.getElementById("edit_student_programme").value = editModalPostedData.programme_student || "";
    } else if (role === "lecturer") {
      document.getElementById("lecturerFields").style.display = "block";
      document.getElementById("edit_username").value = editModalOriginalData.username || "";
      document.getElementById("edit_lecturer_programme").value = editModalPostedData.programme_lecturer || "";
    } else if (role === "supervisor") {
      document.getElementById("supervisorFields").style.display = "block";
      document.getElementById("edit_supervisor_username").value = editModalOriginalData.username || "";
      document.getElementById("edit_supervisor_company").value = editModalPostedData.company_supervisor || "";
    }

    if ((editModalPostedData.password || '') !== '' || (editModalPostedData.confirm_password || '') !== '') {
      document.getElementById("passwordFields").classList.add("show");
    }
  }

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
    removeEditModalAlert();
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

    document.getElementById("lecturerFields").style.display = "none";
    document.getElementById("supervisorFields").style.display = "none";
    document.getElementById("studentFields").style.display = "none";

    if (role === "student") {
      document.getElementById("studentFields").style.display = "block";
      document.getElementById("edit_student_id").value = button.dataset.studentId || "";
      document.getElementById("edit_student_programme").value = button.dataset.programme || "";
    } else if (role === "lecturer") {
      document.getElementById("lecturerFields").style.display = "block";
      document.getElementById("edit_username").value = button.dataset.username || "";
      document.getElementById("edit_lecturer_programme").value = button.dataset.programme || "";
    } else if (role === "supervisor") {
      document.getElementById("supervisorFields").style.display = "block";
      document.getElementById("edit_supervisor_username").value = button.dataset.username || "";
      document.getElementById("edit_supervisor_company").value = button.dataset.companyName || "";
    }

    modal.classList.add("show");
  }

  function closeEditModal() {
    document.getElementById("editUserModal").classList.remove("show");
    document.getElementById("passwordFields").classList.remove("show");
    removeEditModalAlert();
  }

  function togglePasswordFields() {
    document.getElementById("passwordFields").classList.toggle("show");
  }

  document.getElementById('editUserForm').addEventListener('submit', function (e) {
      const role = document.getElementById("edit_role").value.toLowerCase();
      const status = document.getElementById("edit_status").value;

      if (role === "student" && status === "inactive") {
        const ok = confirm(
          "Warning: If this student has a pending internship record, the record and any submitted assessment will be cleared. Continue?"
        );

        if (!ok) {
          e.preventDefault();
          return;
        }
      }

    const passwordBox = document.getElementById('passwordFields');
    const password = document.getElementById('edit_password').value.trim();
    const confirmPassword = document.getElementById('edit_confirm_password').value.trim();

    if (passwordBox.classList.contains('show')) {
      if (password !== '' && password.length < 8) {
        e.preventDefault();
        showEditModalInlineAlert('Password must be at least 8 characters.');
        return;
      }

      if (password !== '' && confirmPassword === '') {
        e.preventDefault();
        showEditModalInlineAlert('Please confirm the new password.');
        return;
      }

      if (password !== '' && password !== confirmPassword) {
        e.preventDefault();
        showEditModalInlineAlert('Passwords do not match.');
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

    if (shouldOpenEditModal) {
      document.getElementById("editUserModal").classList.add("show");
      fillEditFormFromPostedData();

      const modalAlert = document.getElementById('editModalAlert');
      if (modalAlert) {
        setTimeout(() => {
          modalAlert.classList.add('fade-out');
          setTimeout(() => {
            modalAlert.remove();
            restoreEditFormOriginalData();
          }, 500);
        }, 2500);
      }
    }

    const pageAlerts = document.querySelectorAll('main > .alert');
    if (pageAlerts.length) {
      setTimeout(() => {
        pageAlerts.forEach(alert => {
          alert.classList.add('fade-out');
          setTimeout(() => {
            alert.remove();
          }, 500);
        });
      }, 2500);
    }
  });
</script>
</body>
</html>