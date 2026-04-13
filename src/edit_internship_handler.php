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

// ── GET: load record ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) { echo json_encode(['error' => 'No ID provided.']); exit; }

    $conn = getConnection();
    $stmt = $conn->prepare("
        SELECT
            i.internship_id,
            i.student_id,
            s.full_name,
            s.programme,
            u.user_id     AS assessor_id,
            u.full_name   AS assessor_name,
            i.company_name,
            i.industry,
            DATE_FORMAT(i.start_date, '%d/%m/%Y') AS start_date,
            DATE_FORMAT(i.end_date,   '%d/%m/%Y') AS end_date,
            i.status,
            i.notes,
            DATE_FORMAT(i.updated_at, '%d/%m/%Y') AS last_updated
        FROM internships i
        JOIN students s ON i.student_id = s.student_id
        LEFT JOIN users u ON i.assessor_id = u.user_id
        WHERE i.internship_id = ?
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$record) {
        echo json_encode(['success' => false, 'error' => 'Record not found.']);
        $conn->close();
        exit;
    }

    $assessors = [];
    $sql = "
        SELECT
            u.user_id,
            u.full_name,
            COUNT(i.internship_id) AS student_count
        FROM users u
        LEFT JOIN internships i ON u.user_id = i.assessor_id
        WHERE LOWER(u.role) = 'assessor'
        GROUP BY u.user_id, u.full_name
        ORDER BY u.full_name
    ";

    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $assessors[] = $row;
    }

    echo json_encode([
        'success' => true,
        'record' => $record,
        'assessors' => $assessors
    ]);

    $conn->close();
    exit;
}

// ── POST: save changes ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    $input = is_array($json) ? $json : $_POST;

    $internship_id = isset($input['internship_id']) ? (int)$input['internship_id'] : 0;
    $assessor_id   = isset($input['assessor_id'])   ? (int)$input['assessor_id']   : 0;
    $company_name  = isset($input['company_name'])  ? trim($input['company_name'])  : '';
    $industry      = isset($input['industry'])      ? trim($input['industry'])      : '';
    $start_date    = isset($input['start_date'])    ? trim($input['start_date'])    : '';
    $end_date      = isset($input['end_date'])      ? trim($input['end_date'])      : '';
    $status        = isset($input['status'])        ? strtolower(trim($input['status'])) : '';
    $notes         = isset($input['notes'])         ? trim($input['notes'])         : '';

    $errors = [];

    if (!$internship_id)      $errors[] = 'Invalid record ID.';
    if ($assessor_id <= 0)    $errors[] = 'Please select an assessor.';
    if (empty($company_name)) $errors[] = 'Company name is required.';
    if (!in_array($status, ['assigned', 'pending', 'unassigned'])) $errors[] = 'Invalid status.';

    function convertDate($d) {
        $parts = explode('/', $d);
        if (count($parts) !== 3) return false;
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }

    $start_mysql = convertDate($start_date);
    $end_mysql   = convertDate($end_date);

    if (!$start_mysql) $errors[] = 'Invalid start date.';
    if (!$end_mysql)   $errors[] = 'Invalid end date.';
    if ($start_mysql && $end_mysql && $end_mysql <= $start_mysql) $errors[] = 'End date must be after start date.';

    if (!empty($errors)) {
        echo json_encode(['success' => false, 'errors' => $errors, 'received' => $input]);
        exit;
    }

    $conn = getConnection();
    $stmt = $conn->prepare("
        UPDATE internships
        SET assessor_id  = ?,
            company_name = ?,
            industry     = ?,
            start_date   = ?,
            end_date     = ?,
            status       = ?,
            notes        = ?
        WHERE internship_id = ?
    ");

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
        echo json_encode([
            'success' => true,
            'message' => 'Record updated successfully.',
            'affected_rows' => $stmt->affected_rows
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'errors' => ['Database error: ' . $stmt->error]
        ]);
    }

    $stmt->close();
    $conn->close();
    exit;
}