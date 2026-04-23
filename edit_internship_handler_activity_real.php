<?php
// ============================================================
//  edit_internship_handler.php
//  GET  → fetch one record by internship_id
//  POST → update the record
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

function convertDate($d) {
    if ($d === null || $d === '') return null;

    $parts = explode('/', $d);
    if (count($parts) !== 3) return false;

    $day   = trim($parts[0]);
    $month = trim($parts[1]);
    $year  = trim($parts[2]);

    if (!checkdate((int)$month, (int)$day, (int)$year)) {
        return false;
    }

    return $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
}

// ── GET: load record ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$id) {
        jsonResponse([
            'success' => false,
            'error' => 'No ID provided.'
        ]);
    }

    $conn = getConnection();

    $stmt = $conn->prepare("
        SELECT
            i.internship_id,
            i.student_id,
            s.full_name,
            s.programme,
            u.user_id   AS assessor_id,
            u.full_name AS assessor_name,
            i.company_name,
            i.industry,
            DATE_FORMAT(i.start_date, '%d/%m/%Y') AS start_date,
            DATE_FORMAT(i.end_date, '%d/%m/%Y')   AS end_date,
            i.status,
            i.notes,
            DATE_FORMAT(i.updated_at, '%d/%m/%Y') AS last_updated
        FROM internships i
        JOIN students s ON i.student_id = s.student_id
        LEFT JOIN users u ON i.assessor_id = u.user_id
        WHERE i.internship_id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        $conn->close();
        jsonResponse([
            'success' => false,
            'error' => 'Prepare failed: ' . $conn->error
        ]);
    }

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        jsonResponse([
            'success' => false,
            'error' => 'Execute failed: ' . $error
        ]);
    }

    $result = $stmt->get_result();
    $record = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$record) {
        $conn->close();
        jsonResponse([
            'success' => false,
            'error' => 'Record not found.'
        ]);
    }

    $assessors = [];
    $sql = "
        SELECT
            u.user_id,
            u.full_name,
            u.programme,
            COUNT(i.internship_id) AS student_count
        FROM users u
        LEFT JOIN internships i ON u.user_id = i.assessor_id
        WHERE LOWER(u.role) = 'assessor'
        GROUP BY u.user_id, u.full_name, u.programme
        ORDER BY u.full_name
    ";

    $assessorResult = $conn->query($sql);

    if ($assessorResult) {
        while ($row = $assessorResult->fetch_assoc()) {
            $assessors[] = $row;
        }
    }

    $conn->close();

    jsonResponse([
        'success' => true,
        'record' => $record,
        'assessors' => $assessors,
        'is_locked' => ($record['status'] === 'completed'),
        'message' => ($record['status'] === 'completed')
            ? 'This record is completed and should be view-only.'
            : ''
    ]);
}

// ── POST: save changes ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    $input = is_array($json) ? $json : $_POST;

    $internship_id = isset($input['internship_id']) ? (int)$input['internship_id'] : 0;
    $assessor_id   = isset($input['assessor_id']) && $input['assessor_id'] !== '' ? (int)$input['assessor_id'] : null;
    $company_name  = isset($input['company_name']) ? trim($input['company_name']) : '';
    $industry      = isset($input['industry']) ? trim($input['industry']) : '';
    $start_date    = isset($input['start_date']) ? trim($input['start_date']) : '';
    $end_date      = isset($input['end_date']) ? trim($input['end_date']) : '';
    $status        = isset($input['status']) ? strtolower(trim($input['status'])) : '';
    $notes         = isset($input['notes']) ? trim($input['notes']) : '';

    $errors = [];

    if (!$internship_id) {
        $errors[] = 'Invalid record ID.';
    }

    // edit page 不允许手动设成 completed
    if (!in_array($status, ['unassigned', 'pending'])) {
        $errors[] = 'Invalid status. Edit page only allows unassigned or pending.';
    }

    // 先查当前记录
    $conn = getConnection();

    $checkStmt = $conn->prepare("
        SELECT i.internship_id, i.status, s.full_name AS student_name, u.full_name AS assessor_name
        FROM internships i
        JOIN students s ON i.student_id = s.student_id
        LEFT JOIN users u ON i.assessor_id = u.user_id
        WHERE internship_id = ?
        LIMIT 1
    ");

    if (!$checkStmt) {
        $conn->close();
        jsonResponse([
            'success' => false,
            'errors' => ['Prepare failed: ' . $conn->error]
        ]);
    }

    $checkStmt->bind_param('i', $internship_id);

    if (!$checkStmt->execute()) {
        $error = $checkStmt->error;
        $checkStmt->close();
        $conn->close();
        jsonResponse([
            'success' => false,
            'errors' => ['Execute failed: ' . $error]
        ]);
    }

    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$existing) {
        $conn->close();
        jsonResponse([
            'success' => false,
            'errors' => ['Record not found.']
        ]);
    }

    // 已完成记录不允许再改
    if (strtolower($existing['status']) === 'completed') {
        $conn->close();
        jsonResponse([
            'success' => false,
            'errors' => ['Completed record cannot be edited.']
        ]);
    }

    // 日期检查：只有非 unassigned 才需要
    $start_mysql = null;
    $end_mysql   = null;

    if ($status !== 'unassigned') {
        $start_mysql = convertDate($start_date);
        $end_mysql   = convertDate($end_date);

        if (!$start_mysql) $errors[] = 'Invalid start date.';
        if (!$end_mysql)   $errors[] = 'Invalid end date.';
        if ($start_mysql && $end_mysql && $end_mysql <= $start_mysql) {
            $errors[] = 'End date must be after start date.';
        }
    }

    // status logic
    if ($status === 'unassigned') {
        $assessor_id  = null;
        $company_name = null;
        $industry     = null;
        $start_mysql  = null;
        $end_mysql    = null;
        $notes        = $notes; // notes 可留可不留
    } else {
        if ($assessor_id === null || $assessor_id <= 0) {
            $errors[] = 'Please select an assessor.';
        }
        if ($company_name === '') {
            $errors[] = 'Company name is required.';
        }
        if ($industry === '') {
            $errors[] = 'Industry is required.';
        }
    }

    if (!empty($errors)) {
        $conn->close();
        jsonResponse([
            'success' => false,
            'errors' => $errors,
            'received' => $input
        ]);
    }

    $stmt = $conn->prepare("
        UPDATE internships
        SET assessor_id  = ?,
            company_name = ?,
            industry     = ?,
            start_date   = ?,
            end_date     = ?,
            status       = ?,
            notes        = ?,
            updated_at   = NOW()
        WHERE internship_id = ?
    ");

    if (!$stmt) {
        $conn->close();
        jsonResponse([
            'success' => false,
            'errors' => ['Prepare failed: ' . $conn->error]
        ]);
    }

    $stmt->bind_param(
        'issssssi',
        $assessor_id,
        $company_name,
        $industry,
        $start_mysql,
        $end_mysql,
        $status,
        $notes,
        $internship_id
    );

    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();

        $logTitle = 'Internship record updated';
        if ($status === 'unassigned') {
            $logTitle = ($existing['student_name'] ?? 'Student') . ' marked as unassigned';
        } elseif ($assessor_id !== null && $assessor_id > 0) {
            $assessorName = $existing['assessor_name'] ?? 'assessor';
            $freshAssessorStmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
            if ($freshAssessorStmt) {
                $freshAssessorStmt->bind_param('i', $assessor_id);
                $freshAssessorStmt->execute();
                $freshAssessor = $freshAssessorStmt->get_result()->fetch_assoc();
                if ($freshAssessor && !empty($freshAssessor['full_name'])) {
                    $assessorName = $freshAssessor['full_name'];
                }
                $freshAssessorStmt->close();
            }
            $logTitle = ($existing['student_name'] ?? 'Student') . ' assigned to ' . $assessorName;
        }

        $logText = ($company_name ? 'Internship company recorded as ' . $company_name . '. ' : '') . 'Status is now ' . $status . '.';
        writeActivityLog($conn, 'edit', 'internship', $internship_id, $logTitle, $logText, 'internship_list.html');

        $conn->close();

        jsonResponse([
            'success' => true,
            'message' => 'Record updated successfully.',
            'affected_rows' => $affected
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();

        jsonResponse([
            'success' => false,
            'errors' => ['Database error: ' . $error]
        ]);
    }
}

jsonResponse([
    'success' => false,
    'error' => 'Unsupported request method.'
]);
?>