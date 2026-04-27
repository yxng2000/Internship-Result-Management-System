<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');
require_once 'config.php';

$conn = getConnection();

$query = "
    SELECT
        i.internship_id,
        i.student_id,
        s.full_name,
        s.programme,
        i.company_name,
        i.status AS db_status,
        i.lecturer_id,
        i.supervisor_id,
        lu.full_name AS lecturer_name,
        su.full_name AS supervisor_name,

        -- Lecturer assessment row
        la.total_score         AS lecturer_score,
        la.undertaking_tasks   AS l_undertaking_tasks,
        la.health_safety       AS l_health_safety,
        la.theoretical_knowledge AS l_theoretical_knowledge,
        la.report_presentation AS l_report_presentation,
        la.clarity_language    AS l_clarity_language,
        la.lifelong_learning   AS l_lifelong_learning,
        la.project_management  AS l_project_management,
        la.time_management     AS l_time_management,
        la.comments            AS lecturer_comments,

        -- Supervisor assessment row
        sa.total_score         AS supervisor_score,
        sa.undertaking_tasks   AS s_undertaking_tasks,
        sa.health_safety       AS s_health_safety,
        sa.theoretical_knowledge AS s_theoretical_knowledge,
        sa.report_presentation AS s_report_presentation,
        sa.clarity_language    AS s_clarity_language,
        sa.lifelong_learning   AS s_lifelong_learning,
        sa.project_management  AS s_project_management,
        sa.time_management     AS s_time_management,
        sa.comments            AS supervisor_comments

    FROM internships i
    JOIN students s ON i.student_id = s.student_id
    LEFT JOIN users lu ON i.lecturer_id  = lu.user_id
    LEFT JOIN users su ON i.supervisor_id = su.user_id
    LEFT JOIN assessments la ON i.internship_id = la.internship_id AND la.assessor_type = 'lecturer'
    LEFT JOIN assessments sa ON i.internship_id = sa.internship_id AND sa.assessor_type = 'supervisor'
    ORDER BY s.student_id ASC
";

$result = $conn->query($query);
$records = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $l_score = $row['lecturer_score']  !== null ? (float)$row['lecturer_score']  : null;
        $s_score = $row['supervisor_score'] !== null ? (float)$row['supervisor_score'] : null;

        $isUnassigned = empty($row['lecturer_id'])
            || empty($row['supervisor_id'])
            || empty($row['company_name']);

        $displayStatus = 'pending';
        if ($isUnassigned) {
            $displayStatus = 'unassigned';
        } elseif ($l_score !== null && $s_score !== null) {
            $displayStatus = 'completed';
        }

        // Final score = average of both; only available when both submitted and record is fully assigned
        $final_score = null;
        if ($displayStatus === 'completed') {
            $final_score = round(($l_score + $s_score) / 2, 2);
        }

        $records[] = [
            'internship_id'    => $row['internship_id'],
            'student_id'       => $row['student_id'],
            'full_name'        => $row['full_name'],
            'programme'        => $row['programme'],
            'company_name'     => $displayStatus === 'unassigned' ? null : $row['company_name'],
            'status'           => $displayStatus,

            // Names
            'lecturer_name'    => $displayStatus === 'unassigned' ? null : $row['lecturer_name'],
            'supervisor_name'  => $displayStatus === 'unassigned' ? null : $row['supervisor_name'],

            // Final averaged score (null until both submit)
            'total_score'      => $final_score,

            // Per-assessor scores (for admin breakdown modal)
            'lecturer_score'   => $l_score,
            'lecturer_comments' => $row['lecturer_comments'],
            'l_undertaking_tasks'     => $row['l_undertaking_tasks'],
            'l_health_safety'         => $row['l_health_safety'],
            'l_theoretical_knowledge' => $row['l_theoretical_knowledge'],
            'l_report_presentation'   => $row['l_report_presentation'],
            'l_clarity_language'      => $row['l_clarity_language'],
            'l_lifelong_learning'     => $row['l_lifelong_learning'],
            'l_project_management'    => $row['l_project_management'],
            'l_time_management'       => $row['l_time_management'],

            'supervisor_score'  => $s_score,
            'supervisor_comments' => $row['supervisor_comments'],
            's_undertaking_tasks'     => $row['s_undertaking_tasks'],
            's_health_safety'         => $row['s_health_safety'],
            's_theoretical_knowledge' => $row['s_theoretical_knowledge'],
            's_report_presentation'   => $row['s_report_presentation'],
            's_clarity_language'      => $row['s_clarity_language'],
            's_lifelong_learning'     => $row['s_lifelong_learning'],
            's_project_management'    => $row['s_project_management'],
            's_time_management'       => $row['s_time_management'],
        ];
    }
}

echo json_encode(['records' => $records]);
$conn->close();
?>