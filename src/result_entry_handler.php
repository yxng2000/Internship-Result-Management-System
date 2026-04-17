<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'errors' => ['Invalid request method.']]);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) $input = $_POST;

// ── Read inputs ───────────────────────────────────────────
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

// ── Validate ─────────────────────────────────────────────
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

// ── Calculate total ───────────────────────────────────────
$total_score = $undertaking_tasks + $health_safety + $theoretical_knowledge
             + $report_presentation + $clarity_language + $lifelong_learning
             + $project_management + $time_management;

$conn = getConnection();

// ── Check internship exists ───────────────────────────────
$chk = $conn->prepare("SELECT internship_id FROM internships WHERE internship_id = ?");
$chk->bind_param('i', $internship_id);
$chk->execute();
$chk->store_result();
if ($chk->num_rows === 0) {
    echo json_encode(['success' => false, 'errors' => ['Internship record not found.']]);
    $chk->close(); $conn->close(); exit;
}
$chk->close();

// ── Upsert: delete old record if exists, then insert ─────
$del = $conn->prepare("DELETE FROM assessments WHERE internship_id = ?");
$del->bind_param('i', $internship_id);
$del->execute();
$del->close();

$stmt = $conn->prepare("
    INSERT INTO assessments
        (internship_id, undertaking_tasks, health_safety, theoretical_knowledge,
         report_presentation, clarity_language, lifelong_learning,
         project_management, time_management, total_score, comments)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    'iddddddddds',
    $internship_id,
    $undertaking_tasks,
    $health_safety,
    $theoretical_knowledge,
    $report_presentation,
    $clarity_language,
    $lifelong_learning,
    $project_management,
    $time_management,
    $total_score,
    $comments
);

if ($stmt->execute()) {
    // FIXED: Updated to 'completed' to match your ENUM schema
    $upd = $conn->prepare("
        UPDATE internships SET status = 'completed'
        WHERE internship_id = ?
    ");
    $upd->bind_param('i', $internship_id);
    $upd->execute();
    $upd->close();

    echo json_encode([
        'success'     => true,
        'message'     => 'Assessment submitted successfully.',
        'total_score' => number_format($total_score, 2)
    ]);
} else {
    echo json_encode(['success' => false, 'errors' => ['Database error: ' . $stmt->error]]);
}

$stmt->close();
$conn->close();
?>