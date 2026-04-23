<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();
$student_id = $_SESSION['student_id'] ?? ''; 

if (empty($student_id)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. No student ID found in session.']);
    exit;
}

$query = "
    SELECT 
        s.student_id, s.full_name, s.programme, i.company_name, i.status,
        la.total_score AS l_total, sa.total_score AS s_total,
        la.undertaking_tasks AS l_ut, sa.undertaking_tasks AS s_ut,
        la.health_safety AS l_hs, sa.health_safety AS s_hs,
        la.theoretical_knowledge AS l_tk, sa.theoretical_knowledge AS s_tk,
        la.report_presentation AS l_rp, sa.report_presentation AS s_rp,
        la.clarity_language AS l_cl, sa.clarity_language AS s_cl,
        la.lifelong_learning AS l_ll, sa.lifelong_learning AS s_ll,
        la.project_management AS l_pm, sa.project_management AS s_pm,
        la.time_management AS l_tm, sa.time_management AS s_tm,
        la.comments AS l_comments, sa.comments AS s_comments,
        lu.full_name AS lecturer_name, su.full_name AS supervisor_name
    FROM students s
    LEFT JOIN internships i ON s.student_id = i.student_id
    LEFT JOIN assessments la ON i.internship_id = la.internship_id AND la.assessor_type = 'lecturer'
    LEFT JOIN assessments sa ON i.internship_id = sa.internship_id AND sa.assessor_type = 'supervisor'
    LEFT JOIN users lu ON i.lecturer_id = lu.user_id
    LEFT JOIN users su ON i.supervisor_id = su.user_id
    WHERE s.student_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    
    $data = [
        'student_id'    => $row['student_id'],
        'full_name'     => $row['full_name'],
        'programme'     => $row['programme'],
        'company_name'  => $row['company_name'],
        'status'        => $row['status'],
        'total_score'   => null,
        'lecturer_name' => $row['lecturer_name'] ?? null,
        'supervisor_name' => $row['supervisor_name'] ?? null
    ];

    // Only output scores if BOTH have graded
    if ($row['l_total'] !== null && $row['s_total'] !== null) {
        $data['total_score'] = (floatval($row['l_total']) + floatval($row['s_total'])) / 2;
        
        $data['scores'] = [
            'undertaking_tasks'     => ['l' => floatval($row['l_ut']), 's' => floatval($row['s_ut'])],
            'health_safety'         => ['l' => floatval($row['l_hs']), 's' => floatval($row['s_hs'])],
            'theoretical_knowledge' => ['l' => floatval($row['l_tk']), 's' => floatval($row['s_tk'])],
            'report_presentation'   => ['l' => floatval($row['l_rp']), 's' => floatval($row['s_rp'])],
            'clarity_language'      => ['l' => floatval($row['l_cl']), 's' => floatval($row['s_cl'])],
            'lifelong_learning'     => ['l' => floatval($row['l_ll']), 's' => floatval($row['s_ll'])],
            'project_management'    => ['l' => floatval($row['l_pm']), 's' => floatval($row['s_pm'])],
            'time_management'       => ['l' => floatval($row['l_tm']), 's' => floatval($row['s_tm'])],
        ];
        
        $data['lecturer_comments']   = $row['l_comments'];
        $data['supervisor_comments'] = $row['s_comments'];
    }

    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Student record not found.']);
}
?>