<?php
// ============================================================
//  delete_internship.php  (POST handler)
//  Deletes an internship record by internship_id
// ============================================================
header('Content-Type: application/json');
require_once 'config.php';


function writeActivityLog($conn, $actionType, $targetType, $targetId, $title, $description, $linkUrl = null)
{
    $actionType = mysqli_real_escape_string($conn, $actionType);
    $targetType = mysqli_real_escape_string($conn, $targetType);
    $title = mysqli_real_escape_string($conn, $title);
    $description = mysqli_real_escape_string($conn, $description);
    $linkUrl = $linkUrl !== null ? mysqli_real_escape_string($conn, $linkUrl) : null;

    $targetIdValue = $targetId === null ? 'NULL' : (int)$targetId;
    $linkValue = $linkUrl === null || $linkUrl === '' ? 'NULL' : "'" . $linkUrl . "'";

    $sql = "
        INSERT INTO activity_logs (action_type, target_type, target_id, title, description, link_url)
        VALUES (
            '$actionType',
            '$targetType',
            $targetIdValue,
            '$title',
            '$description',
            $linkValue
        )
    ";

    return mysqli_query($conn, $sql);
}


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

$metaStmt = $conn->prepare("
    SELECT s.full_name, i.company_name
    FROM internships i
    JOIN students s ON i.student_id = s.student_id
    WHERE i.internship_id = ?
    LIMIT 1
");
$studentName = 'Student';
$companyName = '';
if ($metaStmt) {
    $metaStmt->bind_param('i', $internship_id);
    $metaStmt->execute();
    $meta = $metaStmt->get_result()->fetch_assoc();
    if ($meta) {
        $studentName = $meta['full_name'] ?: $studentName;
        $companyName = $meta['company_name'] ?: '';
    }
    $metaStmt->close();
}

$stmt = $conn->prepare("DELETE FROM internships WHERE internship_id = ?");
$stmt->bind_param('i', $internship_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        writeActivityLog(
            $conn,
            'delete',
            'internship',
            $internship_id,
            'Internship record deleted',
            $studentName . ($companyName !== '' ? ' internship record for ' . $companyName : ' internship record') . ' was removed from the system.',
            'internship_list.html'
        );
    }
    echo json_encode(['success' => true, 'message' => 'Record deleted successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
