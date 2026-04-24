<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once 'config.php';

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

/* ============================================================
   GET -> Load one internship record + lecturers + companies
   ============================================================ */
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
            l.user_id    AS lecturer_id,
            l.full_name  AS lecturer_name,
            sp.user_id   AS supervisor_id,
            sp.full_name AS supervisor_name,
            i.company_name,
            i.industry,
            DATE_FORMAT(i.start_date, '%d/%m/%Y') AS start_date,
            DATE_FORMAT(i.end_date, '%d/%m/%Y')   AS end_date,
            i.status,
            i.notes,
            DATE_FORMAT(i.updated_at, '%d/%m/%Y') AS last_updated
        FROM internships i
        JOIN students s
            ON i.student_id = s.student_id
        LEFT JOIN users l
            ON i.lecturer_id = l.user_id
        LEFT JOIN users sp
            ON i.supervisor_id = sp.user_id
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

    // lecturers list
    $lecturers = [];
    $lecturerSql = "
        SELECT
            u.user_id,
            u.full_name,
            u.programme,
            COUNT(i.internship_id) AS student_count
        FROM users u
        LEFT JOIN internships i
            ON u.user_id = i.lecturer_id
        WHERE LOWER(u.role) = 'lecturer'
          AND u.status = 'active'
        GROUP BY u.user_id, u.full_name, u.programme
        ORDER BY u.full_name
    ";

    $lecturerResult = $conn->query($lecturerSql);
    if ($lecturerResult) {
        while ($row = $lecturerResult->fetch_assoc()) {
            $lecturers[] = $row;
        }
    }

    // companies + supervisor map
    // directly from users table, no extra company_supervisor table needed
    $companies = [];
    $companySupervisorMap = [];

    $companySql = "
        SELECT
            user_id AS supervisor_id,
            full_name AS supervisor_name,
            company_name
        FROM users
        WHERE LOWER(role) = 'supervisor'
          AND status = 'active'
          AND company_name IS NOT NULL
          AND company_name <> ''
        ORDER BY company_name
    ";

    $companyResult = $conn->query($companySql);
    if ($companyResult) {
        while ($row = $companyResult->fetch_assoc()) {
            $companies[] = [
                'company_name' => $row['company_name']
            ];

            $companySupervisorMap[$row['company_name']] = [
                'supervisor_id'   => $row['supervisor_id'],
                'supervisor_name' => $row['supervisor_name']
            ];
        }
    }

    $conn->close();

    jsonResponse([
        'success' => true,
        'record' => $record,
        'lecturers' => $lecturers,
        'companies' => $companies,
        'company_supervisor_map' => $companySupervisorMap,
        'is_locked' => (strtolower($record['status']) === 'completed'),
        'message' => (strtolower($record['status']) === 'completed')
            ? 'This record is completed and should be view-only.'
            : ''
    ]);
}

/* ============================================================
   POST -> Update internship record
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    $input = is_array($json) ? $json : $_POST;

    $internship_id = isset($input['internship_id']) ? (int)$input['internship_id'] : 0;
    $lecturer_id   = isset($input['lecturer_id']) && $input['lecturer_id'] !== '' ? (int)$input['lecturer_id'] : null;
    $supervisor_id = isset($input['supervisor_id']) && $input['supervisor_id'] !== '' ? (int)$input['supervisor_id'] : null;
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

    if (!in_array($status, ['unassigned', 'pending'])) {
        $errors[] = 'Invalid status. Edit page only allows unassigned or pending.';
    }

    $conn = getConnection();

    // check existing record
    $checkStmt = $conn->prepare("
        SELECT internship_id, status
        FROM internships
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

    if (strtolower($existing['status']) === 'completed') {
        $conn->close();
        jsonResponse([
            'success' => false,
            'errors' => ['Completed record cannot be edited.']
        ]);
    }

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

        if ($lecturer_id === null || $lecturer_id <= 0) {
            $errors[] = 'Please select a lecturer.';
        }

        if ($supervisor_id === null || $supervisor_id <= 0) {
            $errors[] = 'Please select a supervisor.';
        }

        if ($company_name === '') {
            $errors[] = 'Company name is required.';
        }

        if ($industry === '') {
            $errors[] = 'Industry is required.';
        }

        // verify supervisor matches selected company
        if ($supervisor_id !== null && $supervisor_id > 0 && $company_name !== '') {
            $verifyStmt = $conn->prepare("
                SELECT user_id
                FROM users
                WHERE user_id = ?
                  AND LOWER(role) = 'supervisor'
                  AND status = 'active'
                  AND company_name = ?
                LIMIT 1
            ");

            if ($verifyStmt) {
                $verifyStmt->bind_param('is', $supervisor_id, $company_name);
                $verifyStmt->execute();
                $verified = $verifyStmt->get_result()->fetch_assoc();
                $verifyStmt->close();

                if (!$verified) {
                    $errors[] = 'Selected supervisor does not match the selected company.';
                }
            } else {
                $errors[] = 'Failed to verify supervisor-company mapping.';
            }
        }
    }

    if ($status === 'unassigned') {
        $lecturer_id   = null;
        $supervisor_id = null;
        $company_name  = null;
        $industry      = null;
        $start_mysql   = null;
        $end_mysql     = null;
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
        SET lecturer_id   = ?,
            supervisor_id = ?,
            company_name  = ?,
            industry      = ?,
            start_date    = ?,
            end_date      = ?,
            status        = ?,
            notes         = ?,
            updated_at    = NOW()
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
        'iissssssi',
        $lecturer_id,
        $supervisor_id,
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