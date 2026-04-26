<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request method.');
}

$conn = getConnection();

$admin_id      = $_SESSION['user_id'] ?? null;

$student_id    = $_POST['student_id'] ?? '';
$lecturer_id   = isset($_POST['lecturer']) ? (int)$_POST['lecturer'] : 0;
$supervisor_id = isset($_POST['supervisor']) ? (int)$_POST['supervisor'] : 0;
$company_name  = trim($_POST['company'] ?? '');
$industry      = trim($_POST['industry'] ?? '');
$industry_other = trim($_POST['industry_other'] ?? '');
$start_date    = $_POST['start_date'] ?? '';
$end_date      = $_POST['end_date'] ?? '';
$notes         = trim($_POST['notes'] ?? '');

if ($industry === 'Other' && $industry_other !== '') {
    $industry = $industry_other;
}

$errors = [];

if ($student_id === '') $errors[] = 'Please select a student.';
if ($lecturer_id <= 0) $errors[] = 'Please select a lecturer.';
if ($supervisor_id <= 0) $errors[] = 'Please select a supervisor.';
if ($company_name === '') $errors[] = 'Please enter company name.';
if ($industry === '') $errors[] = 'Please select industry.';
if ($start_date === '') $errors[] = 'Please select start date.';
if ($end_date === '') $errors[] = 'Please select end date.';

if ($start_date !== '' && $end_date !== '' && $end_date <= $start_date) {
    $errors[] = 'End date must be after start date.';
}

if (!empty($errors)) {
    $conn->close();
    exit(implode('<br>', $errors));
}

/* Validate lecturer */
$stmt = $conn->prepare("
    SELECT full_name 
    FROM users 
    WHERE user_id = ? AND role = 'lecturer' AND status = 'active'
");
$stmt->bind_param('i', $lecturer_id);
$stmt->execute();
$lecturer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lecturer) {
    $conn->close();
    exit('Invalid lecturer selected.');
}

/* Validate supervisor */
$stmt = $conn->prepare("
    SELECT full_name 
    FROM users 
    WHERE user_id = ? AND role = 'supervisor' AND status = 'active'
");
$stmt->bind_param('i', $supervisor_id);
$stmt->execute();
$supervisor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supervisor) {
    $conn->close();
    exit('Invalid supervisor selected.');
}

/* Get student name */
$stmt = $conn->prepare("
    SELECT full_name 
    FROM students 
    WHERE student_id = ?
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    $conn->close();
    exit('Invalid student selected.');
}

/* Assign internship */
$stmt = $conn->prepare("
    UPDATE internships
    SET lecturer_id = ?,
        supervisor_id = ?,
        company_name = ?,
        industry = ?,
        start_date = ?,
        end_date = ?,
        status = 'pending',
        notes = ?,
        updated_at = NOW()
    WHERE student_id = ?
      AND (
            status = 'unassigned'
            OR lecturer_id IS NULL
            OR supervisor_id IS NULL
            OR company_name IS NULL
            OR company_name = ''
      )
");

$stmt->bind_param(
    'iissssss',
    $lecturer_id,
    $supervisor_id,
    $company_name,
    $industry,
    $start_date,
    $end_date,
    $notes,
    $student_id
);

if (!$stmt->execute()) {
    $conn->close();
    exit('Assign failed: ' . $stmt->error);
}

if ($stmt->affected_rows <= 0) {
    $stmt->close();
    $conn->close();
    exit('Assign failed. Student may already be assigned.');
}

$stmt->close();

/* Get internship_id after update */
$stmt = $conn->prepare("
    SELECT internship_id 
    FROM internships 
    WHERE student_id = ?
");
$stmt->bind_param('s', $student_id);
$stmt->execute();
$internship = $stmt->get_result()->fetch_assoc();
$stmt->close();

$internship_id = $internship['internship_id'] ?? null;

/* Write activity log */
$title = 'Student assigned for internship';

$description = $student['full_name'] . ' was assigned to ' .
               $company_name . '. Lecturer: ' .
               $lecturer['full_name'] . ', Supervisor: ' .
               $supervisor['full_name'] . '.';

$link_url = 'edit_internship.php?id=' . $internship_id;

$stmt = $conn->prepare("
    INSERT INTO activity_logs 
    (action_type, target_type, target_id, title, description, link_url)
    VALUES (?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    $conn->close();
    exit('Activity log prepare failed: ' . $conn->error);
}

$action_type = 'assign';
$target_type = 'internship';

$stmt->bind_param(
    'ssisss',
    $action_type,
    $target_type,
    $internship_id,
    $title,
    $description,
    $link_url
);

$stmt->execute();
$stmt->close();

$conn->close();

header('Location: internship_list.php?success=assigned');
exit;
?>