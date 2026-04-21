<?php
// ============================================================
//  result_entry_handler.php
//  Stores lecturer OR supervisor scores separately.
//  When both have submitted, computes the averaged final score.
// ============================================================
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit;
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['lecturer', 'supervisor'])) {
    echo json_encode(['success' => false, 'errors' => ['Unauthorized.']]);
    exit;
}
$assessor_type = ($user_role === 'lecturer') ? 'lecturer' : 'supervisor';

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;

$internship_id          = isset($input['internship_id'])          ? (int)$input['internship_id']              : 0;
$undertaking_tasks      = isset($input['undertaking_tasks'])      ? (float)$input['undertaking_tasks']         : null;
$health_safety          = isset($input['health_safety'])          ? (float)$input['health_safety']             : null;
$theoretical_knowledge  = isset($input['theoretical_knowledge'])  ? (float)$input['theoretical_knowledge']     : null;
$report_presentation    = isset($input['report_presentation'])    ? (float)$input['report_presentation']       : null;
$clarity_language       = isset($input['clarity_language'])       ? (float)$input['clarity_language']          : null;
$lifelong_learning      = isset($input['lifelong_learning'])      ? (float)$input['lifelong_learning']         : null;
$project_management     = isset($input['project_management'])     ? (float)$input['project_management']        : null;
$time_management        = isset($input['time_management'])        ? (float)$input['time_management']           : null;
$comments               = isset($input['comments'])               ? trim($input['comments'])                   : '';

// Validate
$errors = [];
if (!$internship_id) $errors[] = 'Invalid internship ID.';

$limits = [
    'undertaking_tasks'     => [0, 10,  $undertaking_tasks],
    'health_safety'         => [0, 10,  $health_safety],
    'theoretical_knowledge' => [0, 10,  $theoretical_knowledge],
    'report_presentation'   => [0, 15,  $report_presentation],
    'clarity_language'      => [0, 10,  $clarity_language],
    'lifelong_learning'     => [0, 15,  $lifelong_learning],
    'project_management'    => [0, 15,  $project_management],
    'time_management'       => [0, 15,  $time_management],
];

foreach ($limits as $field => [$min, $max, $val]) {
    if ($val === null || $val < $min || $val > $max) {
        $errors[] = ucwords(str_replace('_', ' ', $field)) . " must be between $min and $max.";
    }
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$my_total = $undertaking_tasks + $health_safety + $theoretical_knowledge
          + $report_presentation + $clarity_language + $lifelong_learning
          + $project_management + $time_management;

$conn = getConnection();

// Verify internship exists
$chk = $conn->prepare("SELECT internship_id FROM internships WHERE internship_id = ?");
$chk->bind_param('i', $internship_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    echo json_encode(['success' => false, 'errors' => ['Internship record not found.']]);
    $chk->close(); $conn->close(); exit;
}
$chk->close();

// Upsert: delete my old row if any, then insert fresh
$del = $conn->prepare("DELETE FROM assessments WHERE internship_id = ? AND assessor_type = ?");
$del->bind_param('is', $internship_id, $assessor_type);
$del->execute();
$del->close();

$ins = $conn->prepare("
    INSERT INTO assessments
        (internship_id, assessor_type, undertaking_tasks, health_safety, theoretical_knowledge,
         report_presentation, clarity_language, lifelong_learning,
         project_management, time_management, total_score, comments)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$ins->bind_param('isddddddddds',
    $internship_id, $assessor_type,
    $undertaking_tasks, $health_safety, $theoretical_knowledge,
    $report_presentation, $clarity_language, $lifelong_learning,
    $project_management, $time_management, $my_total, $comments
);

if (!$ins->execute()) {
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $ins->error]]);
    $ins->close(); $conn->close(); exit;
}
$ins->close();

// Check if BOTH lecturer and supervisor have now submitted
$bothCheck = $conn->prepare("
    SELECT assessor_type, total_score
    FROM assessments
    WHERE internship_id = ?
");
$bothCheck->bind_param('i', $internship_id);
$bothCheck->execute();
$bothResult = $bothCheck->get_result();
$bothCheck->close();

$submitted = [];
while ($row = $bothResult->fetch_assoc()) {
    $submitted[$row['assessor_type']] = (float)$row['total_score'];
}

$final_score = null;
$new_status  = 'pending';

if (isset($submitted['lecturer']) && isset($submitted['supervisor'])) {
    // Average of both totals
    $final_score = round(($submitted['lecturer'] + $submitted['supervisor']) / 2, 2);
    $new_status  = 'completed';
}

// Update internship status
$upd = $conn->prepare("UPDATE internships SET status = ? WHERE internship_id = ?");
$upd->bind_param('si', $new_status, $internship_id);
$upd->execute();
$upd->close();

$conn->close();

echo json_encode([
    'success'     => true,
    'message'     => 'Assessment submitted successfully.',
    'total_score' => number_format($my_total, 2),
    'final_score' => $final_score !== null ? number_format($final_score, 2) : null,
    'both_done'   => ($final_score !== null)
]);
?>