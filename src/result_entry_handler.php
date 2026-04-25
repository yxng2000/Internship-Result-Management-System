<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['lecturer', 'supervisor'])) { 
    echo json_encode(['success' => false, 'errors' => ["Unauthorized role. Please log out and log back in."]]);
    exit; 
}
$assessor_type = ($user_role === 'lecturer') ? 'lecturer' : 'supervisor';

// Fetch the POST data
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$internship_id = (int)($input['internship_id'] ?? 0);

if (!$internship_id) {
    echo json_encode(['success' => false, 'errors' => ['Missing internship ID.']]);
    exit;
}

// Ensure all inputs are treated as numbers
$my_total = array_sum([
    (float)($input['undertaking_tasks'] ?? 0), 
    (float)($input['health_safety'] ?? 0), 
    (float)($input['theoretical_knowledge'] ?? 0), 
    (float)($input['report_presentation'] ?? 0), 
    (float)($input['clarity_language'] ?? 0), 
    (float)($input['lifelong_learning'] ?? 0), 
    (float)($input['project_management'] ?? 0), 
    (float)($input['time_management'] ?? 0)
]);

$conn = getConnection();

// Check internship end date before allowing assessment
$check = $conn->prepare("
    SELECT end_date, status 
    FROM internships 
    WHERE internship_id = ?
");

if (!$check) {
    echo json_encode(['success' => false, 'errors' => ['SQL Prepare Check Error: ' . $conn->error]]);
    exit;
}

$check->bind_param('i', $internship_id);
$check->execute();
$result = $check->get_result();
$row = $result->fetch_assoc();
$check->close();

if (!$row) {
    echo json_encode(['success' => false, 'errors' => ['Internship record not found.']]);
    exit;
}

$today = date('Y-m-d');

if (empty($row['end_date'])) {
    echo json_encode(['success' => false, 'errors' => ['Internship end date is missing. Cannot assess.']]);
    exit;
}

if ($row['end_date'] > $today) {
    echo json_encode(['success' => false, 'errors' => ['Internship has not ended yet. Cannot assess.']]);
    exit;
}

// 1. Delete the old score for this specific assessor role
$del = $conn->prepare("DELETE FROM assessments WHERE internship_id = ? AND assessor_type = ?");
if (!$del) {
    echo json_encode(['success' => false, 'errors' => ['SQL Prepare Delete Error: ' . $conn->error]]);
    exit;
}
$del->bind_param('is', $internship_id, $assessor_type);
if (!$del->execute()) {
    echo json_encode(['success' => false, 'errors' => ['SQL Execute Delete Error: ' . $del->error]]);
    exit;
}
$del->close();

// 2. Insert the new scores
$ins = $conn->prepare("
    INSERT INTO assessments (internship_id, assessor_type, undertaking_tasks, health_safety, 
    theoretical_knowledge, report_presentation, clarity_language, lifelong_learning, 
    project_management, time_management, total_score, comments)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

// IF PREPARE FAILS: This catches if the table structure is wrong
if (!$ins) {
    echo json_encode(['success' => false, 'errors' => [
        'DATABASE ERROR: ' . $conn->error . ' ---> Did you forget to add the assessor_type column in phpMyAdmin?'
    ]]);
    exit;
}

$ins->bind_param('isddddddddds', $internship_id, $assessor_type, 
    $input['undertaking_tasks'], $input['health_safety'], $input['theoretical_knowledge'], 
    $input['report_presentation'], $input['clarity_language'], $input['lifelong_learning'], 
    $input['project_management'], $input['time_management'], $my_total, $input['comments']
);

// IF EXECUTE FAILS: This catches data mismatch issues
if (!$ins->execute()) {
    echo json_encode(['success' => false, 'errors' => ['SQL Execute Insert Error: ' . $ins->error]]);
    exit;
}
$ins->close();

// 3. Mark the internship as completed if both roles have graded
$bothCheck = $conn->prepare("SELECT COUNT(*) as c FROM assessments WHERE internship_id = ?");
$bothCheck->bind_param('i', $internship_id);
$bothCheck->execute();
$count = $bothCheck->get_result()->fetch_assoc()['c'];
$bothCheck->close();

if ($count >= 2) {
    $upd = $conn->prepare("UPDATE internships SET status = 'completed' WHERE internship_id = ?");
    $upd->bind_param('i', $internship_id);
    $upd->execute();
    $upd->close();
} else {
    $upd = $conn->prepare("UPDATE internships SET status = 'pending' WHERE internship_id = ? AND status = 'unassigned'");
    $upd->bind_param('i', $internship_id);
    $upd->execute();
    $upd->close();
}

$conn->close();

echo json_encode(['success' => true, 'total_score' => $my_total]);
?>