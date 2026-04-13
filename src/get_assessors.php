<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
header('Content-Type: application/json');

$conn = getConnection();

$sql = "
    SELECT 
        u.user_id,
        u.full_name,
        '' AS department,
        COUNT(i.internship_id) AS student_count
    FROM users u
    LEFT JOIN internships i
        ON u.user_id = i.assessor_id
        AND i.status != 'unassigned'
    WHERE u.user_id IN (2, 3, 4)
    GROUP BY u.user_id, u.full_name
    ORDER BY u.user_id
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