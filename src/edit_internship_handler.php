<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
header('Content-Type: application/json');
require_once 'config.php';

function jsonResponse($data) {
    echo json_encode($data);
    exit;
}

function convertDate($d) {
    if ($d === null || $d === '') return null;

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        [$year, $month, $day] = explode('-', $d);

        if (checkdate((int)$month, (int)$day, (int)$year)) {
            return $d;
        }

        return false;
    }

    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $d)) {
        [$day, $month, $year] = explode('/', $d);

        if (checkdate((int)$month, (int)$day, (int)$year)) {
            return $year . '-' . $month . '-' . $day;
        }

        return false;
    }

    return false;
}

/* ============================================================
   GET -> Load one internship record
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
            DATE_FORMAT(i.start_date, '%Y-%m-%d') AS start_date,
            DATE_FORMAT(i.end_date, '%Y-%m-%d')   AS end_date,
            i.status,
            i.notes,
            DATE_FORMAT(i.updated_at, '%d/%m/%Y') AS last_updated
        FROM internships i
        JOIN students s ON i.student_id = s.student_id
        LEFT JOIN users l ON i.lecturer_id = l.user_id
        LEFT JOIN users sp ON i.supervisor_id = sp.user_id
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
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$record) {
        $conn->close();
        jsonResponse([
            'success' => false,
            'error' => 'Record not found.'
        ]);
    }

    $lecturers = [];

    $lecturerResult = $conn->query("
        SELECT
            u.user_id,
            u.full_name,
            u.programme,
            COUNT(i.internship_id) AS student_count
        FROM users u
        LEFT JOIN internships i ON u.user_id = i.lecturer_id
        WHERE LOWER(u.role) = 'lecturer'
          AND u.status = 'active'
        GROUP BY u.user_id, u.full_name, u.programme
        ORDER BY u.full_name
    ");

    if ($lecturerResult) {
        while ($row = $lecturerResult->fetch_assoc()) {
            $lecturers[] = $row;
        }
    }

    $companies = [];
    $companySupervisorMap = [];

    $companyResult = $conn->query("
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
    ");

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
        'is_locked' => strtolower($record['status']) === 'completed',
        'message' => strtolower($record['status']) === 'completed'
            ? 'This record is completed and should be view-only.'
            : ''
    ]);
}

/* ============================================================
   POST -> Update internship record + write activity log
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

    if (!in_array($status, ['unassigned', 'pending'], true)) {
        $errors[] = 'Invalid status. Edit page only allows unassigned or pending.';
    }

    $conn = getConnection();

    /* Get existing record before update */
    $checkStmt = $conn->prepare("
        SELECT 
            i.internship_id,
            i.student_id,
            s.full_name AS student_name,
            i.status,
            i.company_name
        FROM internships i
        JOIN students s ON i.student_id = s.student_id
        WHERE i.internship_id = ?
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
    $checkStmt->execute();
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

    /* Update internship */
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

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();

        jsonResponse([
            'success' => false,
            'errors' => ['Database error: ' . $error]
        ]);
    }

    $affected = $stmt->affected_rows;
    $stmt->close();

    /* Write activity log only after update success */
    if ($status === 'unassigned') {
        $title = 'Internship record set to unassigned';
        $description = $existing['student_name'] . ' was changed to unassigned. Lecturer, supervisor, company and dates were cleared.';
    } else {
        $title = 'Internship record updated';
        $description = $existing['student_name'] . ' internship details were updated. Company: ' . $company_name . '. Status: ' . $status . '.';
    }

    $action_type = 'edit';
    $target_type = 'internship';
    $target_id = $internship_id;
    $link_url = 'edit_internship.php?id=' . $internship_id;

    $logStmt = $conn->prepare("
        INSERT INTO activity_logs
        (action_type, target_type, target_id, title, description, link_url)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    if ($logStmt) {
        $logStmt->bind_param(
            'ssisss',
            $action_type,
            $target_type,
            $target_id,
            $title,
            $description,
            $link_url
        );
        $logStmt->execute();
        $logStmt->close();
    }

    $conn->close();

    jsonResponse([
        'success' => true,
        'message' => 'Record updated successfully.',
        'affected_rows' => $affected
    ]);
}

jsonResponse([
    'success' => false,
    'error' => 'Unsupported request method.'
]);
?>