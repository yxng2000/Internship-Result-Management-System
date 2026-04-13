<?php
session_start();
require_once 'db.php';

// ── Auth guard ─────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

// ── Search & filter params ─────────────────────────────────────
$search     = trim($_GET['search'] ?? '');
$filter_col = in_array($_GET['filter'] ?? '', ['student_id', 'full_name']) ? $_GET['filter'] : 'full_name';

// ── Build query depending on role ─────────────────────────────
// Assessors only see their own students; Admins see all
$where_parts = [];
$params      = [];
$types       = '';

if ($role === 'assessor') {
    $where_parts[] = "a.assessor_id = ?";
    $params[]      = $user_id;
    $types        .= 'i';
}

if ($search !== '') {
    if ($filter_col === 'student_id') {
        $where_parts[] = "s.student_id LIKE ?";
    } else {
        $where_parts[] = "s.full_name LIKE ?";
    }
    $params[] = "%$search%";
    $types   .= 's';
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$sql = "
    SELECT
        a.assessment_id,
        s.student_id,
        s.full_name,
        s.programme,
        i.company_name,
        u.full_name        AS assessor_name,
        a.task_score,
        a.safety_score,
        a.knowledge_score,
        a.report_score,
        a.language_score,
        a.lifelong_score,
        a.project_score,
        a.time_score,
        a.total_score,
        a.comment,
        a.submitted_at
    FROM assessments a
    JOIN students    s ON a.student_id  = s.student_id
    JOIN users       u ON a.assessor_id = u.user_id
    LEFT JOIN internships i ON s.student_id = i.student_id AND i.assessor_id = a.assessor_id
    $where_sql
    ORDER BY a.submitted_at DESC
";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Grade helper
function gradeLabel($score) {
    if ($score >= 9)  return ['A+', '#1a7a4a'];
    if ($score >= 8)  return ['A',  '#2e8b57'];
    if ($score >= 7)  return ['B+', '#0d7377'];
    if ($score >= 6)  return ['B',  '#2980b9'];
    if ($score >= 5)  return ['C',  '#8e6b00'];
    return ['D', '#c0392b'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Results | Internship System</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --navy:   #0f1b2d;
    --ink:    #1a2e45;
    --teal:   #0d7377;
    --gold:   #e8a838;
    --cream:  #f7f3ec;
    --white:  #ffffff;
    --muted:  #6b7a8d;
    --border: #d8dce3;
    --shadow: 0 4px 24px rgba(15,27,45,0.10);
  }
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--navy); min-height: 100vh; }

  /* Topbar */
  .topbar {
    background: var(--navy); padding: 0 2rem;
    display: flex; align-items: center; justify-content: space-between;
    height: 62px; position: sticky; top: 0; z-index: 100;
  }
  .topbar-brand { font-family: 'DM Serif Display', serif; color: var(--white); font-size: 1.1rem; }
  .topbar-brand span { color: var(--gold); }
  .topbar-nav { display: flex; gap: 0.5rem; align-items: center; }
  .topbar-nav a {
    color: rgba(255,255,255,0.7); text-decoration: none;
    font-size: 0.85rem; padding: 0.4rem 0.9rem; border-radius: 6px; transition: all 0.2s;
  }
  .topbar-nav a:hover, .topbar-nav a.active { background: rgba(255,255,255,0.1); color: var(--white); }
  .topbar-nav a.active { color: var(--gold); }

  /* Layout */
  .page-wrap { max-width: 1100px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }
  .page-header { margin-bottom: 2rem; }
  .page-header h1 { font-family: 'DM Serif Display', serif; font-size: 2rem; }
  .page-header p { color: var(--muted); margin-top: 0.4rem; font-size: 0.92rem; }

  /* Stats row */
  .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.8rem; }
  .stat-card {
    background: var(--white); border-radius: 12px; padding: 1.2rem 1.5rem;
    box-shadow: var(--shadow); display: flex; align-items: center; gap: 1rem;
  }
  .stat-icon { font-size: 1.8rem; }
  .stat-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); font-weight: 600; }
  .stat-value { font-family: 'DM Serif Display', serif; font-size: 1.8rem; color: var(--navy); line-height: 1; }

  /* Search bar */
  .search-bar {
    background: var(--white); border-radius: 12px; padding: 1.2rem 1.5rem;
    box-shadow: var(--shadow); margin-bottom: 1.5rem;
    display: flex; gap: 0.8rem; align-items: flex-end; flex-wrap: wrap;
  }
  .search-group { display: flex; flex-direction: column; gap: 0.4rem; flex: 1; min-width: 200px; }
  .search-group label { font-size: 0.78rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); }
  .search-group input, .search-group select {
    padding: 0.65rem 1rem; border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'DM Sans', sans-serif; font-size: 0.9rem; color: var(--navy);
    background: var(--cream); transition: border-color 0.2s;
  }
  .search-group input:focus, .search-group select:focus {
    outline: none; border-color: var(--teal); background: var(--white);
  }
  .btn-search {
    padding: 0.65rem 1.5rem; background: var(--teal); color: var(--white);
    border: none; border-radius: 8px; font-family: 'DM Sans', sans-serif;
    font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: background 0.2s;
    white-space: nowrap;
  }
  .btn-search:hover { background: #0a5e62; }
  .btn-clear {
    padding: 0.65rem 1rem; background: transparent; color: var(--muted);
    border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'DM Sans', sans-serif; font-size: 0.9rem; cursor: pointer; transition: all 0.2s;
  }
  .btn-clear:hover { border-color: var(--navy); color: var(--navy); }

  /* Results count */
  .results-count { font-size: 0.85rem; color: var(--muted); margin-bottom: 1rem; }
  .results-count strong { color: var(--navy); }

  /* Table card */
  .table-card { background: var(--white); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; }
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; }
  thead { background: var(--ink); }
  thead th {
    padding: 1rem 1.2rem; text-align: left;
    font-size: 0.75rem; font-weight: 600; letter-spacing: 0.07em;
    text-transform: uppercase; color: rgba(255,255,255,0.7);
    white-space: nowrap;
  }
  tbody tr { border-bottom: 1px solid var(--border); transition: background 0.15s; }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: #f0f8f8; }
  td { padding: 0.9rem 1.2rem; font-size: 0.88rem; vertical-align: middle; }

  .student-name { font-weight: 600; color: var(--navy); }
  .student-id   { font-size: 0.78rem; color: var(--muted); margin-top: 2px; }
  .programme    { font-size: 0.78rem; color: var(--muted); }
  .company      { font-size: 0.82rem; color: var(--teal); }

  .score-cell   { text-align: center; font-weight: 600; font-size: 0.85rem; }
  .score-small  { font-size: 0.75rem; color: var(--muted); font-weight: 400; }

  .grade-badge {
    display: inline-block;
    padding: 3px 10px; border-radius: 20px;
    font-size: 0.8rem; font-weight: 700;
    color: var(--white);
  }
  .total-cell   { font-family: 'DM Serif Display', serif; font-size: 1.2rem; text-align: center; }

  /* Detail row */
  .detail-row { display: none; background: #f5faff; }
  .detail-row.open { display: table-row; }
  .detail-inner { padding: 1rem 1.5rem; }
  .score-breakdown {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem 1rem;
    margin-bottom: 0.8rem;
  }
  .breakdown-item { font-size: 0.82rem; }
  .breakdown-label { color: var(--muted); }
  .breakdown-value { font-weight: 600; color: var(--navy); }
  .comment-box {
    background: var(--white); border-left: 3px solid var(--teal);
    padding: 0.7rem 1rem; border-radius: 0 8px 8px 0;
    font-size: 0.85rem; color: var(--navy); font-style: italic;
  }

  .toggle-btn {
    background: none; border: none; cursor: pointer;
    color: var(--teal); font-size: 0.8rem; font-weight: 600;
    padding: 4px 10px; border-radius: 6px; transition: background 0.15s;
  }
  .toggle-btn:hover { background: rgba(13,115,119,0.08); }

  /* Empty state */
  .empty-state { text-align: center; padding: 4rem 2rem; color: var(--muted); }
  .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; }
  .empty-state h3 { font-family: 'DM Serif Display', serif; color: var(--navy); margin-bottom: 0.5rem; }
</style>
</head>
<body>

<nav class="topbar">
  <div class="topbar-brand">Internship <span>Results</span> System</div>
  <div class="topbar-nav">
    <a href="dashboard.php">Dashboard</a>
    <?php if ($role === 'assessor'): ?>
      <a href="result_entry.php">Enter Marks</a>
    <?php endif; ?>
    <a href="view_results.php" class="active">View Results</a>
    <a href="logout.php">Logout</a>
  </div>
</nav>

<div class="page-wrap">

  <div class="page-header">
    <h1>Internship Results</h1>
    <p><?= $role === 'admin' ? 'Viewing all student assessments.' : 'Viewing assessments for your assigned students.' ?></p>
  </div>

  <!-- Stats -->
  <?php
  $total_assessed = count($results);
  $avg_score = $total_assessed > 0
    ? round(array_sum(array_column($results, 'total_score')) / $total_assessed, 2)
    : 0;
  $high_score = $total_assessed > 0 ? max(array_column($results, 'total_score')) : 0;
  ?>
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon">🎓</div>
      <div><div class="stat-label">Students Assessed</div><div class="stat-value"><?= $total_assessed ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">📊</div>
      <div><div class="stat-label">Average Score</div><div class="stat-value"><?= $avg_score ?></div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🏆</div>
      <div><div class="stat-label">Highest Score</div><div class="stat-value"><?= $high_score ?></div></div>
    </div>
  </div>

  <!-- Search -->
  <form method="GET" class="search-bar">
    <div class="search-group">
      <label>Search by</label>
      <select name="filter">
        <option value="full_name" <?= $filter_col === 'full_name' ? 'selected' : '' ?>>Student Name</option>
        <option value="student_id" <?= $filter_col === 'student_id' ? 'selected' : '' ?>>Student ID</option>
      </select>
    </div>
    <div class="search-group">
      <label>Keyword</label>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Type to search…">
    </div>
    <button type="submit" class="btn-search">Search</button>
    <?php if ($search): ?>
      <a href="view_results.php"><button type="button" class="btn-clear">Clear</button></a>
    <?php endif; ?>
  </form>

  <!-- Count -->
  <p class="results-count">
    Showing <strong><?= count($results) ?></strong> result<?= count($results) !== 1 ? 's' : '' ?>
    <?= $search ? " for <strong>" . htmlspecialchars($search) . "</strong>" : '' ?>
  </p>

  <!-- Table -->
  <div class="table-card">
    <div class="table-wrap">
      <?php if (empty($results)): ?>
        <div class="empty-state">
          <div class="icon">🔍</div>
          <h3>No results found</h3>
          <p><?= $search ? 'Try a different search keyword.' : 'No assessments have been submitted yet.' ?></p>
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>Company</th>
            <th>Assessor</th>
            <th style="text-align:center">Task<br><small>10%</small></th>
            <th style="text-align:center">Safety<br><small>10%</small></th>
            <th style="text-align:center">Know.<br><small>10%</small></th>
            <th style="text-align:center">Report<br><small>15%</small></th>
            <th style="text-align:center">Lang.<br><small>10%</small></th>
            <th style="text-align:center">Lifelong<br><small>15%</small></th>
            <th style="text-align:center">Project<br><small>15%</small></th>
            <th style="text-align:center">Time<br><small>15%</small></th>
            <th style="text-align:center">Total</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $i => $r):
            [$grade, $grade_color] = gradeLabel($r['total_score']);
          ?>
          <tr>
            <td>
              <div class="student-name"><?= htmlspecialchars($r['full_name']) ?></div>
              <div class="student-id"><?= htmlspecialchars($r['student_id']) ?></div>
              <div class="programme"><?= htmlspecialchars($r['programme']) ?></div>
            </td>
            <td><div class="company"><?= htmlspecialchars($r['company_name'] ?? '—') ?></div></td>
            <td><?= htmlspecialchars($r['assessor_name']) ?></td>
            <td class="score-cell"><?= $r['task_score'] ?></td>
            <td class="score-cell"><?= $r['safety_score'] ?></td>
            <td class="score-cell"><?= $r['knowledge_score'] ?></td>
            <td class="score-cell"><?= $r['report_score'] ?></td>
            <td class="score-cell"><?= $r['language_score'] ?></td>
            <td class="score-cell"><?= $r['lifelong_score'] ?></td>
            <td class="score-cell"><?= $r['project_score'] ?></td>
            <td class="score-cell"><?= $r['time_score'] ?></td>
            <td class="total-cell">
              <?= number_format($r['total_score'], 2) ?>
              <br>
              <span class="grade-badge" style="background:<?= $grade_color ?>"><?= $grade ?></span>
            </td>
            <td>
              <button class="toggle-btn" onclick="toggleDetail(<?= $i ?>)">Details ▾</button>
            </td>
          </tr>
          <tr class="detail-row" id="detail-<?= $i ?>">
            <td colspan="13">
              <div class="detail-inner">
                <div class="score-breakdown">
                  <div class="breakdown-item"><div class="breakdown-label">Undertaking Tasks (10%)</div><div class="breakdown-value"><?= $r['task_score'] ?> → <?= round($r['task_score'] * 0.10, 2) ?></div></div>
                  <div class="breakdown-item"><div class="breakdown-label">Health & Safety (10%)</div><div class="breakdown-value"><?= $r['safety_score'] ?> → <?= round($r['safety_score'] * 0.10, 2) ?></div></div>
                  <div class="breakdown-item"><div class="breakdown-label">Theoretical Knowledge (10%)</div><div class="breakdown-value"><?= $r['knowledge_score'] ?> → <?= round($r['knowledge_score'] * 0.10, 2) ?></div></div>
                  <div class="breakdown-item"><div class="breakdown-label">Report Presentation (15%)</div><div class="breakdown-value"><?= $r['report_score'] ?> → <?= round($r['report_score'] * 0.15, 2) ?></div></div>
                  <div class="breakdown-item"><div class="breakdown-label">Language & Illustration (10%)</div><div class="breakdown-value"><?= $r['language_score'] ?> → <?= round($r['language_score'] * 0.10, 2) ?></div></div>
                  <div class="breakdown-item"><div class="breakdown-label">Lifelong Learning (15%)</div><div class="breakdown-value"><?= $r['lifelong_score'] ?> → <?= round($r['lifelong_score'] * 0.15, 2) ?></div></div>
                  <div class="breakdown-item"><div class="breakdown-label">Project Management (15%)</div><div class="breakdown-value"><?= $r['project_score'] ?> → <?= round($r['project_score'] * 0.15, 2) ?></div></div>
                  <div class="breakdown-item"><div class="breakdown-label">Time Management (15%)</div><div class="breakdown-value"><?= $r['time_score'] ?> → <?= round($r['time_score'] * 0.15, 2) ?></div></div>
                </div>
                <?php if ($r['comment']): ?>
                  <div class="comment-box">"<?= htmlspecialchars($r['comment']) ?>"</div>
                <?php endif; ?>
                <div style="margin-top:0.5rem;font-size:0.75rem;color:var(--muted)">
                  Submitted: <?= date('d M Y, g:i A', strtotime($r['submitted_at'])) ?>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function toggleDetail(i) {
  const row = document.getElementById('detail-' + i);
  row.classList.toggle('open');
}
</script>
</body>
</html>