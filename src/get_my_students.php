<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once 'config.php';

$user_id = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? '';

// Check if user is logged in and is an assessor
if ($user_id === 0 || !in_array($user_role, ['lecturer', 'supervisor'])) {
    echo json_encode([]);
    exit;
}

$assessor_type = ($user_role === 'lecturer') ? 'lecturer' : 'supervisor';
$conn = getConnection();

// Fetch internships assigned to this specific assessor
// Also grab their existing assessment record (if they have already scored this student)
$query = "
    SELECT 
        i.internship_id, i.company_name, i.status AS internship_status,
        i.end_date,
        i.start_date,
        s.student_id, s.full_name, s.programme,
        a.total_score, a.undertaking_tasks, a.health_safety, 
        a.theoretical_knowledge, a.report_presentation, 
        a.clarity_language, a.lifelong_learning, 
        a.project_management, a.time_management, a.comments
    FROM internships i
    JOIN students s ON i.student_id = s.student_id
    LEFT JOIN assessments a ON i.internship_id = a.internship_id AND a.assessor_type = ?
    WHERE " . ($assessor_type === 'lecturer' ? "i.lecturer_id = ?" : "i.supervisor_id = ?") . "
    ORDER BY s.full_name ASC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("si", $assessor_type, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $student = [
        'internship_id' => $row['internship_id'],
        'student_id'    => $row['student_id'],
        'full_name'     => $row['full_name'],
        'programme'     => $row['programme'],
        'company_name'  => $row['company_name'],
        'total_score'   => $row['total_score'],
        'end_date'      => $row['end_date'],
    ];

    // If the assessor has already graded them, bundle the scores to pre-fill the form
    if ($row['total_score'] !== null) {
        $student['assessment'] = [
            'undertaking_tasks'     => $row['undertaking_tasks'],
            'health_safety'         => $row['health_safety'],
            'theoretical_knowledge' => $row['theoretical_knowledge'],
            'report_presentation'   => $row['report_presentation'],
            'clarity_language'      => $row['clarity_language'],
            'lifelong_learning'     => $row['lifelong_learning'],
            'project_management'    => $row['project_management'],
            'time_management'       => $row['time_management'],
            'comments'              => $row['comments']
        ];
    }

    $students[] = $student;
}

$stmt->close();
$conn->close();

echo json_encode($students);
?>