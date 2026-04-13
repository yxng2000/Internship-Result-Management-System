<?php
// ============================================================
//  get_internships.php
//  Returns all internship records as JSON for internship_list
// ============================================================
header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();

// Optional filters from query string
$search   = isset($_GET['search'])   ? '%' . $conn->real_escape_string($_GET['search'])   . '%' : '%';
$status   = isset($_GET['status'])   && $_GET['status']   !== 'all' ? $conn->real_escape_string($_GET['status'])   : null;
$assessor = isset($_GET['assessor']) && $_GET['assessor'] !== 'all' ? $conn->real_escape_string($_GET['assessor']) : null;

$sql = "
    SELECT
        s.student_id,
        s.full_name,
        s.programme,
        u.full_name   AS assessor_name,
        i.company_name,
        i.industry,
        i.start_date,
        i.end_date,
        i.status,
        i.internship_id
    FROM internships i
    JOIN students s ON i.student_id = s.student_id
    LEFT JOIN users u ON i.assessor_id = u.user_id
    WHERE (s.student_id LIKE ? OR s.full_name LIKE ?)
";

$params = [$search, $search];
$types  = 'ss';

if ($status) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($assessor) {
    $sql .= " AND u.full_name = ?";
    $params[] = $assessor;
    $types .= 's';
}

$sql .= " ORDER BY s.student_id ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// Summary counts
$countSql = "SELECT status, COUNT(*) as cnt FROM internships GROUP BY status";
$countResult = $conn->query($countSql);
$counts = ['assigned' => 0, 'pending' => 0, 'unassigned' => 0, 'total' => 0];
while ($c = $countResult->fetch_assoc()) {
    $counts[$c['status']] = (int)$c['cnt'];
    $counts['total'] += (int)$c['cnt'];
}

echo json_encode(['records' => $rows, 'counts' => $counts]);

$stmt->close();
$conn->close();
?>
