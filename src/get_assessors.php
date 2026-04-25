<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();

$programme = isset($_GET['programme']) && $_GET['programme'] !== ''
    ? trim($_GET['programme'])
    : null;

$sql = "
    SELECT
        u.user_id,
        u.full_name,
        u.programme,
        u.status,
        COUNT(i.internship_id) AS student_count
    FROM users u
    LEFT JOIN internships i ON u.user_id = i.lecturer_id
    WHERE u.role = 'lecturer'
";

$params = [];
$types = '';

if ($programme) {
    $sql .= " AND u.programme = ?";
    $params[] = $programme;
    $types .= 's';
}

$sql .= "
    GROUP BY u.user_id, u.full_name, u.programme
    ORDER BY u.full_name ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(json_encode([
        'error' => 'Prepare failed',
        'details' => $conn->error
    ]));
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode($rows);

$stmt->close();
$conn->close();
?>