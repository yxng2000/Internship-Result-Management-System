<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request method.');
}

// 1. Read form data
$student_id   = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
$assessor_id  = isset($_POST['assessor']) ? (int)$_POST['assessor'] : 0;
$company_name = isset($_POST['company']) ? trim($_POST['company']) : '';
$industry     = isset($_POST['industry']) ? trim($_POST['industry']) : '';
$start_date   = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
$end_date     = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
$notes        = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// 2. Validation
$errors = [];

if ($student_id === '') $errors[] = 'Student ID is required.';
if ($assessor_id <= 0) $errors[] = 'Please select an assessor.';
if ($company_name === '') $errors[] = 'Company name is required.';

function convertDate($d) {
    $parts = explode('/', $d);
    if (count($parts) !== 3) return false;
    return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
}

$start_mysql = convertDate($start_date);
$end_mysql   = convertDate($end_date);

if (!$start_mysql) $errors[] = 'Invalid start date.';
if (!$end_mysql) $errors[] = 'Invalid end date.';
if ($start_mysql && $end_mysql && $end_mysql <= $start_mysql) {
    $errors[] = 'End date must be after start date.';
}

if (!empty($errors)) {
    exit(implode('<br>', $errors));
}

// 3. Connect DB
$conn = getConnection();

if (!$conn) {
    exit('Database connection failed.');
}

// 4. Check student exists
$check = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
if (!$check) {
    exit("Check prepare failed: " . $conn->error);
}
$check->bind_param('s', $student_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    exit('Student not found.');
}
$check->close();

// 5. Check if student already has active internship
$active = $conn->prepare("
    SELECT internship_id
    FROM internships
    WHERE student_id = ? AND status != 'unassigned'
");
if (!$active) {
    exit("Active check failed: " . $conn->error);
}
$active->bind_param('s', $student_id);
$active->execute();
$active->store_result();

if ($active->num_rows > 0) {
    $active->close();
    exit;
}
$active->close();

// 6. Update the existing unassigned record
$stmt = $conn->prepare("
    UPDATE internships
    SET assessor_id = ?,
        company_name = ?,
        industry = ?,
        start_date = ?,
        end_date = ?,
        status = 'assigned',
        notes = ?
    WHERE student_id = ? AND status = 'unassigned'
");

if (!$stmt) {
    exit("Update prepare failed: " . $conn->error);
}

$stmt->bind_param(
    'issssss',
    $assessor_id,
    $company_name,
    $industry,
    $start_mysql,
    $end_mysql,
    $notes,
    $student_id
);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        header("Location: internship_list.html");
        exit;
    } else {
        exit('No unassigned internship record found for this student.');
    }
} else {
    exit("Database error: " . $stmt->error);
}

$stmt->close();
$conn->close();
?>