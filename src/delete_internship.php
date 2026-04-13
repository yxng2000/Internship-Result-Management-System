<?php
// ============================================================
//  delete_internship.php  (POST handler)
//  Deletes an internship record by internship_id
// ============================================================
header('Content-Type: application/json');
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$internship_id = isset($_POST['internship_id']) ? (int)$_POST['internship_id'] : 0;

if (!$internship_id) {
    echo json_encode(['success' => false, 'error' => 'No record ID provided.']);
    exit;
}

$conn = getConnection();
$stmt = $conn->prepare("DELETE FROM internships WHERE internship_id = ?");
$stmt->bind_param('i', $internship_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
