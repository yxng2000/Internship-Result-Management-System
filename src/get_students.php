<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
header('Content-Type: application/json');

$conn = getConnection();

$sql = "
    SELECT 
        s.student_id,
        s.full_name,
        s.programme
    FROM students s
    LEFT JOIN internships i ON s.student_id = i.student_id
    WHERE i.status = 'unassigned'
    ORDER BY s.student_id ASC
";

$result = $conn->query($sql);

if (!$result) {
    die(json_encode([
        'success' => false,
        'error' => $conn->error
    ]));
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode($rows);
$conn->close();
?>