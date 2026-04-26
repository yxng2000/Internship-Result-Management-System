<?php
session_start();
require_once 'auth.php';
requireRole(['lecturer', 'supervisor']);
require_once 'config.php';

$conn = getConnection();

$user_id = (int)($_SESSION['user_id'] ?? 0);
$full_name = $_SESSION['full_name'] ?? 'Assessor User';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function get_initials($name) {
    $name = preg_replace('/\b(Dr|Prof|Mr|Mrs|Ms|Miss)\.?\s*/i', '', trim($name));
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: 'AU';
}

if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? AND role IN ('lecturer', 'supervisor') LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (!empty($row['full_name'])) {
                    $full_name = $row['full_name'];
                }
            }
        }
        $stmt->close();
    }
}

$avatar = get_initials($full_name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Students' Results — Assessor Panel</title>
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

  body { font-family: var(--font); background: var(--bg); color: var(--text); min-height: 100vh; display: flex; }

  .sidebar { width: 220px; flex-shrink: 0; background: var(--surface); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 24px 0; position: fixed; top: 0; left: 0; bottom: 0; }
  .logo { font-size: 15px; font-weight: 700; letter-spacing: 0.06em; color: var(--accent); padding: 0 20px 28px; border-bottom: 1px solid var(--border); margin-bottom: 16px; text-transform: uppercase; }
  .logo span { color: var(--text); }
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

  .main { margin-left: 220px; flex: 1; padding: 32px 36px; max-width: calc(100% - 220px); width: calc(100% - 220px); }

  .page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; }
  .page-title { font-size: 24px; font-weight: 700; letter-spacing: -0.02em; }
  .page-sub { font-size: 13px; color: var(--muted); margin-top: 4px; }

  .btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: var(--radius); font-family: var(--font); font-size: 13.5px; font-weight: 600; cursor: pointer; border: none; transition: all 0.15s; text-decoration: none; }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: #3d7ef5; }
  .btn-ghost { background: var(--surface2); color: var(--text); border: 1px solid var(--border); }
  .btn-ghost:hover { background: var(--border); }
  .btn-sm { padding: 6px 12px; font-size: 12px; }

  .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 28px; }
  .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 18px; }
  .stat-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 8px; }
  .stat-value { font-size: 26px; font-weight: 700; font-family: var(--mono); }
  .stat-value.blue   { color: var(--accent); }
  .stat-value.green  { color: var(--success); }
  .stat-value.amber  { color: var(--warning); }
  .stat-value.purple { color: var(--accent2); }

  .toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
  .search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 420px; }
  .search-wrap svg { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--muted); }
  .search-input { width: 100%; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--font); font-size: 13.5px; padding: 9px 12px 9px 36px; outline: none; transition: border-color 0.15s; }
  .search-input::placeholder { color: var(--muted); }
  .search-input:focus { border-color: var(--accent); }
  .filter-select { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text); font-family: var(--font); font-size: 13px; padding: 9px 14px; outline: none; cursor: pointer; }
  .filter-select:focus { border-color: var(--accent); }

  .table-wrap { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
  table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  thead th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); background: var(--surface2); border-bottom: 1px solid var(--border); white-space: nowrap; }
  tbody tr { border-bottom: 1px solid var(--border); transition: background 0.12s; cursor: pointer; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: var(--surface2); }
  tbody td { padding: 13px 16px; vertical-align: middle; }

  .student-cell { display: flex; flex-direction: column; gap: 2px; }
  .student-id { font-family: var(--mono); font-size: 11px; color: var(--muted); }

  .score-cell { font-family: var(--mono); font-weight: 700; font-size: 14px; }
  .score-high { color: var(--success); }
  .score-mid  { color: var(--warning); }
  .score-low  { color: var(--danger); }
  .score-none { color: var(--muted); }

  .grade-badge { display: inline-block; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; font-family: var(--mono); }
  .grade-A { background: rgba(52,201,123,0.12); color: var(--success); }
  .grade-B { background: rgba(79,142,247,0.12); color: var(--accent); }
  .grade-C { background: rgba(240,160,48,0.12); color: var(--warning); }
  .grade-D { background: rgba(224,85,85,0.12); color: var(--danger); }
  .grade-F { background: rgba(107,112,128,0.12); color: var(--muted); }
  .grade-none { background: rgba(107,112,128,0.08); color: var(--muted); }

  .status-badge { display: inline-block; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; letter-spacing: 0.04em; }
  .status-completed  { background: rgba(52,201,123,0.12); color: var(--success); }
  .status-pending    { background: rgba(240,160,48,0.12); color: var(--warning); }

  .score-bar-wrap { width: 80px; height: 4px; background: var(--border); border-radius: 99px; margin-top: 4px; }
  .score-bar { height: 4px; border-radius: 99px; }

  .no-results { text-align: center; padding: 48px 16px; color: var(--muted); font-size: 14px; display: none; }

  .pagination { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-top: 1px solid var(--border); font-size: 12.5px; color: var(--muted); }
  .page-btns { display: flex; gap: 6px; }
  .page-btn { padding: 5px 11px; border-radius: 6px; border: 1px solid var(--border); background: transparent; color: var(--muted); font-family: var(--font); font-size: 12px; cursor: pointer; transition: all 0.15s; }
  .page-btn:hover, .page-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

  .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); z-index: 200; align-items: center; justify-content: center; padding: 24px; }
  .modal-backdrop.visible { display: flex; }
  .modal { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
  .modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid var(--border); }
  .modal-title { font-size: 16px; font-weight: 700; }
  .modal-close { background: transparent; border: none; color: var(--muted); cursor: pointer; font-size: 18px; line-height: 1; padding: 4px; transition: color 0.15s; }
  .modal-close:hover { color: var(--text); }
  .modal-body { padding: 24px; }

  .modal-student-banner { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
  .modal-avatar { width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0; }
  .modal-student-name { font-size: 15px; font-weight: 600; }
  .modal-student-meta { font-size: 12px; color: var(--muted); margin-top: 3px; font-family: var(--mono); }
  .modal-total { margin-left: auto; text-align: right; }
  .modal-total-val { font-size: 28px; font-weight: 700; font-family: var(--mono); }
  .modal-total-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; }

  .breakdown-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border); }
  .breakdown-row:last-child { border-bottom: none; }
  .breakdown-name { font-size: 13px; }
  .breakdown-weight { font-size: 11px; color: var(--muted); margin-top: 2px; }
  .breakdown-right { display: flex; align-items: center; gap: 12px; }
  .breakdown-score { font-family: var(--mono); font-weight: 700; font-size: 14px; }
  .breakdown-bar-wrap { width: 80px; height: 4px; background: var(--border); border-radius: 99px; }
  .breakdown-bar { height: 4px; border-radius: 99px; background: var(--accent); }

  .comments-box { background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; padding: 14px 16px; margin-top: 16px; font-size: 13px; line-height: 1.7; color: var(--muted); }
  .comments-label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); margin-bottom: 8px; font-weight: 600; }

  .toast { position: fixed; bottom: 24px; right: 24px; background: var(--surface2); border: 1px solid var(--border); border-left: 3px solid var(--success); border-radius: var(--radius); padding: 12px 18px; font-size: 13px; color: var(--text); opacity: 0; transform: translateY(10px); transition: all 0.25s; pointer-events: none; z-index: 999; }
  .toast.show { opacity: 1; transform: translateY(0); }
</style>
</head>
<body>

<nav class="sidebar">
  <div class="logo">IRM<span>sys</span></div>
  <div class="nav-label">Assessor Panel</div>

  <a class="nav-item" href="assessor_dashboard.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    Dashboard
  </a>
  <a class="nav-item" href="result_entry.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    Enter Results
  </a>
  <a class="nav-item active" href="assessor_view_results.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    View Results
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
      <div class="page-title">My Students' Results</div>
      <div class="page-sub">View your scores, check if your partner has submitted, and see the final grade. Click any row to view details.</div>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="exportCSV()">
      <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </button>
  </div>

  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-label">My Students</div>
      <div class="stat-value blue" id="stat-total">0</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">My Assessments</div>
      <div class="stat-value green" id="stat-assessed">0</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Partner Awaiting</div>
      <div class="stat-value amber" id="stat-pending-partner">0</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Fully Completed</div>
      <div class="stat-value purple" id="stat-fully-completed">0</div>
    </div>
  </div>

  <div class="toolbar">
    <div class="search-wrap">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input class="search-input" type="text" id="searchInput" placeholder="Search by student ID or name…">
    </div>
    <select class="filter-select" id="statusFilter">
      <option value="all">All Status</option>
      <option value="completed">Fully Completed (Both)</option>
      <option value="pending_partner">Pending Partner</option>
      <option value="pending_me">Pending My Assessment</option>
    </select>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Programme</th>
          <th>Company</th>
          <th>My Score</th>
          <th>Partner Status</th>
          <th>Final Grade</th>
        </tr>
      </thead>
      <tbody id="tableBody"></tbody>
    </table>
    <div class="no-results" id="noResults">No records match your search.</div>
    <div class="pagination">
      <span id="pageInfo">Showing 0 of 0 records</span>
      <div class="page-btns" id="pageBtns"></div>
    </div>
  </div>

</main>

<!-- Detail Modal -->
<div class="modal-backdrop" id="detailModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">My Assessment Breakdown</div>
      <div style="display: flex; gap: 16px; align-items: center;">
        <button id="modalEditBtn" class="btn btn-primary btn-sm" style="display: none;">
          <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          Edit Score
        </button>
        <button class="modal-close" onclick="closeModal()">✕</button>
      </div>
    </div>
    <div class="modal-body">

      <div class="modal-student-banner">
        <div class="modal-avatar" id="m-avatar">--</div>
        <div>
          <div class="modal-student-name" id="m-name">—</div>
          <div class="modal-student-meta" id="m-meta">—</div>
        </div>
        <div class="modal-total">
          <div class="modal-total-val" id="m-total">—</div>
          <div class="modal-total-label" id="m-total-label">Final Score / 100</div>
        </div>
      </div>

      <div id="m-breakdown"></div>

      <div id="m-comments-wrap" style="display:none;">
        <div class="comments-label" style="margin-top:20px;">Your Comments</div>
        <div class="comments-box" id="m-comments"></div>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  const FETCH_URL = 'get_assessor_results.php?t=' + new Date().getTime();

  const ROWS_PER_PAGE = 10;
  let currentPage = 1;
  let data = [];

  const CRITERIA_META = [
    { key: 'undertaking_tasks',     label: 'Undertaking Tasks',     max: 10, weight: '10%' },
    { key: 'health_safety',         label: 'Health & Safety',       max: 10, weight: '10%' },
    { key: 'theoretical_knowledge', label: 'Theoretical Knowledge', max: 10, weight: '10%' },
    { key: 'report_presentation',   label: 'Report & Presentation', max: 15, weight: '15%' },
    { key: 'clarity_language',      label: 'Clarity & Language',    max: 10, weight: '10%' },
    { key: 'lifelong_learning',     label: 'Lifelong Learning',     max: 15, weight: '15%' },
    { key: 'project_management',    label: 'Project Management',    max: 15, weight: '15%' },
    { key: 'time_management',       label: 'Time Management',       max: 15, weight: '15%' },
  ];

  function safeNum(val) {
    if (val === null || val === undefined || val === '') return null;
    const num = parseFloat(val);
    return isNaN(num) ? null : num;
  }

  function getGrade(score) {
    const s = safeNum(score);
    if (s === null) return null;
    if (s >= 80) return 'A';
    if (s >= 70) return 'B';
    if (s >= 60) return 'C';
    if (s >= 50) return 'D';
    return 'F';
  }

  function getScoreClass(score) {
    const s = safeNum(score);
    if (s === null) return 'score-none';
    if (s >= 70) return 'score-high';
    if (s >= 50) return 'score-mid';
    return 'score-low';
  }

  function getBarColor(pct) {
    if (pct >= 70) return 'var(--success)';
    if (pct >= 50) return 'var(--warning)';
    return 'var(--danger)';
  }

  function getInitials(name) {
    if (!name) return '--';
    return name.split(' ').map(p => p[0]).join('').substring(0, 2).toUpperCase();
  }

  function loadResults() {
    fetch(FETCH_URL)
      .then(r => r.json())
      .then(result => {
        data = result.records || [];
        updateStats();
        render();
      })
      .catch(err => console.error('Failed to load results:', err));
  }

  function updateStats() {
    let myAssessedCount = 0;
    let fullyCompletedCount = 0;
    let pendingPartnerCount = 0;

    data.forEach(r => {
      const iHaveAssessed    = safeNum(r.total_score)       !== null;
      const partnerHasAssessed = safeNum(r.other_total_score) !== null;
      if (iHaveAssessed) myAssessedCount++;
      if (iHaveAssessed && partnerHasAssessed)  fullyCompletedCount++;
      if (iHaveAssessed && !partnerHasAssessed) pendingPartnerCount++;
    });

    document.getElementById('stat-total').textContent           = data.length;
    document.getElementById('stat-assessed').textContent        = myAssessedCount;
    document.getElementById('stat-pending-partner').textContent = pendingPartnerCount;
    document.getElementById('stat-fully-completed').textContent = fullyCompletedCount;
  }

  function getCustomStatus(r) {
    const iHaveAssessed      = safeNum(r.total_score)       !== null;
    const partnerHasAssessed = safeNum(r.other_total_score) !== null;
    if (iHaveAssessed && partnerHasAssessed)  return 'completed';
    if (iHaveAssessed && !partnerHasAssessed) return 'pending_partner';
    return 'pending_me';
  }

  function filteredData() {
    const q  = document.getElementById('searchInput').value.toLowerCase();
    const sf = document.getElementById('statusFilter').value;
    return data.filter(r => {
      const matchQ      = (r.student_id || '').toLowerCase().includes(q) || (r.full_name || '').toLowerCase().includes(q);
      const subStatus   = getCustomStatus(r);
      const matchS      = sf === 'all' || subStatus === sf;
      return matchQ && matchS;
    });
  }

  function render() {
    const rows = filteredData();
    const total = rows.length;
    const totalPages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
    if (currentPage > totalPages) currentPage = 1;

    const slice = rows.slice((currentPage - 1) * ROWS_PER_PAGE, currentPage * ROWS_PER_PAGE);
    const tbody = document.getElementById('tableBody');

    if (slice.length === 0) {
      tbody.innerHTML = '';
      document.getElementById('noResults').style.display = 'block';
    } else {
      document.getElementById('noResults').style.display = 'none';
      tbody.innerHTML = slice.map(r => {
        const myScoreValue   = safeNum(r.total_score);
        const myScore        = myScoreValue !== null ? myScoreValue.toFixed(1) : null;
        const partnerAssessed = safeNum(r.other_total_score) !== null;
        const finalScoreValue = safeNum(r.final_score);
        const finalScore      = finalScoreValue !== null ? finalScoreValue.toFixed(1) : null;
        const grade           = getGrade(r.final_score);
        const scoreClass      = getScoreClass(r.total_score);
        const barPct          = myScore ? Math.min(parseFloat(myScore), 100) : 0;
        const barColor        = getBarColor(barPct);

        return `
          <tr onclick='openModal(${JSON.stringify(r).replace(/'/g, "\\'")})'>
            <td>
              <div class="student-cell">
                <span>${r.full_name || '—'}</span>
                <span class="student-id">${r.student_id || '—'}</span>
              </div>
            </td>
            <td>${r.programme || '—'}</td>
            <td>${r.company_name || '<span style="color:var(--muted);font-size:12px">—</span>'}</td>
            <td>
              ${myScore !== null
                ? `<div class="${scoreClass}" style="font-family:var(--mono);font-weight:700;">${myScore}</div>
                   <div class="score-bar-wrap"><div class="score-bar" style="width:${barPct}%;background:${barColor}"></div></div>`
                : `<span style="color:var(--muted);font-size:12px;">Pending</span>`
              }
            </td>
            <td>
              <span class="status-badge ${partnerAssessed ? 'status-completed' : 'status-pending'}">
                ${partnerAssessed ? 'Assessed' : 'Pending'}
              </span>
            </td>
            <td>
              ${finalScore !== null
                ? `<div style="font-weight:700; font-family:var(--mono); font-size:15px; display:flex; align-items:center; gap:8px;">${finalScore} <span class="grade-badge grade-${grade}">${grade}</span></div>`
                : `<span style="color:var(--muted);font-size:12px;">Awaiting Partner</span>`
              }
            </td>
          </tr>
        `;
      }).join('');
    }

    document.getElementById('pageInfo').textContent =
      `Showing ${total === 0 ? 0 : (currentPage-1)*ROWS_PER_PAGE+1}–${Math.min(currentPage*ROWS_PER_PAGE, total)} of ${total} records`;

    const pageBtns = document.getElementById('pageBtns');
    pageBtns.innerHTML = '';
    for (let i = 1; i <= totalPages; i++) {
      const btn = document.createElement('button');
      btn.className = 'page-btn' + (i === currentPage ? ' active' : '');
      btn.textContent = i;
      btn.onclick = () => { currentPage = i; render(); };
      pageBtns.appendChild(btn);
    }
  }

  function openModal(r) {
    const myScoreAssessed = safeNum(r.total_score) !== null;
    const bothAssessed    = safeNum(r.final_score) !== null;

    const modalEditBtn = document.getElementById('modalEditBtn');
    modalEditBtn.style.display = 'inline-flex';
    modalEditBtn.onclick = () => {
      window.location.href = `result_entry.php?edit=${r.internship_id}`;
    };
    modalEditBtn.innerHTML = myScoreAssessed
      ? `<svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit Score`
      : `<svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Enter Score`;

    document.getElementById('m-avatar').textContent = getInitials(r.full_name);
    document.getElementById('m-name').textContent   = r.full_name || '—';
    document.getElementById('m-meta').textContent   = `${r.student_id || '—'} · ${r.programme || '—'} · ${r.company_name || '—'}`;

    const totalEl    = document.getElementById('m-total');
    const totalLabel = document.getElementById('m-total-label');

    if (bothAssessed) {
      const fScore = parseFloat(r.final_score).toFixed(1);
      const fGrade = getGrade(r.final_score);
      totalEl.textContent    = fScore;
      totalEl.className      = 'modal-total-val ' + getScoreClass(r.final_score);
      totalLabel.textContent = `FINAL SCORE / 100  ·  Grade ${fGrade}`;
    } else if (myScoreAssessed) {
      totalEl.textContent    = parseFloat(r.total_score).toFixed(1);
      totalEl.className      = 'modal-total-val score-mid';
      totalLabel.textContent = 'YOUR SCORE (Awaiting Partner)';
    } else {
      totalEl.textContent    = '—';
      totalEl.className      = 'modal-total-val score-none';
      totalLabel.textContent = 'Not yet assessed by anyone';
    }

    const breakdownEl = document.getElementById('m-breakdown');
    if (myScoreAssessed) {
      breakdownEl.innerHTML = CRITERIA_META.map(c => {
        const val = safeNum(r[c.key]) ?? 0;
        const pct = (val / c.max) * 100;
        return `
          <div class="breakdown-row">
            <div>
              <div class="breakdown-name">${c.label}</div>
              <div class="breakdown-weight">${c.weight} · max ${c.max} pts</div>
            </div>
            <div class="breakdown-right">
              <div class="breakdown-bar-wrap">
                <div class="breakdown-bar" style="width:${pct}%;background:${getBarColor(pct)}"></div>
              </div>
              <div class="breakdown-score" style="color:${getBarColor(pct)}">${val.toFixed(1)}</div>
            </div>
          </div>
        `;
      }).join('');
    } else {
      breakdownEl.innerHTML = '<div style="text-align:center;padding:32px;color:var(--muted);font-size:13px;">You have not submitted an assessment for this student yet.</div>';
    }

    const commentsWrap = document.getElementById('m-comments-wrap');
    if (myScoreAssessed && r.comments) {
      document.getElementById('m-comments').textContent = r.comments;
      commentsWrap.style.display = 'block';
    } else {
      commentsWrap.style.display = 'none';
    }

    document.getElementById('detailModal').classList.add('visible');
  }

  function closeModal() {
    document.getElementById('detailModal').classList.remove('visible');
  }

  document.getElementById('detailModal').addEventListener('click', e => {
    if (e.target === document.getElementById('detailModal')) closeModal();
  });

  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
  }

  function exportCSV() {
    const rows = filteredData();
    const esc = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const header = ['Student ID', 'Name', 'Programme', 'Company', 'My Score', 'Partner Status', 'Final Score', 'Grade'];
    const lines = rows.map(r => [
      esc(r.student_id),
      esc(r.full_name),
      esc(r.programme),
      esc(r.company_name || ''),
      esc(safeNum(r.total_score) !== null ? r.total_score : ''),
      esc(safeNum(r.other_total_score) !== null ? 'Assessed' : 'Pending'),
      esc(safeNum(r.final_score) !== null ? r.final_score : ''),
      esc(getGrade(r.final_score) || '')
    ].join(','));

    const csv = [header.map(esc).join(','), ...lines].join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = 'my_assessment_results.csv';
    a.click();
    showToast('CSV exported.');
  }

  document.getElementById('searchInput').addEventListener('input',  () => { currentPage = 1; render(); });
  document.getElementById('statusFilter').addEventListener('change', () => { currentPage = 1; render(); });

  loadResults();
</script>
</body>
</html>