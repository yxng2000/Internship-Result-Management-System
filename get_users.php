<?php
// ============================================================
// get_users.php
// Return user list as JSON
// Supports search, role filter, status filter
// ============================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();

// ---------- Get filters ----------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role   = isset($_GET['role']) ? trim($_GET['role']) : 'all';
$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// ---------- Build query ----------
$sql = "SELECT user_id, full_name, email, role, programme, department, status, notes
        FROM users
        WHERE 1=1";

$params = [];
$types = "";

// Search by full_name / user_id / email
if ($search !== '') {
    $sql .= " AND (full_name LIKE ? OR user_id LIKE ? OR email LIKE ?)";
    $searchLike = "%" . $search . "%";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types .= "sss";
}

// Filter by role
if ($role !== 'all') {
    $sql .= " AND role = ?";
    $params[] = $role;
    $types .= "s";
}

// Filter by status
if ($status !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $status;
    $types .= "s";
}

// Order nicely
$sql .= " ORDER BY role ASC, full_name ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error' => 'Prepare failed: ' . $conn->error
    ]);
    exit;
}

// Bind params if any
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$users = [];

while ($row = $result->fetch_assoc()) {
    $users[] = [
        'user_id'    => $row['user_id'],
        'full_name'  => $row['full_name'],
        'email'      => $row['email'],
        'role'       => $row['role'],
        'programme'  => $row['programme'],
        'department' => $row['department'],
        'status'     => $row['status'],
        'notes'      => $row['notes']
    ];
}

// ---------- Summary counts ----------
$totalUsers = 0;
$studentCount = 0;
$assessorCount = 0;
$adminCount = 0;

foreach ($users as $user) {
    $totalUsers++;

    if ($user['role'] === 'student') {
        $studentCount++;
    } elseif ($user['role'] === 'assessor') {
        $assessorCount++;
    } elseif ($user['role'] === 'admin') {
        $adminCount++;
    }
}

echo json_encode([
    'success' => true,
    'summary' => [
        'total_users'    => $totalUsers,
        'students'       => $studentCount,
        'assessors'      => $assessorCount,
        'admins'         => $adminCount
    ],
    'users' => $users
], JSON_PRETTY_PRINT);

$stmt->close();
$conn->close();
?>