<?php
header('Content-Type: application/json');
require_once 'config.php';
session_start();

$conn = getConnection();

$studentId = null;

// temporary testing mode first
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $studentId = trim($_GET['student_id']);
}

// later when login is ready, use session
if (!$studentId && isset($_SESSION['role']) && $_SESSION['role'] === 'student' && !empty($_SESSION['student_id'])) {
    $studentId = $_SESSION['student_id'];
}

if (!$studentId) {
    echo json_encode([
        'success' => false,
        'error' => 'No student selected.'
    ]);
    exit;
}

$sql = "
    SELECT
        s.student_id,
        s.full_name,
        s.programme,
        s.email AS student_email,

        i.internship_id,
        i.company_name,
        i.industry,
        i.start_date,
        i.end_date,
        i.status,
        i.notes,

        u.full_name AS assessor_name,
        u.email AS assessor_email,

        a.total_score,
        a.comments,
        a.submitted_at

    FROM students s
    LEFT JOIN internships i
        ON s.student_id = i.student_id
    LEFT JOIN users u
        ON i.assessor_id = u.user_id
       AND u.role = 'assessor'
    LEFT JOIN assessments a
        ON i.internship_id = a.internship_id
    WHERE s.student_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error' => 'Prepare failed: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode([
        'success' => false,
        'error' => 'Student record not found.'
    ]);
    exit;
}

$assessmentStatus = 'Not Assigned';
if ($row['status'] === 'pending') {
    $assessmentStatus = 'Pending Evaluation';
} elseif ($row['status'] === 'completed') {
    $assessmentStatus = 'Completed';
}

echo json_encode([
    'success' => true,
    'data' => [
        'student_id'        => $row['student_id'],
        'full_name'         => $row['full_name'],
        'programme'         => $row['programme'],
        'student_email'     => $row['student_email'],

        'internship_id'     => $row['internship_id'],
        'company_name'      => $row['company_name'],
        'industry'          => $row['industry'],
        'start_date'        => $row['start_date'],
        'end_date'          => $row['end_date'],
        'status'            => $row['status'] ?? 'unassigned',
        'notes'             => $row['notes'] ?? '',

        'assessor_name'     => $row['assessor_name'],
        'assessor_email'    => $row['assessor_email'],

        'assessment_status' => $assessmentStatus,
        'total_score'       => $row['total_score'],
        'comments'          => $row['comments'],
        'submitted_at'      => $row['submitted_at']
    ]
], JSON_UNESCAPED_UNICODE);