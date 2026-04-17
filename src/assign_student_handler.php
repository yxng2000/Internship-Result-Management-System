<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Invalid request method.');
}

// 1. Read form data
$student_id      = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
$assessor_id     = isset($_POST['assessor']) ? (int)$_POST['assessor'] : 0;
$company_name    = isset($_POST['company']) ? trim($_POST['company']) : '';
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
if ($assessor_id <= 0) $errors[] = 'Please select an assessor.';
if ($company_name === '') $errors[] = 'Company name is required.';

// date input type="date" sends YYYY-MM-DD
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

// 6. Check if student already has active internship
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

// 7. Update the existing unassigned record
$stmt = $conn->prepare("
    UPDATE internships
    SET assessor_id = ?,
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
    'issssss',
    $assessor_id,
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
        header("Location: internship_list.html");
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