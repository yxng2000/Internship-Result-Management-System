<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();
$assessor_id = (int)($_SESSION['user_id'] ?? 0);

if (!$assessor_id) {
    echo json_encode([]);
    exit;
}

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
$students = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $student = [
            'internship_id' => $row['internship_id'],
            'student_id'    => $row['student_id'],
            'full_name'     => $row['full_name'],
            'company_name'  => $row['company_name'],
            'programme'     => $row['programme'],
            'total_score'   => $row['total_score'],
            'assessment'    => null
        ];
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
}
echo json_encode($students);
$stmt->close();
$conn->close();
?>