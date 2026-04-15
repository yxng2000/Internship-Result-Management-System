<?php
// ============================================================
// add_user.php
// Handle add user form submission
// Compatible with config.php -> getConnection()
// ============================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

$conn = getConnection();

// ---------- Helper function ----------
function clean($value) {
    return trim($value ?? '');
}

// ---------- Get common fields ----------
$full_name        = clean($_POST['full_name']);
$role             = clean($_POST['role']);
$email            = clean($_POST['email']);
$status           = clean($_POST['status']);
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// ---------- Basic validation ----------
if (
    empty($full_name) ||
    empty($role) ||
    empty($email) ||
    empty($status) ||
    empty($password) ||
    empty($confirm_password)
) {
    die("Please fill in all required basic fields.");
}

if ($password !== $confirm_password) {
    die("Passwords do not match.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email format.");
}

$allowed_roles = ['student', 'assessor', 'admin'];
if (!in_array($role, $allowed_roles)) {
    die("Invalid role selected.");
}

$allowed_status = ['active', 'inactive'];
if (!in_array($status, $allowed_status)) {
    die("Invalid status selected.");
}

// ---------- Role-based mapping ----------
$user_id    = null;
$programme  = null;
$department = null;
$notes      = null;

if ($role === 'student') {
    $user_id   = clean($_POST['student_id']);
    $programme = clean($_POST['programme']);
    $notes     = clean($_POST['student_notes']);

    if (empty($user_id) || empty($programme)) {
        die("Student ID and Programme are required for student.");
    }
}

if ($role === 'assessor') {
    $user_id    = clean($_POST['staff_id']);
    $department = clean($_POST['department']);
    $notes      = clean($_POST['assessor_notes']);

    if (empty($user_id) || empty($department)) {
        die("Staff ID and Department are required for assessor.");
    }
}

if ($role === 'admin') {
    $user_id    = clean($_POST['admin_id']);
    $department = clean($_POST['admin_department']);
    $notes      = clean($_POST['admin_notes']);

    if (empty($user_id) || empty($department)) {
        die("Admin ID and Department are required for admin.");
    }
}

// ---------- Extra validation ----------
if (empty($user_id)) {
    die("User ID could not be determined.");
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// ---------- Check duplicate user_id or email ----------
$checkSql = "SELECT user_id, email FROM users WHERE user_id = ? OR email = ?";
$checkStmt = $conn->prepare($checkSql);

if (!$checkStmt) {
    die("Prepare failed: " . $conn->error);
}

$checkStmt->bind_param("ss", $user_id, $email);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    $existing = $checkResult->fetch_assoc();

    if ($existing['user_id'] === $user_id) {
        die("User ID already exists.");
    }

    if ($existing['email'] === $email) {
        die("Email already exists.");
    }
}

$checkStmt->close();

// ---------- Insert user ----------
$sql = "INSERT INTO users (
            user_id,
            full_name,
            email,
            password,
            role,
            programme,
            department,
            status,
            notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param(
    "sssssssss",
    $user_id,
    $full_name,
    $email,
    $hashed_password,
    $role,
    $programme,
    $department,
    $status,
    $notes
);

if ($stmt->execute()) {
    echo "
    <script>
        alert('User added successfully.');
        window.location.href = 'user_management.html';
    </script>
    ";
} else {
    echo "Error inserting user: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>