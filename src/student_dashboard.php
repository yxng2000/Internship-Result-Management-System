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
        au.full_name AS assessor_name
    FROM students s
    JOIN users u ON s.student_id = u.student_id
    LEFT JOIN internships i
        ON s.student_id = i.student_id
    LEFT JOIN users au
        ON i.assessor_id = au.user_id
    WHERE u.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL PREPARE ERROR: " . $conn->error);
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    die("SQL EXECUTE ERROR: " . $stmt->error);
}

$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row) {
    $full_name = $row['student_name'] ?: $full_name;
}

$avatar = get_initials($full_name);

$student_id    = $row['student_id']    ?? '—';
$programme     = $row['programme']     ?? '—';
$student_email = $row['student_email'] ?? '—';
$company_name  = $row['company_name']  ?? null;
$industry      = $row['industry']      ?? null;
$assessor_name = $row['assessor_name'] ?? null;
$status        = $row['status']        ?? 'unassigned';
$start_date    = $row['start_date']    ?? null;
$end_date      = $row['end_date']      ?? null;
$notes         = $row['notes']         ?? null;

function format_date_display($date) {
    if (!$date) return '—';
    $ts = strtotime($date);
    if (!$ts) return '—';
    return date('d M Y', $ts);
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

    if ($months <= 0) return 'Less than 1 month';
    if ($months === 1) return '1 month';
    return $months . ' months';
}

function status_label($status) {
    if (!$status) return 'Unassigned';
    return ucfirst($status);
}

function status_class($status) {
    if ($status === 'completed') return 'status-completed';
    if ($status === 'pending') return 'status-pending';
    return 'status-unassigned';
}

$period_text   = build_period($start_date, $end_date);
$duration_text = calc_duration($start_date, $end_date);
$status_text   = status_label($status);
$status_class  = status_class($status);
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
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: all 0.15s;
    text-decoration: none;
  }

  .nav-item:hover {
    color: var(--text);
    background: var(--surface2);
  }

  .nav-item.active {
    color: var(--accent);
    border-left-color: var(--accent);
    background: rgba(79,142,247,0.07);
  }

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

  .page-title {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -0.02em;
    margin-bottom: 6px;
  }

  .page-sub {
    font-size: 13px;
    color: var(--muted);
  }

  .status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    white-space: nowrap;
  }

  .status-completed {
    background: rgba(52,201,123,0.12);
    color: var(--success);
  }

  .status-pending {
    background: rgba(240,160,48,0.12);
    color: var(--warning);
  }

  .status-unassigned {
    background: rgba(107,112,128,0.12);
    color: var(--muted);
  }

  .stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
  }

  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px;
  }

  .stat-label {
    font-size: 11px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 8px;
  }

  .stat-value {
    font-size: 23px;
    font-weight: 700;
    font-family: var(--mono);
  }

  .stat-value.blue { color: var(--accent); }
  .stat-value.green { color: var(--success); }
  .stat-value.amber { color: var(--warning); }
  .stat-value.purple { color: var(--accent2); }

  .content-grid {
    display: grid;
    grid-template-columns: 1.08fr 0.92fr;
    gap: 18px;
  }

  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px;
  }

  .card-title {
    font-size: 12px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--muted);
    margin-bottom: 16px;
  }

  .student-name {
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 8px;
  }

  .student-meta {
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 6px;
  }

  .student-id {
    font-family: var(--mono);
    font-size: 12px;
    color: var(--accent);
    margin-bottom: 12px;
  }

  .info-list {
    display: grid;
    gap: 12px;
  }

  .info-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
  }

  .info-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
  }

  .info-label {
    color: var(--muted);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .info-value {
    font-size: 14px;
    font-weight: 600;
    text-align: right;
    word-break: break-word;
  }

  .action-list {
    display: grid;
    gap: 14px;
  }

  .action-card {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 18px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.15s ease;
  }

  .action-card:hover {
    border-color: rgba(79,142,247,0.35);
    transform: translateY(-1px);
  }

  .action-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 8px;
  }

  .action-desc {
    font-size: 13px;
    line-height: 1.6;
    color: var(--muted);
  }

  .detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-top: 18px;
  }

  .field {
    padding: 14px 16px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface2);
  }

  .field-label {
    font-size: 11px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 8px;
  }

  .field-value {
    font-size: 14px;
    font-weight: 600;
    line-height: 1.45;
  }

  .field-value.mono {
    font-family: var(--mono);
    font-size: 13px;
  }

  .notes-box {
    margin-top: 18px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface2);
  }

  .notes-text {
    color: var(--text);
    font-size: 14px;
    line-height: 1.6;
  }

  @media (max-width: 980px) {
    .stats-row,
    .content-grid,
    .detail-grid {
      grid-template-columns: 1fr;
    }

    .main {
      padding: 24px 20px;
    }
  }
</style>
</head>
<body>

<nav class="sidebar">
  <div class="logo">IRM<span>sys</span></div>

  <div class="nav-label">Student Panel</div>

  <a class="nav-item active" href="student_dashboard.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <rect x="3" y="3" width="7" height="7" rx="1"/>
      <rect x="14" y="3" width="7" height="7" rx="1"/>
      <rect x="3" y="14" width="7" height="7" rx="1"/>
      <rect x="14" y="14" width="7" height="7" rx="1"/>
    </svg>
    Dashboard
  </a>

  <a class="nav-item" href="student_view_internship.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <rect x="2" y="7" width="20" height="14" rx="2"/>
      <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
    </svg>
    View Internship
  </a>

  <a class="nav-item" href="student_view_result.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
      <polyline points="14 2 14 8 20 8"/>
      <line x1="16" y1="13" x2="8" y2="13"/>
      <line x1="16" y1="17" x2="8" y2="17"/>
      <polyline points="10 9 9 9 8 9"/>
    </svg>
    View Result
  </a>

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
      <div class="page-sub">Here is a quick overview of your internship placement and evaluation progress.</div>
    </div>
    <span class="status-badge <?= e($status_class) ?>"><?= e($status_text) ?></span>
  </div>

  <section class="stats-row">
    <div class="stat-card">
      <div class="stat-label">Student ID</div>
      <div class="stat-value blue"><?= e($student_id) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Company</div>
      <div class="stat-value green"><?= e($company_name ?: '—') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Assessor</div>
      <div class="stat-value amber"><?= e($assessor_name ?: '—') ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Duration</div>
      <div class="stat-value purple"><?= e($duration_text) ?></div>
    </div>
  </section>

  <section class="content-grid">
    <div class="card">
      <div class="card-title">Student Profile</div>
      <div class="student-name"><?= e($full_name) ?></div>
      <div class="student-meta"><?= e($programme) ?></div>
      <div class="student-id"><?= e($student_id) ?></div>

      <div class="info-list">
        <div class="info-row">
          <span class="info-label">Student Email</span>
          <span class="info-value"><?= e($student_email) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Internship Status</span>
          <span class="info-value"><?= e($status_text) ?></span>
        </div>
      </div>

      <div class="detail-grid">
        <div class="field">
          <div class="field-label">Industry</div>
          <div class="field-value"><?= e($industry ?: 'Not available') ?></div>
        </div>

        <div class="field">
          <div class="field-label">Internship Period</div>
          <div class="field-value"><?= e($period_text) ?></div>
        </div>

        <div class="field">
          <div class="field-label">Company</div>
          <div class="field-value"><?= e($company_name ?: 'Not assigned') ?></div>
        </div>

        <div class="field">
          <div class="field-label">Assessor</div>
          <div class="field-value"><?= e($assessor_name ?: 'Not assigned') ?></div>
        </div>
      </div>

      <div class="notes-box">
        <div class="field-label" style="margin-bottom:10px;">Placement Remarks</div>
        <div class="notes-text"><?= e($notes ?: 'No notes available.') ?></div>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Quick Actions</div>

      <div class="action-list">
        <a href="student_view_internship.php" class="action-card">
          <div class="action-title">View Internship</div>
          <div class="action-desc">
            Check your assigned company, assessor, internship duration, and any placement remarks.
          </div>
        </a>

        <a href="student_view_result.php" class="action-card">
          <div class="action-title">View Result</div>
          <div class="action-desc">
            See your internship assessment result and feedback once your evaluator has submitted it.
          </div>
        </a>
      </div>
    </div>
  </section>
</main>

</body>
</html>