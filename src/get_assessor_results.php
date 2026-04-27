<?php
session_start();
require_once 'auth.php';
requireRole(['lecturer', 'supervisor']);
require_once 'config.php';
header('Content-Type: application/json');

$user_id   = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? '';

if (!$user_id) { echo json_encode(['success' => false]); exit; }

$id_column = ($user_role === 'lecturer') ? 'i.lecturer_id' : 'i.supervisor_id';
$my_type = ($user_role === 'lecturer') ? 'lecturer' : 'supervisor';
$other_type = ($user_role === 'lecturer') ? 'supervisor' : 'lecturer';
$conn = getConnection();

$sql = "
    SELECT
        s.student_id, s.full_name, s.programme, i.internship_id, i.company_name,
        a.undertaking_tasks, a.health_safety, a.theoretical_knowledge,
        a.report_presentation, a.clarity_language, a.lifelong_learning,
        a.project_management, a.time_management, a.total_score, a.comments,
        other_a.total_score AS other_total_score
    FROM internships i
    JOIN students s ON i.student_id = s.student_id
    LEFT JOIN assessments a ON i.internship_id = a.internship_id AND a.assessor_type = ?
    LEFT JOIN assessments other_a ON i.internship_id = other_a.internship_id AND other_a.assessor_type = ?
    WHERE $id_column = ?
    ORDER BY s.student_id ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param('ssi', $my_type, $other_type, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$records = [];

while ($row = $result->fetch_assoc()) { 
    // SAFELY calculate the final averaged score ONLY if both of you have submitted
    $row['final_score'] = null;
    if ($row['total_score'] !== null && $row['other_total_score'] !== null) {
        $row['final_score'] = round(((float)$row['total_score'] + (float)$row['other_total_score']) / 2, 2);
    }
    $records[] = $row; 
}

echo json_encode(['success' => true, 'records' => $records]);
$stmt->close();
$conn->close();
?>