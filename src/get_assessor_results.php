<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();
$assessor_id = 4; // Hardcoded to Dr. Amir (User ID 4)

$query = "
    SELECT 
        i.internship_id, i.student_id, s.full_name, s.programme, 
        i.company_name, a.total_score, a.undertaking_tasks, a.health_safety,
        a.theoretical_knowledge, a.report_presentation, a.clarity_language,
        a.lifelong_learning, a.project_management, a.time_management, a.comments
    FROM internships i
    JOIN students s ON i.student_id = s.student_id
    LEFT JOIN assessments a ON i.internship_id = a.internship_id
    WHERE i.assessor_id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $assessor_id);
$stmt->execute();
$result = $stmt->get_result();

$records = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

echo json_encode(['records' => $records]);
$stmt->close();
$conn->close();
?>