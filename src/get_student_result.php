<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();

// Hardcoded to Ahmad Zulkifli for testing. 
// In the final version, this comes from $_SESSION['student_id']
$student_id = 'S0021'; 

$query = "
    SELECT 
        s.student_id, s.full_name, s.programme, 
        i.company_name, i.status,
        u.full_name AS assessor_name,
        a.total_score, a.undertaking_tasks, a.health_safety,
        a.theoretical_knowledge, a.report_presentation, a.clarity_language,
        a.lifelong_learning, a.project_management, a.time_management, a.comments
    FROM students s
    LEFT JOIN internships i ON s.student_id = i.student_id
    LEFT JOIN users u ON i.assessor_id = u.user_id
    LEFT JOIN assessments a ON i.internship_id = a.internship_id
    WHERE s.student_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student record not found.']);
}

$stmt->close();
$conn->close();
?>