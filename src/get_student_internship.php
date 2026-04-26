<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();

$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : '';

if ($student_id === '') {
    echo json_encode([
        'success' => false,
        'error'   => 'Student ID is missing.'
    ]);
    exit;
}

$sql = "
    SELECT
        i.internship_id,
        s.student_id,
        s.full_name,
        s.programme,
        s.email AS student_email,

        i.company_name,
        i.industry,
        i.start_date,
        i.end_date,
        i.status,
        i.notes,
        i.updated_at,

        l.full_name AS lecturer_name,
        l.email     AS lecturer_email,

        sup.full_name AS supervisor_name,
        sup.email     AS supervisor_email

    FROM students s
    LEFT JOIN internships i
        ON s.student_id = i.student_id
    LEFT JOIN users l
        ON i.lecturer_id = l.user_id
    LEFT JOIN users sup
        ON i.supervisor_id = sup.user_id
    WHERE s.student_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'error'   => 'Prepare failed: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param("s", $student_id);

if (!$stmt->execute()) {
    echo json_encode([
        'success' => false,
        'error'   => 'Execute failed: ' . $stmt->error
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode([
        'success' => false,
        'error'   => 'No internship record found for this student.'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

if (empty($row['status'])) {
    $row['status'] = 'unassigned';
}

echo json_encode([
    'success' => true,
    'data'    => $row
]);

$stmt->close();
$conn->close();
?>