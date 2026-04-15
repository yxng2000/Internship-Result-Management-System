<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();

$search   = isset($_GET['search']) ? '%' . $conn->real_escape_string($_GET['search']) . '%' : '%';
$status   = isset($_GET['status']) && $_GET['status'] !== 'all' ? $conn->real_escape_string($_GET['status']) : null;
$assessor = isset($_GET['assessor']) && $_GET['assessor'] !== 'all' ? $conn->real_escape_string($_GET['assessor']) : null;

$sql = "
    SELECT
        s.student_id,
        s.full_name,
        s.programme,
        u.full_name AS assessor_name,
        i.company_name,
        i.industry,
        i.start_date,
        i.end_date,
        i.internship_id,
        a.total_score
    FROM internships i
    JOIN students s ON i.student_id = s.student_id
    LEFT JOIN users u ON i.assessor_id = u.user_id
    LEFT JOIN assessments a 
        ON i.internship_id = a.internship_id
    WHERE (s.student_id LIKE ? OR s.full_name LIKE ?)
";

$params = [$search, $search];
$types  = 'ss';

if ($assessor) {
    $sql .= " AND u.full_name = ?";
    $params[] = $assessor;
    $types .= 's';
}

$sql .= " ORDER BY s.student_id ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die(json_encode([
        'error' => 'Prepare failed',
        'details' => $conn->error
    ]));
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$counts = [
    'completed' => 0,
    'pending' => 0,
    'unassigned' => 0,
    'total' => 0
];

while ($row = $result->fetch_assoc()) {
    if (empty($row['assessor_name'])) {
        $row['status'] = 'unassigned';
    } elseif ($row['total_score'] === null) {
        $row['status'] = 'pending';
    } else {
        $row['status'] = 'completed';
    }

    if ($status && $row['status'] !== $status) {
        continue;
    }

    $rows[] = $row;
    $counts[$row['status']]++;
    $counts['total']++;
}

echo json_encode([
    'records' => $rows,
    'counts' => $counts
]);

$stmt->close();
$conn->close();
?>