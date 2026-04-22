<?php
require_once 'auth.php';
requireRole('admin');
require_once 'config.php';

$conn = getConnection();

function scalar_query(mysqli $conn, string $sql): int {
    $result = mysqli_query($conn, $sql);
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        return (int)($row['total'] ?? 0);
    }
    return 0;
}

function get_dashboard_stats(mysqli $conn): array {
    return [
        'totalUsers' => scalar_query($conn, "SELECT COUNT(*) AS total FROM users"),
        'totalStudents' => scalar_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'student'"),
        'totalLecturers' => scalar_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'lecturer'"),
        'totalSupervisors' => scalar_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'supervisor'"),
        'totalAdmins' => scalar_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'"),
        'unassignedCount' => scalar_query($conn, "
            SELECT COUNT(*) AS total
            FROM internships
            WHERE lecturer_id IS NULL
               OR supervisor_id IS NULL
               OR company_name IS NULL
               OR start_date IS NULL
               OR end_date IS NULL
               OR status = 'unassigned'
        "),
        'pendingCount' => scalar_query($conn, "SELECT COUNT(*) AS total FROM internships WHERE status = 'pending'"),
        'completedCount' => scalar_query($conn, "SELECT COUNT(*) AS total FROM internships WHERE status = 'completed'"),
        'pendingEvaluationCount' => scalar_query($conn, "
            SELECT COUNT(*) AS total
            FROM internships i
            LEFT JOIN assessments lecturer_assessment
                ON lecturer_assessment.internship_id = i.internship_id
               AND lecturer_assessment.assessor_type = 'lecturer'
            LEFT JOIN assessments supervisor_assessment
                ON supervisor_assessment.internship_id = i.internship_id
               AND supervisor_assessment.assessor_type = 'supervisor'
            WHERE i.lecturer_id IS NOT NULL
              AND i.supervisor_id IS NOT NULL
              AND (lecturer_assessment.assessment_id IS NULL OR supervisor_assessment.assessment_id IS NULL)
        "),
    ];
}

function get_attention_items(array $stats): array {
    $unassigned = (int)$stats['unassignedCount'];
    $pending = (int)$stats['pendingCount'];

    return [
        [
            'title' => $unassigned . ' student' . ($unassigned === 1 ? '' : 's') . ' not assigned',
            'text' => $unassigned > 0
                ? 'There ' . ($unassigned === 1 ? 'is' : 'are') . ' still ' . $unassigned . ' student' . ($unassigned === 1 ? '' : 's') . ' without a complete lecturer, supervisor, or internship assignment.'
                : 'All students currently have complete lecturer, supervisor, and internship records.',
            'tag' => $unassigned > 0 ? 'Needs action' : 'Resolved',
            'tagClass' => $unassigned > 0 ? 'tag-danger' : 'tag-success',
        ],
        [
            'title' => $pending . ' evaluation' . ($pending === 1 ? '' : 's') . ' pending',
            'text' => $pending > 0
                ? 'Internship assessments are still waiting for lecturer/supervisor completion.'
                : 'All current internship assessments have been completed.',
            'tag' => $pending > 0 ? 'Pending' : 'Completed',
            'tagClass' => $pending > 0 ? 'tag-pending' : 'tag-success',
        ],
        [
            'title' => ($unassigned === 0 && $pending === 0) ? 'System status excellent' : 'System status normal',
            'text' => ($unassigned === 0 && $pending === 0)
                ? 'All major modules are available and no immediate admin follow-up is required.'
                : 'All major modules are available and user access is functioning properly.',
            'tag' => ($unassigned === 0 && $pending === 0) ? 'Excellent' : 'Normal',
            'tagClass' => 'tag-success',
        ],
    ];
}


function get_recent_activities(mysqli $conn, int $limit = 4): array {
    $items = [];
    $sql = "
        SELECT title, description, link_url, created_at
        FROM activity_logs
        ORDER BY created_at DESC, log_id DESC
        LIMIT ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $items[] = [
                    'title' => $row['title'],
                    'text' => $row['description'],
                    'link' => $row['link_url'] ?? '',
                ];
            }
        }
        $stmt->close();
    }

    return $items;
}

function get_pending_evaluations(mysqli $conn, int $limit = 5): array {
    $items = [];
    $sql = "
        SELECT
            s.full_name AS student_name,
            CASE
                WHEN l.full_name IS NOT NULL AND su.full_name IS NOT NULL THEN CONCAT(l.full_name, ' / ', su.full_name)
                WHEN l.full_name IS NOT NULL THEN l.full_name
                WHEN su.full_name IS NOT NULL THEN su.full_name
                ELSE '-'
            END AS assigned_staff_name,
            CASE
                WHEN lecturer_assessment.assessment_id IS NULL AND supervisor_assessment.assessment_id IS NULL THEN 'Awaiting both'
                WHEN lecturer_assessment.assessment_id IS NULL THEN 'Lecturer pending'
                WHEN supervisor_assessment.assessment_id IS NULL THEN 'Supervisor pending'
                ELSE 'Completed'
            END AS evaluation_status,
            GREATEST(
                COALESCE(lecturer_assessment.updated_at, '1970-01-01 00:00:00'),
                COALESCE(supervisor_assessment.updated_at, '1970-01-01 00:00:00'),
                COALESCE(i.updated_at, '1970-01-01 00:00:00')
            ) AS sort_updated_at,
            i.internship_id
        FROM internships i
        JOIN students s ON i.student_id = s.student_id
        LEFT JOIN users l ON i.lecturer_id = l.user_id
        LEFT JOIN users su ON i.supervisor_id = su.user_id
        LEFT JOIN assessments lecturer_assessment
            ON lecturer_assessment.internship_id = i.internship_id
           AND lecturer_assessment.assessor_type = 'lecturer'
        LEFT JOIN assessments supervisor_assessment
            ON supervisor_assessment.internship_id = i.internship_id
           AND supervisor_assessment.assessor_type = 'supervisor'
        WHERE i.lecturer_id IS NOT NULL
          AND i.supervisor_id IS NOT NULL
          AND (lecturer_assessment.assessment_id IS NULL OR supervisor_assessment.assessment_id IS NULL)
        ORDER BY
            CASE
                WHEN lecturer_assessment.assessment_id IS NULL AND supervisor_assessment.assessment_id IS NULL THEN 1
                WHEN lecturer_assessment.assessment_id IS NULL THEN 2
                WHEN supervisor_assessment.assessment_id IS NULL THEN 3
                ELSE 4
            END,
            sort_updated_at DESC,
            i.internship_id DESC
        LIMIT ?
    ";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $statusClass = 'status-pending';
            if ($row['evaluation_status'] === 'Awaiting both') {
                $statusClass = 'status-unassigned';
            } elseif ($row['evaluation_status'] === 'Supervisor pending') {
                $statusClass = 'status-info';
            }

            $items[] = [
                'student_name' => $row['student_name'],
                'assigned_staff_name' => $row['assigned_staff_name'],
                'status' => $row['evaluation_status'],
                'status_class' => $statusClass,
            ];
        }
        $stmt->close();
    }

    return $items;
}

$stats = get_dashboard_stats($conn);
$attentionItems = get_attention_items($stats);
$pendingEvaluations = get_pending_evaluations($conn, 5);
$recentActivities = get_recent_activities($conn, 4);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'dashboard') {
    header('Content-Type: application/json');
    echo json_encode([
        'stats' => get_dashboard_stats($conn),
        'attention' => get_attention_items(get_dashboard_stats($conn)),
        'pendingEvaluations' => get_pending_evaluations($conn, 5),
        'recentActivities' => get_recent_activities($conn, 4),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | IRMSYS</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:        #0e0f13;
    --surface:   #16181f;
    --surface2:  #1e2029;
    --border:    #2a2d38;
    --accent:    #4f8ef7;
    --accent2:   #7c6af7;
    --text:      #e8eaf0;
    --muted:     #6b7080;
    --success:   #34c97b;
    --warning:   #f0a030;
    --danger:    #e05555;
    --info:      #66d4f4;
    --radius:    10px;
    --font:      'Syne', sans-serif;
    --mono:      'DM Mono', monospace;
  }

  body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
  }

  .sidebar {
    width: 220px;
    flex-shrink: 0;
    background: var(--surface);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    padding: 24px 0;
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    overflow-y: auto;
  }

  .logo {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: var(--accent);
    padding: 0 20px 28px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 16px;
    text-transform: uppercase;
  }

  .logo span { color: var(--text); }

  .nav-label {
    font-size: 10px;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--muted);
    padding: 0 20px 8px;
  }

  .nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    font-size: 13.5px;
    font-weight: 500;
    color: var(--muted);
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: all 0.15s;
  }

  .nav-item:hover { color: var(--text); background: var(--surface2); }
  .nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(79,142,247,0.07); }

  .sidebar-footer {
    margin-top: auto;
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
  }

  .sidebar-user {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    min-width: 0;
  }

  .avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    flex-shrink: 0;
  }

  .user-name {
    font-size: 12px;
    font-weight: 500;
    color: rgba(232, 234, 240, 0.55);
    line-height: 1.3;
    white-space: nowrap;
  }

  .logout-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: transparent;
    color: #ff6b6b;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.15s ease;
    flex-shrink: 0;
  }

  .logout-btn:hover {
    background: rgba(224, 85, 85, 0.08);
    border-color: #e05555;
    color: #ff7b7b;
  }

  .main {
    margin-left: 220px;
    flex: 1;
    padding: 32px 36px;
    max-width: 1220px;
  }

  .page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 28px;
    gap: 16px;
  }

  .page-title { font-size: 24px; font-weight: 700; letter-spacing: -0.02em; }
  .page-sub { font-size: 13px; color: var(--muted); margin-top: 4px; }

  .stats-row {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 14px;
    margin-bottom: 28px;
  }

  .stat-card,
  .panel,
  .table-wrap,
  .mini-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
  }

  .stat-card {
    padding: 16px 18px;
    text-decoration: none;
    color: inherit;
    transition: all 0.15s;
    display: block;
  }

  .mini-card:hover,
  .quick-card:hover {
    border-color: var(--accent);
    background: rgba(79, 142, 247, 0.06);
    box-shadow: 0 0 0 1px rgba(79, 142, 247, 0.18);
    transform: translateY(-2px);
  }

  .quick-card:active {
    transform: translateY(-1px);
  }

  .stat-label {
    font-size: 11px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 8px;
  }

  .stat-value {
    font-size: 26px;
    font-weight: 700;
    font-family: var(--mono);
  }

  .blue { color: var(--accent); }
  .green { color: var(--success); }
  .amber { color: var(--warning); }
  .purple { color: var(--accent2); }
  .red { color: #ff8d8d; }
  .cyan { color: var(--info); }

  .content-grid,
  .bottom-grid {
    display: grid;
    gap: 18px;
    margin-bottom: 18px;
  }

  .content-grid {
    grid-template-columns: 1.5fr 1fr;
  }

  .bottom-grid {
    grid-template-columns: 1fr 1fr;
  }

  .panel {
    padding: 18px;
  }

  .panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
  }

  .panel-title {
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.02em;
  }

  .panel-sub {
    font-size: 12px;
    color: var(--muted);
    margin-top: 2px;
  }

  .btn {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 14px;
    border-radius: var(--radius);
    font-family: var(--font);
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--border);
    transition: all 0.15s;
    text-decoration: none;
    color: var(--text);
    background: var(--surface2);
  }

  .btn:hover { background: var(--border); }

  .quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
  }

  .quick-card,
  .mini-card,
  .alert-item,
  .activity-item {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
  }

  .quick-card {
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
  }

  .quick-card h4,
  .alert-item h4,
  .activity-item h4,
  .mini-card h4 {
    font-size: 15px;
    margin-bottom: 6px;
    font-weight: 700;
  }

  .quick-card p,
  .alert-item p,
  .activity-item p,
  .mini-card p {
    color: var(--muted);
    font-size: 12.5px;
    line-height: 1.55;
  }

  .alert-list,
  .activity-list,
  .mini-card-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .small-tag,
  .status-badge {
    display: inline-block;
    margin-top: 9px;
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.03em;
  }

  .tag-danger { background: rgba(224,85,85,0.12); color: var(--danger); }
  .tag-pending { background: rgba(240,160,48,0.12); color: var(--warning); }
  .tag-success { background: rgba(52,201,123,0.12); color: var(--success); }
  .status-pending { background: rgba(240,160,48,0.12); color: var(--warning); }
  .status-unassigned { background: rgba(124,106,247,0.14); color: var(--accent2); }
  .status-info { background: rgba(102,212,244,0.14); color: var(--info); }
  .status-completed { background: rgba(52,201,123,0.12); color: var(--success); }

  .table-wrap { overflow: hidden; }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
  }

  thead th {
    padding: 12px 16px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted);
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
  }

  tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background 0.12s;
  }

  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: var(--surface2); }
  tbody td { padding: 13px 16px; vertical-align: middle; }

  @media (max-width: 1200px) {
    .stats-row { grid-template-columns: repeat(3, 1fr); }
    .content-grid, .bottom-grid { grid-template-columns: 1fr; }
  }

  @media (max-width: 860px) {
    .sidebar { display: none; }
    .main { margin-left: 0; padding: 20px; }
    .stats-row, .quick-actions { grid-template-columns: 1fr; }
    .page-header { flex-direction: column; align-items: flex-start; }
  }
</style>
</head>
<body>

<nav class="sidebar">
  <div class="logo">IRM<span>sys</span></div>

  <div class="nav-label">Admin Panel</div>

  <a class="nav-item active" href="admin_dashboard.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    Dashboard
  </a>
  <a class="nav-item" href="user_management.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    User Management
  </a>
  <a class="nav-item" href="internship_list.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
    Internship Mgmt
  </a>
  <a class="nav-item" href="view_results.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    Results
  </a>
  </div>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar">AD</div>
      <div class="user-name">Admin User</div>
    </div>

    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</nav>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Welcome back, Admin</div>
      <div class="page-sub">Here is a quick overview of internship management system activities and current status</div>
    </div>
  </div>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-label">Total Users</div>
      <div class="stat-value blue" id="stat-totalUsers"><?php echo $stats['totalUsers']; ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Students</div>
      <div class="stat-value green" id="stat-totalStudents"><?php echo $stats['totalStudents']; ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Lecturers</div>
      <div class="stat-value amber" id="stat-totalLecturers"><?php echo $stats['totalLecturers']; ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Supervisors</div>
      <div class="stat-value amber" id="stat-totalSupervisors"><?php echo $stats['totalSupervisors']; ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Admin</div>
      <div class="stat-value purple" id="stat-totalAdmins"><?php echo $stats['totalAdmins']; ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Unassigned</div>
      <div class="stat-value amber" id="stat-unassignedCount"><?php echo $stats['unassignedCount']; ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Pending</div>
      <div class="stat-value red" id="stat-pendingCount"><?php echo $stats['pendingCount']; ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Completed</div>
      <div class="stat-value cyan" id="stat-completedCount"><?php echo $stats['completedCount']; ?></div>
    </div>
  </div>

  <section class="content-grid">
    <div class="panel">
      <div class="panel-header">
        <div>
          <div class="panel-title">Quick Actions</div>
          <div class="panel-sub">Open the main admin modules directly from dashboard</div>
        </div>
      </div>

      <div class="quick-actions">
        <a class="quick-card" href="user_management.php">
          <h4>Manage Users</h4>
          <p>Manage student, lecturer, supervisor, and admin accounts or update existing user information.</p>
        </a>

        <a class="quick-card" href="add_user.php">
          <h4>Add New User</h4>
          <p>Create a new student, lecturer, supervisor, or admin account directly from the user management module.</p>
        </a>

        <a class="quick-card" href="internship_list.php">
          <h4>Manage Internships</h4>
          <p>View, edit, and track all internship assignments and status records.</p>
        </a>

        <a class="quick-card" href="view_results.php">
          <h4>View Results</h4>
          <p>Review submitted evaluations and final assessment results for students.</p>
        </a>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <div>
          <div class="panel-title">Attention Needed</div>
          <div class="panel-sub">Items that still require admin follow-up</div>
        </div>
      </div>

      <div class="alert-list" id="attention-list">
        <?php foreach ($attentionItems as $item): ?>
          <div class="alert-item">
            <h4><?php echo htmlspecialchars($item['title']); ?></h4>
            <p><?php echo htmlspecialchars($item['text']); ?></p>
            <span class="small-tag <?php echo htmlspecialchars($item['tagClass']); ?>"><?php echo htmlspecialchars($item['tag']); ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="bottom-grid">
    <div class="panel">
      <div class="panel-header">
        <div>
          <div class="panel-title">Recent Activities</div>
          <div class="panel-sub">Recent admin-side system updates</div>
        </div>
        <a href="user_management.php" class="btn">Open Users</a>
      </div>

      <div class="activity-list" id="recent-activities-list">
        <?php if (!empty($recentActivities)): ?>
          <?php foreach ($recentActivities as $item): ?>
            <div class="activity-item">
              <h4><?php echo htmlspecialchars($item['title']); ?></h4>
              <p><?php echo htmlspecialchars($item['text']); ?></p>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="activity-item">
            <h4>No recent activity yet</h4>
            <p>Recent add, edit, delete, assignment, and result actions will appear here once recorded in the database.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-header">
        <div>
          <div class="panel-title">Pending Evaluations</div>
          <div class="panel-sub">Students whose lecturer/supervisor evaluation results are still pending.</div>
        </div>
        <a href="view_results.php" class="btn">Open Results</a>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Student</th>
              <th>Assigned Staff</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="pending-evaluations-body">
            <?php if (!empty($pendingEvaluations)): ?>
              <?php foreach ($pendingEvaluations as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['assigned_staff_name']); ?></td>
                  <td>
                    <span class="status-badge <?php echo htmlspecialchars($row['status_class']); ?>">
                      <?php echo htmlspecialchars($row['status']); ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="3" style="color: var(--muted);">No pending evaluation result records found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</main>

<script>
const DASHBOARD_AJAX_URL = 'admin_dashboard.php?ajax=dashboard';

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function updateStats(stats) {
  Object.entries(stats || {}).forEach(([key, value]) => {
    const el = document.getElementById(`stat-${key}`);
    if (el) el.textContent = value;
  });
}

function renderAttention(items) {
  const container = document.getElementById('attention-list');
  if (!container) return;
  container.innerHTML = (items || []).map(item => `
    <div class="alert-item">
      <h4>${escapeHtml(item.title)}</h4>
      <p>${escapeHtml(item.text)}</p>
      <span class="small-tag ${escapeHtml(item.tagClass)}">${escapeHtml(item.tag)}</span>
    </div>
  `).join('');
}

function renderPendingEvaluations(items) {
  const tbody = document.getElementById('pending-evaluations-body');
  if (!tbody) return;
  if (!items || !items.length) {
    tbody.innerHTML = '<tr><td colspan="3" style="color: var(--muted);">No pending evaluation result records found.</td></tr>';
    return;
  }

  tbody.innerHTML = items.map(item => {
    const badgeClass = item.status === 'unassigned' ? 'status-unassigned' : 'status-pending';
    const statusText = item.status ? item.status.charAt(0).toUpperCase() + item.status.slice(1) : '';
    return `
      <tr>
        <td>${escapeHtml(item.student_name)}</td>
        <td>${escapeHtml(item.assigned_staff_name)}</td>
        <td><span class="status-badge ${badgeClass}">${escapeHtml(statusText)}</span></td>
      </tr>
    `;
  }).join('');
}


function renderRecentActivities(items) {
  const container = document.getElementById('recent-activities-list');
  if (!container) return;
  if (!items || !items.length) {
    container.innerHTML = `
      <div class="activity-item">
        <h4>No recent activity yet</h4>
        <p>Recent add, edit, delete, assignment, and result actions will appear here once recorded in the database.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = items.map(item => `
    <div class="activity-item">
      <h4>${escapeHtml(item.title)}</h4>
      <p>${escapeHtml(item.text)}</p>
    </div>
  `).join('');
}

async function refreshDashboardData() {
  try {
    const response = await fetch(DASHBOARD_AJAX_URL, { cache: 'no-store' });
    if (!response.ok) throw new Error('Request failed');
    const data = await response.json();
    updateStats(data.stats || {});
    renderAttention(data.attention || []);
    renderPendingEvaluations(data.pendingEvaluations || []);
    renderRecentActivities(data.recentActivities || []);
  } catch (error) {
    console.error('Dashboard refresh failed:', error);
  }
}

setInterval(refreshDashboardData, 5000);
</script>
</body>
</html>