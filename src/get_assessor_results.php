<?php
    
session_start();
require_once 'auth.php';
requireRole('assessor');
require_once 'config.php';

header('Content-Type: application/json');

$assessor_id = (int)($_SESSION['user_id'] ?? 0);

if (!$assessor_id) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

$conn = getConnection();

$sql = "
    SELECT
        s.student_id,
        s.full_name,
        s.programme,
        i.internship_id,
        i.company_name,
        a.undertaking_tasks,
        a.health_safety,
        a.theoretical_knowledge,
        a.report_presentation,
        a.clarity_language,
        a.lifelong_learning,
        a.project_management,
        a.time_management,
        a.total_score,
        a.comments
    FROM internships i
    JOIN students s ON i.student_id = s.student_id
    LEFT JOIN assessments a ON i.internship_id = a.internship_id
    WHERE i.assessor_id = ?
    ORDER BY s.student_id ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Query prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('i', $assessor_id);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

echo json_encode([
    'success' => true,
    'records' => $records
]);

$stmt->close();
$conn->close();
?>