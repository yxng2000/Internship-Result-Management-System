<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();

$search    = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
$status    = isset($_GET['status']) && $_GET['status'] !== 'all' ? $_GET['status'] : null;
$assessor  = isset($_GET['assessor']) && $_GET['assessor'] !== 'all' ? $_GET['assessor'] : null;
$programme = isset($_GET['programme']) && $_GET['programme'] !== 'all' ? $_GET['programme'] : null;

$sql = "
    SELECT
        s.student_id,
        s.full_name,
        s.programme,
        i.company_name,
        i.industry,
        i.start_date,
        i.end_date,
        i.internship_id,

        lec.full_name AS lecturer_name,
        sup.full_name AS supervisor_name,

        la.total_score AS lecturer_score,
        sa.total_score AS supervisor_score,

        CASE
            WHEN i.lecturer_id IS NULL
                 OR i.supervisor_id IS NULL
                 OR i.company_name IS NULL
                 OR i.company_name = ''
            THEN 'unassigned'

            WHEN la.total_score IS NOT NULL
                 AND sa.total_score IS NOT NULL
            THEN 'completed'

            ELSE 'pending'
        END AS status

    FROM internships i
    JOIN students s
        ON i.student_id = s.student_id

    LEFT JOIN users lec
        ON i.lecturer_id = lec.user_id

    LEFT JOIN users sup
        ON i.supervisor_id = sup.user_id

    LEFT JOIN assessments la
        ON i.internship_id = la.internship_id
       AND la.assessor_type = 'lecturer'

    LEFT JOIN assessments sa
        ON i.internship_id = sa.internship_id
       AND sa.assessor_type = 'supervisor'

    WHERE (s.student_id LIKE ? OR s.full_name LIKE ?)
";

$params = [$search, $search];
$types  = 'ss';

if ($status) {
    $sql .= "
        AND (
            CASE
                WHEN i.lecturer_id IS NULL
                     OR i.supervisor_id IS NULL
                     OR i.company_name IS NULL
                     OR i.company_name = ''
                THEN 'unassigned'

                WHEN la.total_score IS NOT NULL
                     AND sa.total_score IS NOT NULL
                THEN 'completed'

                ELSE 'pending'
            END
        ) = ?
    ";
    $params[] = $status;
    $types .= 's';
}

if ($assessor) {
    $sql .= " AND (lec.full_name = ? OR sup.full_name = ?)";
    $params[] = $assessor;
    $params[] = $assessor;
    $types .= 'ss';
}

if ($programme) {
    $sql .= " AND s.programme = ?";
    $params[] = $programme;
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
    $row['lecturer_score'] = $row['lecturer_score'] !== null ? (float)$row['lecturer_score'] : null;
    $row['supervisor_score'] = $row['supervisor_score'] !== null ? (float)$row['supervisor_score'] : null;

    $rows[] = $row;

    if (isset($counts[$row['status']])) {
        $counts[$row['status']]++;
    }

    $counts['total']++;
}

echo json_encode([
    'records' => $rows,
    'counts' => $counts
]);

$stmt->close();
$conn->close();
?>