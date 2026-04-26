<?php
// ============================================================
// delete_internship.php
// Reset internship to unassigned + write recent activity
// ============================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$internship_id = isset($_POST['internship_id']) ? (int)$_POST['internship_id'] : 0;

if ($internship_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'No internship ID provided.']);
    exit;
}

$conn = getConnection();

/* Get internship info before reset */
$stmt = $conn->prepare("
    SELECT 
        i.internship_id,
        s.student_id,
        s.full_name,
        i.company_name
    FROM internships i
    JOIN students s ON i.student_id = s.student_id
    WHERE i.internship_id = ?
");
$stmt->bind_param("i", $internship_id);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$record) {
    echo json_encode(['success' => false, 'error' => 'Internship record not found.']);
    $conn->close();
    exit;
}

/* Reset internship */
$stmt = $conn->prepare("
    UPDATE internships
    SET
        lecturer_id = NULL,
        supervisor_id = NULL,
        company_name = NULL,
        industry = NULL,
        start_date = NULL,
        end_date = NULL,
        status = 'unassigned',
        notes = '',
        updated_at = NOW()
    WHERE internship_id = ?
");
$stmt->bind_param("i", $internship_id);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'error' => 'Reset failed: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

/* Recent activity */
$action_type = 'reset';
$target_type = 'internship';
$target_id = $internship_id;
$title = 'Internship reset';
$description = $record['full_name'] . ' (' . $record['student_id'] . ') was reset to unassigned.';
$link_url = 'assign_student.php?student_id=' . urlencode($record['student_id']);

$stmt = $conn->prepare("
    INSERT INTO activity_logs
        (action_type, target_type, target_id, title, description, link_url)
    VALUES
        (?, ?, ?, ?, ?, ?)
");

$activity_saved = true;
$activity_error = '';

if ($stmt) {
    $stmt->bind_param(
        "ssisss",
        $action_type,
        $target_type,
        $target_id,
        $title,
        $description,
        $link_url
    );

    if (!$stmt->execute()) {
        $activity_saved = false;
        $activity_error = $stmt->error;
    }

    $stmt->close();
} else {
    $activity_saved = false;
    $activity_error = $conn->error;
}

echo json_encode([
    'success' => true,
    'message' => 'Internship record reset successfully.',
    'activity_saved' => $activity_saved,
    'activity_error' => $activity_error
]);

$conn->close();
?>