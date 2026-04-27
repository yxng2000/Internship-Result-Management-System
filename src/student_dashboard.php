<?php
require_once 'auth.php'; 
requireRole('student'); 
require_once 'config.php'; 

$conn = getConnection(); 

$user_id = (int)($_SESSION['user_id'] ?? 0); 
$full_name = $_SESSION['full_name'] ?? 'Student User'; 

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function get_initials($name) {
    $name = trim((string)$name);
    if ($name === '') return 'ST';
    $parts = preg_split('/\s+/', $name);
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: 'ST';
}

$sql = "
    SELECT
        s.student_id,
        s.full_name AS student_name,
        s.programme,
        s.email AS student_email,
        i.internship_id,
        i.company_name,
        i.industry,
        i.start_date,
        i.end_date,
        i.status,
        i.notes,
        i.updated_at,
        l.full_name AS lecturer_name,
        su.full_name AS supervisor_name
    FROM students s
    JOIN users u ON s.student_id = u.student_id
    LEFT JOIN internships i
        ON s.student_id = i.student_id
    LEFT JOIN users l
        ON i.lecturer_id = l.user_id
    LEFT JOIN users su
        ON i.supervisor_id = su.user_id
    WHERE u.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row) {
    $full_name = $row['student_name'] ?: $full_name;
}

$avatar = get_initials($full_name);

// Data variables with fallbacks
$student_id    = $row['student_id']    ?? '—';
$programme     = $row['programme']     ?? '—';
$student_email = $row['student_email'] ?? '—';
$company_name  = $row['company_name']  ?? null;
$industry      = $row['industry']      ?? null;
$status        = $row['status']        ?? 'unassigned';
$start_date    = $row['start_date']    ?? null;
$end_date      = $row['end_date']      ?? null;
$notes         = $row['notes']         ?? null;

// Explicitly defined roles for the UI
$lecturer_name   = $row['lecturer_name']   ?? 'Not assigned';
$supervisor_name = $row['supervisor_name'] ?? 'Not assigned';

/**
 * Date formatting helpers
 */
function format_date_display($date) {
    if (!$date) return '—';
    $ts = strtotime($date);
    return $ts ? date('d M Y', $ts) : '—';
}

function build_period($start, $end) {
    if (!$start || !$end) return 'Not available';
    return format_date_display($start) . ' – ' . format_date_display($end);
}

function calc_duration($start, $end) {
    if (!$start || !$end) return '—';
    $startDt = date_create($start);
    $endDt   = date_create($end);
    if (!$startDt || !$endDt || $endDt < $startDt) return '—';
    $diff = date_diff($startDt, $endDt);
    $months = ($diff->y * 12) + $diff->m;
    if ($diff->d > 0) $months++;
    return $months <= 0 ? 'Less than 1 month' : ($months === 1 ? '1 month' : $months . ' months');
}

$period_text   = build_period($start_date, $end_date);
$duration_text = calc_duration($start_date, $end_date);
$status_text   = ucfirst($status ?: 'unassigned');
$status_class  = 'status-' . ($status ?: 'unassigned');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg: #0e0f13; --surface: #16181f; --surface2: #1e2029; --border: #2a2d38;
    --accent: #4f8ef7; --accent2: #7c6af7; --text: #e8eaf0; --muted: #6b7080;
    --success: #34c97b; --warning: #f0a030; --danger: #e05555; --radius: 10px;
    --font: 'Syne', sans-serif; --mono: 'DM Mono', monospace;
  }
  body { font-family: var(--font); background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }
  
  /* Sidebar */
  .sidebar { width: 220px; flex-shrink: 0; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 24px 0; position: fixed; top: 0; left: 0; bottom: 0; }
  .logo { font-size: 15px; font-weight: 700; letter-spacing: 0.06em; color: var(--accent); padding: 0 20px 28px; border-bottom: 1px solid var(--border); margin-bottom: 16px; text-transform: uppercase; }
  .nav-label { font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); padding: 0 20px 8px; }
  .nav-item { display: flex; align-items: center; gap: 10px; padding: 10px 20px; font-size: 13.5px; font-weight: 500; color: var(--muted); cursor: pointer; border-left: 3px solid transparent; transition: all 0.15s; text-decoration: none; }
  .nav-item:hover { color: var(--text); background: var(--surface2); }
  .nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(79,142,247,0.07); }
  
  .sidebar-footer { margin-top: auto; padding: 16px 20px; border-top: 1px solid var(--border); display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; }
  .sidebar-user { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; min-width: 0; }
  .avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; }
  .user-name { font-size: 12px; font-weight: 500; color: rgba(232, 234, 240, 0.55); line-height: 1.3; white-space: nowrap; }
  .logout-btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 14px; border: 1px solid var(--border); border-radius: 10px; background: transparent; color: #ff6b6b; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.15s ease; flex-shrink: 0; }
  .logout-btn:hover { background: rgba(224, 85, 85, 0.08); border-color: #e05555; color: #ff7b7b; }

  /* Main Content */
  .main { margin-left: 220px; flex: 1; width: calc(100% - 220px); padding: 36px 56px; max-width: none; }
  .page-header, .stats-row, .content-grid { max-width: 1460px; margin-left: auto; margin-right: auto; }
  .page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; gap: 16px; }
  .page-title { font-size: 28px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 6px; }
  .page-sub { font-size: 13px; color: var(--muted); }

  .status-badge { display: inline-block; padding: 6px 12px; border-radius: 99px; font-size: 12px; font-weight: 600; letter-spacing: 0.04em; white-space: nowrap; }
  .status-completed { background: rgba(52,201,123,0.12); color: var(--success); }
  .status-pending { background: rgba(240,160,48,0.12); color: var(--warning); }
  .status-unassigned { background: rgba(107,112,128,0.12); color: var(--muted); }

  /* Dashboard Cards */
  .stats-row { display: grid; grid-template-columns: repeat(5, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
  .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 18px; }
  .stat-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px; }
  .stat-value { font-size: 18px; font-weight: 700; font-family: var(--mono); }

  .content-grid { display: grid; grid-template-columns: minmax(620px, 1.35fr) minmax(420px, 0.85fr); gap: 24px; align-items: stretch; }
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
  .card-title { font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); margin-bottom: 16px; }
  
  .detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 18px; margin-top: 18px; }
  .field { padding: 14px 16px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface2); }
  .field-label { font-size: 11px; color: var(--muted); text-transform: uppercase; margin-bottom: 8px; }
  .field-value { font-size: 14px; font-weight: 600; }
  
  .notes-box { margin-top: 18px; padding: 16px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface2); }
  .notes-text { color: var(--text); font-size: 14px; line-height: 1.6; }

  .student-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
  .student-name { font-size: 24px; font-weight: 700; margin-bottom: 8px; }
  .student-meta { font-size: 13px; color: var(--muted); margin-bottom: 6px; }
  .student-id { font-family: var(--mono); color: var(--accent); font-size: 12px; }
  .mini-avatar { width: 46px; height: 46px; border-radius: 14px; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; flex-shrink: 0; }
  .action-list { display: grid; gap: 14px; }
  .action-card { padding: 18px; background: var(--surface2); border: 1px solid var(--border); border-radius: 10px; text-decoration: none; color: inherit; display: block; transition: all 0.18s ease; }
  .action-card:hover { border-color: var(--accent); transform: translateY(-2px); background: #222530; }
  .action-title { font-weight: 700; margin-bottom: 8px; }
  .action-sub { font-size: 13px; color: var(--muted); line-height: 1.5; }
  @media (max-width: 980px) {
    .stats-row { grid-template-columns: repeat(2, 1fr); }
    .content-grid, .detail-grid { grid-template-columns: 1fr; }
    .main { margin-left: 220px; width: calc(100% - 220px); padding: 24px 20px; }
  }
</style>
</head>
<body>

<nav class="sidebar">
  <div class="logo">IRM<span>sys</span></div>
  <div class="nav-label">Student Panel</div>
  <a class="nav-item active" href="student_dashboard.php">Dashboard</a>
  <a class="nav-item" href="student_view_internship.php">View Internship</a>
  <a class="nav-item" href="student_view_result.php">View Result</a>
  
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar"><?= e($avatar) ?></div>
      <div class="user-name"><?= e($full_name) ?></div>
    </div>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</nav>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Welcome back, <?= e($full_name) ?></div>
      <div class="page-sub">Overview of your internship and evaluation progress.</div>
    </div>
    <span class="status-badge <?= e($status_class) ?>"><?= e($status_text) ?></span>
  </div>

  <section class="stats-row">
    <div class="stat-card">
      <div class="stat-label">Student ID</div>
      <div class="stat-value" style="color:var(--accent);"><?= e($student_id) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Company</div>
      <div class="stat-value" style="color:var(--success);"><?= e($company_name ?: '—') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Lecturer</div>
      <div class="stat-value" style="color:var(--warning);"><?= e($lecturer_name) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Supervisor</div>
      <div class="stat-value" style="color:var(--accent2);"><?= e($supervisor_name) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Duration</div>
      <div class="stat-value" style="color:var(--accent2);"><?= e($duration_text) ?></div>
    </div>
  </section>

  <section class="content-grid">
    <div class="card">
      <div class="card-title">Student Profile</div>
      <div class="student-head">
        <div>
          <div class="student-name"><?= e($full_name) ?></div>
          <div class="student-meta"><?= e($programme) ?></div>
          <div class="student-id"><?= e($student_id) ?></div>
        </div>
        <div class="mini-avatar"><?= e($avatar) ?></div>
      </div>

      <div class="detail-grid">
        <div class="field">
          <div class="field-label">Programme</div>
          <div class="field-value"><?= e($programme) ?></div>
        </div>
        <div class="field">
          <div class="field-label">Student Email</div>
          <div class="field-value"><?= e($student_email) ?></div>
        </div>
        <div class="field">
          <div class="field-label">Industry</div>
          <div class="field-value"><?= e($industry ?: 'Not available') ?></div>
        </div>
        <div class="field">
          <div class="field-label">Internship Period</div>
          <div class="field-value"><?= e($period_text) ?></div>
        </div>
      </div>

      <div class="notes-box">
        <div class="field-label" style="font-size:11px; color:var(--muted); text-transform:uppercase; margin-bottom:10px;">Placement Remarks</div>
        <div class="notes-text"><?= e($notes ?: 'No notes available.') ?></div>
      </div>
    </div>
    
    <div class="card">
      <div class="card-title">Quick Actions</div>
      <div class="action-list">
        <a href="student_view_internship.php" class="action-card">
          <div class="action-title">View Internship</div>
          <div class="action-sub">Open full placement details, company information, internship period, and remarks.</div>
        </a>
        <a href="student_view_result.php" class="action-card">
          <div class="action-title">View Result</div>
          <div class="action-sub">Check evaluation scores, final grade, and assessor feedback.</div>
        </a>
      </div>
    </div>
  </section>
</main>

</body>
</html>