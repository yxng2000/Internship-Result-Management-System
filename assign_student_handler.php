<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request method.');
}

// 1. Read form data
$student_id      = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
$lecturer_id     = isset($_POST['lecturer']) ? (int)$_POST['lecturer'] : 0;
$company_name    = isset($_POST['company']) ? trim($_POST['company']) : '';
$supervisor_id   = isset($_POST['supervisor']) ? (int)$_POST['supervisor'] : 0;
$industry        = isset($_POST['industry']) ? trim($_POST['industry']) : '';
$industry_other  = isset($_POST['industry_other']) ? trim($_POST['industry_other']) : '';
$start_date      = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
$end_date        = isset($_POST['end_date']) ? trim($_POST['end_date']) : '';
$notes           = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// 2. Handle "Other" industry
if ($industry === 'Other' && $industry_other !== '') {
    $industry = $industry_other;
}

// 3. Validation
$errors = [];

if ($student_id === '') $errors[] = 'Student ID is required.';
if ($lecturer_id <= 0) $errors[] = 'Please select a lecturer.';
if ($company_name === '') $errors[] = 'Company name is required.';
if ($supervisor_id <= 0) $errors[] = 'Supervisor is required.';

function isValidMysqlDate($date) {
    if (!$date) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

if (!isValidMysqlDate($start_date)) $errors[] = 'Invalid start date.';
if (!isValidMysqlDate($end_date)) $errors[] = 'Invalid end date.';
if (isValidMysqlDate($start_date) && isValidMysqlDate($end_date) && $end_date <= $start_date) {
    $errors[] = 'End date must be after start date.';
}

if (!empty($errors)) {
    exit(implode('<br>', $errors));
}

// 4. Connect DB
$conn = getConnection();
if (!$conn) {
    exit('Database connection failed.');
}

// 5. Check student exists
$check = $conn->prepare("SELECT student_id FROM students WHERE student_id = ?");
if (!$check) {
    $conn->close();
    exit("Check prepare failed: " . $conn->error);
}
$check->bind_param('s', $student_id);
$check->execute();
$check->store_result();

if ($check->num_rows === 0) {
    $check->close();
    $conn->close();
    exit('Student not found.');
}
$check->close();

// 6. Check lecturer is valid lecturer
$lecturerCheck = $conn->prepare("
    SELECT user_id
    FROM users
    WHERE user_id = ?
      AND role = 'lecturer'
      AND status = 'active'
");
if (!$lecturerCheck) {
    $conn->close();
    exit("Lecturer check failed: " . $conn->error);
}
$lecturerCheck->bind_param('i', $lecturer_id);
$lecturerCheck->execute();
$lecturerCheck->store_result();

if ($lecturerCheck->num_rows === 0) {
    $lecturerCheck->close();
    $conn->close();
    exit('Invalid lecturer selected.');
}
$lecturerCheck->close();

// 7. Lock supervisor to only the two fixed supervisors
// John Tan = 9, Sarah Lim = 10
$allowed_supervisors = [9, 10];

if (!in_array($supervisor_id, $allowed_supervisors, true)) {
    $conn->close();
    exit('Invalid supervisor selected. Only fixed supervisors are allowed.');
}

// 8. Optional extra safety: make company and supervisor match
$company_lower = strtolower(trim($company_name));

if ($company_lower === 'intel penang') {
    $supervisor_id = 9;
} elseif ($company_lower === 'maybank') {
    $supervisor_id = 10;
}

// 9. Double-check supervisor really exists and is active supervisor
$supervisorCheck = $conn->prepare("
    SELECT user_id
    FROM users
    WHERE user_id = ?
      AND role = 'supervisor'
      AND status = 'active'
");
if (!$supervisorCheck) {
    $conn->close();
    exit("Supervisor check failed: " . $conn->error);
}
$supervisorCheck->bind_param('i', $supervisor_id);
$supervisorCheck->execute();
$supervisorCheck->store_result();

if ($supervisorCheck->num_rows === 0) {
    $supervisorCheck->close();
    $conn->close();
    exit('Selected supervisor is not valid.');
}
$supervisorCheck->close();

// 10. Check if student already has active internship
$active = $conn->prepare("
    SELECT internship_id
    FROM internships
    WHERE student_id = ? AND status != 'unassigned'
");
if (!$active) {
    $conn->close();
    exit("Active check failed: " . $conn->error);
}
$active->bind_param('s', $student_id);
$active->execute();
$active->store_result();

if ($active->num_rows > 0) {
    $active->close();
    $conn->close();
    exit('This student already has an active internship record.');
}
$active->close();

// 11. Update the existing unassigned record
$stmt = $conn->prepare("
    UPDATE internships
    SET lecturer_id = ?,
        supervisor_id = ?,
        company_name = ?,
        industry = ?,
        start_date = ?,
        end_date = ?,
        status = 'pending',
        notes = ?
    WHERE student_id = ? AND status = 'unassigned'
");

if (!$stmt) {
    $conn->close();
    exit("Update prepare failed: " . $conn->error);
}

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

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        $stmt->close();
        $conn->close();
        header("Location: internship_list.php");
        exit;
    } else {
        $stmt->close();
        $conn->close();
        exit('No unassigned internship record found for this student.');
    }
} else {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    exit("Database error: " . $error);
}
?>