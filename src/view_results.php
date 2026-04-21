<?php
session_start();
require_once 'auth.php';
requireRole('admin');
require_once 'config.php';

$conn = getConnection();
$user_id   = (int)($_SESSION['user_id'] ?? 0);
$full_name = $_SESSION['full_name'] ?? 'Admin User';

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function get_initials($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    $i = '';
    foreach ($parts as $p) { if ($p) { $i .= strtoupper($p[0]); } if (strlen($i) >= 2) break; }
    return $i ?: 'AU';
}

if ($user_id > 0) {
    $s = $conn->prepare("SELECT full_name FROM users WHERE user_id=? AND role='admin' LIMIT 1");
    $s->bind_param('i', $user_id); $s->execute();
    $row = $s->get_result()->fetch_assoc(); $s->close();
    if ($row && !empty($row['full_name'])) $full_name = $row['full_name'];
}
$avatar = get_initials($full_name);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Results — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  :root {
    --bg:#0e0f13; --surface:#16181f; --surface2:#1e2029; --border:#2a2d38;
    --accent:#4f8ef7; --accent2:#7c6af7; --text:#e8eaf0; --muted:#6b7080;
    --success:#34c97b; --warning:#f0a030; --danger:#e05555; --radius:10px;
    --font:'Syne',sans-serif; --mono:'DM Mono',monospace;
  }
  body { font-family:var(--font); background:var(--bg); color:var(--text); min-height:100vh; display:flex; }
  .sidebar { width:220px; flex-shrink:0; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; padding:24px 0; position:fixed; top:0; left:0; bottom:0; }
  .logo { font-size:15px; font-weight:700; letter-spacing:.06em; color:var(--accent); padding:0 20px 28px; border-bottom:1px solid var(--border); margin-bottom:16px; text-transform:uppercase; }
  .logo span { color:var(--text); }
  .nav-label { font-size:10px; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); padding:0 20px 8px; }
  .nav-item { display:flex; align-items:center; gap:10px; padding:10px 20px; font-size:13.5px; font-weight:500; color:var(--muted); border-left:3px solid transparent; transition:all .15s; text-decoration:none; }
  .nav-item:hover { color:var(--text); background:var(--surface2); }
  .nav-item.active { color:var(--accent); border-left-color:var(--accent); background:rgba(79,142,247,.07); }
  .sidebar-footer { margin-top:auto; padding:16px 20px; border-top:1px solid var(--border); display:flex; align-items:flex-end; justify-content:space-between; gap:12px; }
  .sidebar-user { display:flex; flex-direction:column; align-items:flex-start; gap:8px; min-width:0; }
  .avatar { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,var(--accent),var(--accent2)); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#fff; flex-shrink:0; }
  .user-name { font-size:12px; font-weight:500; color:rgba(232,234,240,.55); white-space:nowrap; }
  .logout-btn { display:inline-flex; align-items:center; justify-content:center; padding:8px 14px; border:1px solid var(--border); border-radius:10px; background:transparent; color:#ff6b6b; font-size:13px; font-weight:600; text-decoration:none; transition:all .15s ease; flex-shrink:0; }
  .logout-btn:hover { background:rgba(224,85,85,.08); border-color:#e05555; color:#ff7b7b; }
  .main { margin-left:220px; flex:1; padding:32px 36px; max-width:calc(100vw - 220px); }
  .page-header { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:28px; }
  .page-title { font-size:24px; font-weight:700; letter-spacing:-.02em; }
  .page-sub { font-size:13px; color:var(--muted); margin-top:4px; }
  .btn { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:var(--radius); font-family:var(--font); font-size:13.5px; font-weight:600; cursor:pointer; border:none; transition:all .15s; text-decoration:none; }
  .btn-ghost { background:var(--surface2); color:var(--text); border:1px solid var(--border); }
  .btn-ghost:hover { background:var(--border); }
  .btn-sm { padding:6px 12px; font-size:12px; }
  .stats-row { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:28px; }
  .stat-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px 18px; }
  .stat-label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; margin-bottom:8px; }
  .stat-value { font-size:26px; font-weight:700; font-family:var(--mono); }
  .blue{color:var(--accent);} .green{color:var(--success);} .amber{color:var(--warning);} .purple{color:var(--accent2);} .red{color:#ff8d8d;}
  .toolbar { display:flex; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
  .search-wrap { position:relative; flex:1; min-width:200px; max-width:320px; }
  .search-wrap svg { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--muted); }
  .search-input { width:100%; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--font); font-size:13.5px; padding:9px 12px 9px 36px; outline:none; transition:border-color .15s; }
  .search-input::placeholder { color:var(--muted); }
  .search-input:focus { border-color:var(--accent); }
  .filter-select { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-family:var(--font); font-size:13px; padding:9px 14px; outline:none; cursor:pointer; }
  .table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  thead th { padding:12px 14px; text-align:left; font-size:11px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--muted); background:var(--surface2); border-bottom:1px solid var(--border); white-space:nowrap; }
  tbody tr { border-bottom:1px solid var(--border); transition:background .12s; cursor:pointer; }
  tbody tr:last-child { border-bottom:none; }
  tbody tr:hover { background:var(--surface2); }
  tbody td { padding:12px 14px; vertical-align:middle; }
  .student-cell { display:flex; flex-direction:column; gap:2px; }
  .student-id { font-family:var(--mono); font-size:11px; color:var(--muted); }
  .score-cell { font-family:var(--mono); font-weight:700; font-size:14px; }
  .score-high{color:var(--success);} .score-mid{color:var(--warning);} .score-low{color:var(--danger);} .score-none{color:var(--muted);}
  .grade-badge { display:inline-block; padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700; font-family:var(--mono); }
  .grade-A{background:rgba(52,201,123,.12);color:var(--success);} .grade-B{background:rgba(79,142,247,.12);color:var(--accent);}
  .grade-C{background:rgba(240,160,48,.12);color:var(--warning);} .grade-D{background:rgba(224,85,85,.12);color:var(--danger);}
  .grade-F{background:rgba(107,112,128,.12);color:var(--muted);} .grade-none{background:rgba(107,112,128,.08);color:var(--muted);}
  .status-dot { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; }
  .dot { width:7px; height:7px; border-radius:50%; }
  .dot-done { background:var(--success); }
  .dot-pending { background:var(--warning); }
  .dot-none { background:var(--muted); }
  .actions { display:flex; gap:6px; }
  .icon-btn { width:30px; height:30px; border-radius:6px; border:1px solid var(--border); background:transparent; color:var(--muted); cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; }
  .icon-btn:hover { background:var(--surface2); color:var(--text); border-color:var(--text); }
  .no-results { text-align:center; padding:48px 16px; color:var(--muted); font-size:14px; display:none; }
  .pagination { display:flex; align-items:center; justify-content:space-between; padding:14px 16px; border-top:1px solid var(--border); font-size:12.5px; color:var(--muted); }
  .page-btns { display:flex; gap:6px; }
  .page-btn { padding:5px 11px; border-radius:6px; border:1px solid var(--border); background:transparent; color:var(--muted); font-family:var(--font); font-size:12px; cursor:pointer; transition:all .15s; }
  .page-btn:hover, .page-btn.active { background:var(--accent); color:#fff; border-color:var(--accent); }

  /* Modal */
  .modal-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:200; align-items:center; justify-content:center; padding:24px; }
  .modal-backdrop.visible { display:flex; }
  .modal { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); width:100%; max-width:680px; max-height:90vh; overflow-y:auto; }
  .modal-header { display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid var(--border); }
  .modal-title { font-size:16px; font-weight:700; }
  .modal-close { background:transparent; border:none; color:var(--muted); cursor:pointer; font-size:18px; }
  .modal-close:hover { color:var(--text); }
  .modal-body { padding:24px; }
  .modal-student-banner { display:flex; align-items:center; gap:14px; margin-bottom:20px; }
  .modal-avatar { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--accent),var(--accent2)); display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; color:#fff; flex-shrink:0; }
  .modal-student-name { font-size:15px; font-weight:600; }
  .modal-student-meta { font-size:12px; color:var(--muted); margin-top:3px; font-family:var(--mono); }
  .modal-final { margin-left:auto; text-align:right; }
  .modal-final-val { font-size:28px; font-weight:700; font-family:var(--mono); }
  .modal-final-label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; }
  .dual-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:4px; }
  .assessor-panel { background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:16px; }
  .assessor-panel-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; margin-bottom:12px; padding-bottom:8px; border-bottom:1px solid var(--border); }
  .assessor-panel-title.lect { color:var(--accent); }
  .assessor-panel-title.sup  { color:var(--warning); }
  .assessor-total { font-size:20px; font-weight:700; font-family:var(--mono); margin-bottom:12px; }
  .breakdown-row-sm { display:flex; align-items:center; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--border); font-size:12px; }
  .breakdown-row-sm:last-child { border-bottom:none; }
  .breakdown-score-sm { font-family:var(--mono); font-weight:700; }
  .comment-box { margin-top:12px; font-size:12px; color:var(--muted); font-style:italic; line-height:1.5; }
  .not-submitted { color:var(--muted); font-size:13px; text-align:center; padding:20px 0; }
  .toast { position:fixed; bottom:24px; right:24px; background:var(--surface2); border:1px solid var(--border); border-left:3px solid var(--success); border-radius:var(--radius); padding:12px 18px; font-size:13px; color:var(--text); opacity:0; transform:translateY(10px); transition:all .25s; pointer-events:none; z-index:999; }
  .toast.show { opacity:1; transform:translateY(0); }
</style>
</head>
<body>
<nav class="sidebar">
  <div class="logo">IRM<span>sys</span></div>
  <div class="nav-label">Admin Panel</div>
  <a class="nav-item" href="admin_dashboard.php"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Dashboard</a>
  <a class="nav-item" href="user_management.php"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>User Management</a>
  <a class="nav-item" href="internship_list.php"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>Internship Mgmt</a>
  <a class="nav-item active" href="view_results.php"><svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>Results</a>
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
      <div class="page-title">All Assessment Results</div>
      <div class="page-sub">Final score = average of lecturer + supervisor marks. Click any row to see full breakdown.</div>
    </div>
    <button class="btn btn-ghost btn-sm" onclick="exportCSV()">
      <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export CSV
    </button>
  </div>

  <div class="stats-row">
    <div class="stat-card"><div class="stat-label">Total Students</div><div class="stat-value blue" id="stat-total">0</div></div>
    <div class="stat-card"><div class="stat-label">Both Submitted</div><div class="stat-value green" id="stat-both">0</div></div>
    <div class="stat-card"><div class="stat-label">Lecturer Done</div><div class="stat-value amber" id="stat-lect">0</div></div>
    <div class="stat-card"><div class="stat-label">Supervisor Done</div><div class="stat-value amber" id="stat-sup">0</div></div>
    <div class="stat-card"><div class="stat-label">Average Score</div><div class="stat-value purple" id="stat-avg">—</div></div>
  </div>

  <div class="toolbar">
    <div class="search-wrap">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input class="search-input" type="text" id="searchInput" placeholder="Search by student ID or name…">
    </div>
    <select class="filter-select" id="statusFilter">
      <option value="all">All Status</option>
      <option value="both">Both Submitted</option>
      <option value="partial">Partially Submitted</option>
      <option value="none">Not Started</option>
    </select>
    <select class="filter-select" id="gradeFilter">
      <option value="all">All Grades</option>
      <option value="A">Grade A (≥80)</option>
      <option value="B">Grade B (70–79)</option>
      <option value="C">Grade C (60–69)</option>
      <option value="D">Grade D (50–59)</option>
      <option value="F">Grade F (&lt;50)</option>
    </select>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Programme</th>
          <th>Company</th>
          <th>Lecturer Status</th>
          <th>Supervisor Status</th>
          <th>Final Score</th>
          <th>Grade</th>
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
      <div class="modal-title">Assessment Breakdown</div>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body">
      <div class="modal-student-banner">
        <div class="modal-avatar" id="m-avatar">--</div>
        <div>
          <div class="modal-student-name" id="m-name">—</div>
          <div class="modal-student-meta" id="m-meta">—</div>
        </div>
        <div class="modal-final">
          <div class="modal-final-val" id="m-final">—</div>
          <div class="modal-final-label">Final Score / 100</div>
        </div>
      </div>
      <div class="dual-grid" id="m-dual"></div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const ROWS_PER_PAGE = 10;
let currentPage = 1;
let data = [];

const CRITERIA = [
  { key:'undertaking_tasks',     label:'Undertaking Tasks',     max:10 },
  { key:'health_safety',         label:'Health & Safety',        max:10 },
  { key:'theoretical_knowledge', label:'Theoretical Knowledge',  max:10 },
  { key:'report_presentation',   label:'Report & Presentation',  max:15 },
  { key:'clarity_language',      label:'Clarity & Language',     max:10 },
  { key:'lifelong_learning',     label:'Lifelong Learning',      max:15 },
  { key:'project_management',    label:'Project Management',     max:15 },
  { key:'time_management',       label:'Time Management',        max:15 },
];

function getGrade(score) {
  if (score === null || score === undefined || score === '') return null;
  const s = parseFloat(score);
  if (s >= 80) return 'A'; if (s >= 70) return 'B';
  if (s >= 60) return 'C'; if (s >= 50) return 'D'; return 'F';
}

function getScoreClass(score) {
  if (score === null || score === '') return 'score-none';
  const s = parseFloat(score);
  if (s >= 70) return 'score-high'; if (s >= 50) return 'score-mid'; return 'score-low';
}

function getInitials(name) {
  if (!name) return '--';
  return name.split(' ').map(p => p[0]).join('').substring(0, 2).toUpperCase();
}

function loadResults() {
  fetch('get_results.php')
    .then(r => r.json())
    .then(result => {
      data = result.records || [];
      updateStats();
      render();
    })
    .catch(err => console.error('Failed to load results:', err));
}

function updateStats() {
  let both = 0, lect = 0, sup = 0;
  const scores = [];
  data.forEach(r => {
    const hasL = r.lecturer_score !== null;
    const hasS = r.supervisor_score !== null;
    if (hasL) lect++;
    if (hasS) sup++;
    if (hasL && hasS) { both++; scores.push(r.total_score); }
  });
  const avg = scores.length ? (scores.reduce((a,b)=>a+parseFloat(b),0)/scores.length).toFixed(1) : '—';
  document.getElementById('stat-total').textContent = data.length;
  document.getElementById('stat-both').textContent  = both;
  document.getElementById('stat-lect').textContent  = lect;
  document.getElementById('stat-sup').textContent   = sup;
  document.getElementById('stat-avg').textContent   = avg;
}

function getSubmissionStatus(r) {
  const hasL = r.lecturer_score !== null;
  const hasS = r.supervisor_score !== null;
  if (hasL && hasS) return 'both';
  if (hasL || hasS) return 'partial';
  return 'none';
}

function filteredData() {
  const q  = document.getElementById('searchInput').value.toLowerCase();
  const sf = document.getElementById('statusFilter').value;
  const gf = document.getElementById('gradeFilter').value;

  return data.filter(r => {
    const matchQ = (r.student_id||'').toLowerCase().includes(q) || (r.full_name||'').toLowerCase().includes(q);
    const sub = getSubmissionStatus(r);
    const matchS = sf === 'all' || sub === sf;
    const matchG = gf === 'all' || getGrade(r.total_score) === gf;
    return matchQ && matchS && matchG;
  });
}

function statusDot(submitted, label) {
  const cls = submitted ? 'dot-done' : 'dot-pending';
  const txt = submitted ? 'Done' : 'Pending';
  return `<div class="status-dot"><div class="dot ${cls}"></div>${txt}<br><small style="color:var(--muted);font-size:10px">${label}</small></div>`;
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
      const hasL = r.lecturer_score !== null;
      const hasS = r.supervisor_score !== null;
      const hasFinal = r.total_score !== null;
      const score = hasFinal ? parseFloat(r.total_score).toFixed(2) : null;
      const grade = getGrade(r.total_score);
      const safeR = JSON.stringify(r).replace(/'/g, "\\'");
      return `
        <tr onclick='openModal(${safeR})'>
          <td><div class="student-cell"><span>${r.full_name}</span><span class="student-id">${r.student_id}</span></div></td>
          <td>${r.programme}</td>
          <td>${r.company_name || '<span style="color:var(--muted)">—</span>'}</td>
          <td>${statusDot(hasL, r.lecturer_name || '—')}</td>
          <td>${statusDot(hasS, r.supervisor_name || '—')}</td>
          <td>${hasFinal
            ? `<span class="${getScoreClass(r.total_score)}" style="font-family:var(--mono);font-weight:700">${score}</span>`
            : `<span style="color:var(--muted);font-size:12px">Awaiting both</span>`
          }</td>
          <td>${grade ? `<span class="grade-badge grade-${grade}">${grade}</span>` : `<span class="grade-badge grade-none">—</span>`}</td>
        </tr>`;
    }).join('');
  }

  document.getElementById('pageInfo').textContent =
    `Showing ${Math.min(total,(currentPage-1)*ROWS_PER_PAGE+slice.length)} of ${total} records`;

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

function buildAssessorPanel(r, prefix, title, cls, nameField) {
  const score = r[prefix + 'score'];
  if (score === null) {
    return `<div class="assessor-panel">
      <div class="assessor-panel-title ${cls}">${title}</div>
      <div class="not-submitted">Not submitted yet</div>
    </div>`;
  }
  const rows = CRITERIA.map(c => {
    const val = parseFloat(r[prefix.replace('_score','') + c.key] || 0);
    return `<div class="breakdown-row-sm">
      <span>${c.label} <span style="color:var(--muted)">(${c.max})</span></span>
      <span class="breakdown-score-sm">${val.toFixed(1)}</span>
    </div>`;
  }).join('');
  const comment = r[prefix.replace('score','comments')];
  return `<div class="assessor-panel">
    <div class="assessor-panel-title ${cls}">${title} — ${r[nameField] || '—'}</div>
    <div class="assessor-total" style="color:var(--${cls==='lect'?'accent':'warning'})">${parseFloat(score).toFixed(2)} / 100</div>
    ${rows}
    ${comment ? `<div class="comment-box">"${comment}"</div>` : ''}
  </div>`;
}

function openModal(r) {
  document.getElementById('m-avatar').textContent = getInitials(r.full_name);
  document.getElementById('m-name').textContent   = r.full_name;
  document.getElementById('m-meta').textContent   = `${r.student_id} · ${r.programme} · ${r.company_name || '—'}`;

  const finalEl = document.getElementById('m-final');
  if (r.total_score !== null) {
    finalEl.textContent = parseFloat(r.total_score).toFixed(2);
    finalEl.style.color = 'var(--success)';
    document.querySelector('.modal-final-label').textContent =
      `Final Score / 100 · Grade ${getGrade(r.total_score)}`;
  } else {
    finalEl.textContent = '—';
    finalEl.style.color = 'var(--muted)';
    document.querySelector('.modal-final-label').textContent = 'Awaiting both assessors';
  }

  document.getElementById('m-dual').innerHTML =
    buildAssessorPanel(r, 'l_', 'Lecturer', 'lect', 'lecturer_name') +
    buildAssessorPanel(r, 's_', 'Supervisor', 'sup', 'supervisor_name');

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
  const esc = v => `"${String(v??'').replace(/"/g,'""')}"`;
  const header = ['Student ID','Name','Programme','Company','Lecturer','Lecturer Score','Supervisor','Supervisor Score','Final Score','Grade'];
  const lines = rows.map(r => [
    esc(r.student_id), esc(r.full_name), esc(r.programme), esc(r.company_name||''),
    esc(r.lecturer_name||''), esc(r.lecturer_score!==null?r.lecturer_score:''),
    esc(r.supervisor_name||''), esc(r.supervisor_score!==null?r.supervisor_score:''),
    esc(r.total_score!==null?r.total_score:''), esc(getGrade(r.total_score)||'')
  ].join(','));
  const csv = [header.map(esc).join(','), ...lines].join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'assessment_results.csv';
  a.click();
  showToast('CSV exported.');
}

document.getElementById('searchInput').addEventListener('input',  () => { currentPage=1; render(); });
document.getElementById('statusFilter').addEventListener('change', () => { currentPage=1; render(); });
document.getElementById('gradeFilter').addEventListener('change',  () => { currentPage=1; render(); });

loadResults();
</script>
</body>
</html>