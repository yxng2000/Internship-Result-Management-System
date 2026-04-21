<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();
$user_id   = (int)($_SESSION['user_id'] ?? 0);
$user_role = $_SESSION['role'] ?? '';

if (!$user_id || !in_array($user_role, ['lecturer', 'supervisor'])) {
    echo json_encode([]);
    exit;
}

// Lecturer is linked via lecturer_id; supervisor via supervisor_id
$id_column = ($user_role === 'lecturer') ? 'i.lecturer_id' : 'i.supervisor_id';

$query = "
    SELECT
        i.internship_id,
        i.student_id,
        s.full_name,
        s.programme,
        i.company_name,
        a.total_score,
        a.undertaking_tasks,
        a.health_safety,
        a.theoretical_knowledge,
        a.report_presentation,
        a.clarity_language,
        a.lifelong_learning,
        a.project_management,
        a.time_management,
        a.comments
    FROM internships i
    JOIN students s ON i.student_id = s.student_id
    -- Only show THIS assessor's own assessment row (filtered by assessor_type)
    LEFT JOIN assessments a
        ON i.internship_id = a.internship_id
        AND a.assessor_type = ?
    WHERE $id_column = ?
";

$assessor_type = ($user_role === 'lecturer') ? 'lecturer' : 'supervisor';

$stmt = $conn->prepare($query);
$stmt->bind_param("si", $assessor_type, $user_id);
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