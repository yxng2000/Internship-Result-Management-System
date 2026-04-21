<?php
session_start();
require_once 'auth.php';
requireRole('student');
require_once 'config.php';

$conn = getConnection();
$user_id = (int)($_SESSION['user_id'] ?? 0);

$student_id = $_SESSION['student_id'] ?? '';
if (empty($student_id)) {
    header('Location: login.php');
    exit;
}

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
        if ($part !== '') { $initials .= strtoupper(substr($part, 0, 1)); }
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: 'ST';
}

if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? AND role = 'student' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if (!empty($row['full_name'])) { $full_name = $row['full_name']; }
            }
        }
        $stmt->close();
    }
}
$avatar = get_initials($full_name);

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
$db_result = $stmt->get_result();

$assessment_data = null;
if ($db_result && $row = $db_result->fetch_assoc()) {
    $assessment_data = [
        'student_id'      => $row['student_id'],
        'full_name'       => $row['full_name'],
        'programme'       => $row['programme'],
        'company_name'    => $row['company_name'],
        'status'          => $row['status'],
        'lecturer_name'   => $row['lecturer_name'],
        'supervisor_name' => $row['supervisor_name'],
        'l_total'         => $row['l_total'],
        's_total'         => $row['s_total'],
        'scores'          => [
            'undertaking_tasks'     => ['l' => $row['l_ut'], 's' => $row['s_ut']],
            'health_safety'         => ['l' => $row['l_hs'], 's' => $row['s_hs']],
            'theoretical_knowledge' => ['l' => $row['l_tk'], 's' => $row['s_tk']],
            'report_presentation'   => ['l' => $row['l_rp'], 's' => $row['s_rp']],
            'clarity_language'      => ['l' => $row['l_cl'], 's' => $row['s_cl']],
            'lifelong_learning'     => ['l' => $row['l_ll'], 's' => $row['s_ll']],
            'project_management'    => ['l' => $row['l_pm'], 's' => $row['s_pm']],
            'time_management'       => ['l' => $row['l_tm'], 's' => $row['s_tm']],
        ],
        'lecturer_comments'   => $row['l_comments'],
        'supervisor_comments' => $row['s_comments']
    ];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Result — Student Panel</title>
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
  .main { margin-left: 220px; flex: 1; padding: 40px 48px; max-width: 900px; }
  .page-header { margin-bottom: 32px; }
  .page-title { font-size: 28px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 6px; }
  .page-sub { font-size: 14px; color: var(--muted); }
  .result-container { display: none; }
  .student-banner { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; margin-bottom: 24px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
  .banner-avatar { width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 700; color: #fff; flex-shrink: 0; }
  .banner-name { font-size: 18px; font-weight: 700; }
  .banner-meta { font-size: 13px; color: var(--muted); margin-top: 4px; font-family: var(--mono); }
  .banner-details { margin-left: auto; display: flex; gap: 32px; }
  .detail-group { text-align: right; }
  .detail-label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); margin-bottom: 4px; }
  .detail-value { font-size: 14px; font-weight: 600; }
  .score-card { background: linear-gradient(135deg, rgba(79,142,247,0.05), rgba(124,106,247,0.05)); border: 1px solid rgba(79,142,247,0.2); border-radius: var(--radius); padding: 32px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; }
  .score-label { font-size: 14px; font-weight: 600; color: var(--accent); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 8px; }
  .score-main { font-size: 48px; font-weight: 700; font-family: var(--mono); line-height: 1; }
  .grade-badge-large { display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 16px; font-size: 28px; font-weight: 700; font-family: var(--mono); background: rgba(52,201,123,0.15); color: var(--success); border: 2px solid rgba(52,201,123,0.3); }
  .breakdown-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px 32px; margin-bottom: 24px; }
  .section-title { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
  .breakdown-row { display: flex; flex-direction: column; align-items: stretch; gap: 12px; padding: 18px 0; border-bottom: 1px solid var(--border); }
  .breakdown-row:last-child { border-bottom: none; }
  .breakdown-name { font-size: 15px; font-weight: 500; }
  .breakdown-score { font-family: var(--mono); font-weight: 700; font-size: 18px; text-align: right; }
  .score-max-text { font-size: 11px; color: var(--muted); font-weight: 500; }
  .comments-card { background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px 32px; }
  .comments-text { font-size: 14px; line-height: 1.6; color: var(--text); }
</style>
</head>
<body>

<nav class="sidebar">
  <div class="logo">IRM<span>sys</span></div>
  <div class="nav-label">Student Panel</div>
  <a class="nav-item" href="student_dashboard.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"></rect><rect x="14" y="3" width="7" height="7" rx="1"></rect><rect x="3" y="14" width="7" height="7" rx="1"></rect><rect x="14" y="14" width="7" height="7" rx="1"></rect></svg>
    Dashboard
  </a>
  <a class="nav-item" href="student_view_internship.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
    View Internship
  </a>
  <a class="nav-item active" href="student_view_result.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
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
    <div class="page-title">Internship Assessment Result</div>
    <div class="page-sub">View your final evaluation and feedback from your assessors.</div>
  </div>

  <div class="result-container" id="resultContainer" style="display: block;">
    <div class="student-banner">
      <div class="banner-avatar" id="b-avatar">--</div>
      <div>
        <div class="banner-name" id="b-name">—</div>
        <div class="banner-meta" id="b-meta">—</div>
      </div>
      <div class="banner-details">
        <div class="detail-group">
          <div class="detail-label">Company</div>
          <div class="detail-value" id="b-company">—</div>
        </div>
        <div class="detail-group">
          <div class="detail-label">Assigned Assessors</div>
          <div class="detail-value" id="b-assessor" style="text-align: left; line-height: 1.4;">—</div>
        </div>
      </div>
    </div>

    <div class="score-card">
      <div>
        <div class="score-label">Final Averaged Score</div>
        <div class="score-main"><span id="totalScore" style="color: var(--text);">-</span> <span style="font-size: 20px; color: var(--muted);">/ 100.0</span></div>
      </div>
      <div class="grade-badge-large" id="gradeBadge">-</div>
    </div>

    <div class="breakdown-card">
      <div class="section-title">Criteria Breakdown</div>
      <div id="breakdownList"></div>
    </div>

    <div class="comments-card">
      <div class="section-title" style="border: none; margin-bottom: 12px; padding: 0;">Assessor Feedback</div>
      <div id="feedbackContainer"></div>
    </div>
  </div>
</main>

<script>
  const data = <?php echo json_encode($assessment_data); ?>;

  const CRITERIA_META = [
    { key: 'undertaking_tasks',     label: 'Undertaking Tasks',     max: 10 },
    { key: 'health_safety',         label: 'Health & Safety',       max: 10 },
    { key: 'theoretical_knowledge', label: 'Theoretical Knowledge', max: 10 },
    { key: 'report_presentation',   label: 'Report & Presentation', max: 15 },
    { key: 'clarity_language',      label: 'Clarity & Language',    max: 10 },
    { key: 'lifelong_learning',     label: 'Lifelong Learning',     max: 15 },
    { key: 'project_management',    label: 'Project Management',    max: 15 },
    { key: 'time_management',       label: 'Time Management',       max: 15 },
  ];

  // SUPER SAFE NUMBER CHECKER
  function safeNumber(val) {
    if (val === null || val === undefined || val === '') return null;
    const num = parseFloat(val);
    if (isNaN(num)) return null;
    return num;
  }

  function getInitials(name) {
    if (!name) return '--';
    return name.split(' ').map(p => p[0]).join('').substring(0, 2).toUpperCase();
  }

  function getGrade(score) {
    const s = safeNumber(score);
    if (s === null) return { letter: '-', color: 'var(--muted)', bg: '107,112,128' };
    if (s >= 80) return { letter: 'A', color: 'var(--success)', bg: '52,201,123' };
    if (s >= 70) return { letter: 'B', color: 'var(--accent)', bg: '79,142,247' };
    if (s >= 60) return { letter: 'C', color: 'var(--warning)', bg: '240,160,48' };
    if (s >= 50) return { letter: 'D', color: 'var(--danger)', bg: '224,85,85' };
    return { letter: 'F', color: 'var(--danger)', bg: '224,85,85' };
  }

  function getBarColor(pct) {
    if (pct >= 70) return 'var(--success)';
    if (pct >= 50) return 'var(--warning)';
    return 'var(--danger)';
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!data) {
        document.getElementById('resultContainer').innerHTML = '<div style="color:var(--danger); padding: 24px; background: var(--surface); border-radius: var(--radius);">Student record not found.</div>';
        return;
    }

    document.getElementById('b-name').textContent = data.full_name || '—';
    document.getElementById('b-avatar').textContent = getInitials(data.full_name || '');
    document.getElementById('b-meta').textContent = `${data.student_id || '—'} · ${data.programme || '—'}`;
    document.getElementById('b-company').textContent = data.company_name || 'Not assigned';

    let assessorHTML = '';
    if (data.lecturer_name) assessorHTML += `<div>${escapeHtml(data.lecturer_name)} <span style="color:var(--muted); font-size:10px;">(L)</span></div>`;
    if (data.supervisor_name) assessorHTML += `<div style="margin-top: 2px;">${escapeHtml(data.supervisor_name)} <span style="color:var(--muted); font-size:10px;">(S)</span></div>`;
    document.getElementById('b-assessor').innerHTML = assessorHTML || 'Not assigned';

    const lTotal = safeNumber(data.l_total);
    const sTotal = safeNumber(data.s_total);

    if (lTotal !== null && sTotal !== null) {
        const safeTotal = (lTotal + sTotal) / 2;
        const gradeInfo = getGrade(safeTotal);
        
        document.getElementById('totalScore').textContent = safeTotal.toFixed(1);
        const badge = document.getElementById('gradeBadge');
        badge.textContent = gradeInfo.letter;
        badge.style.color = gradeInfo.color;
        badge.style.borderColor = gradeInfo.color;
        badge.style.background = `rgba(${gradeInfo.bg}, 0.15)`;
    }

    let breakdownHTML = '';
    CRITERIA_META.forEach(c => {
      const scoresObj = (data.scores && data.scores[c.key]) ? data.scores[c.key] : { l: null, s: null };
      
      const l_val = safeNumber(scoresObj.l);
      const s_val = safeNumber(scoresObj.s);

      const l_text = l_val !== null ? `${l_val.toFixed(1)} / ${c.max.toFixed(1)}` : '<span style="color: var(--muted);">-</span>';
      const s_text = s_val !== null ? `${s_val.toFixed(1)} / ${c.max.toFixed(1)}` : '<span style="color: var(--muted);">-</span>';

      let avg_text = '<span style="color: var(--muted);">-</span>';
      let barColor = 'var(--muted)';

      if (l_val !== null && s_val !== null) {
        const avg_val = (l_val + s_val) / 2;
        avg_text = avg_val.toFixed(1);
        const pct = (avg_val / c.max) * 100;
        barColor = getBarColor(pct);
      }

      breakdownHTML += `
        <div class="breakdown-row">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <div class="breakdown-name">${c.label}</div>
            <div class="breakdown-score" style="color:${barColor};">
              ${avg_text} <span class="score-max-text">/ ${c.max.toFixed(1)} (Avg)</span>
            </div>
          </div>
          
          <div style="display: flex; gap: 24px; font-size: 13px; color: var(--muted); background: var(--surface2); padding: 10px 14px; border-radius: 8px;">
            <div style="flex: 1; display: flex; justify-content: space-between;">
              <span>Lecturer Score:</span>
              <span style="font-family: var(--mono); color: var(--text);">${l_text}</span>
            </div>
            <div style="width: 1px; background: var(--border);"></div>
            <div style="flex: 1; display: flex; justify-content: space-between;">
              <span>Supervisor Score:</span>
              <span style="font-family: var(--mono); color: var(--text);">${s_text}</span>
            </div>
          </div>
        </div>
      `;
    });
    document.getElementById('breakdownList').innerHTML = breakdownHTML;

    const l_comments = (data.lecturer_comments && data.lecturer_comments.trim() !== '') ? escapeHtml(data.lecturer_comments) : '<span style="color: var(--muted); font-style: italic;">No feedback provided yet.</span>';
    const s_comments = (data.supervisor_comments && data.supervisor_comments.trim() !== '') ? escapeHtml(data.supervisor_comments) : '<span style="color: var(--muted); font-style: italic;">No feedback provided yet.</span>';
    const l_name_display = data.lecturer_name ? escapeHtml(data.lecturer_name) : 'Lecturer';
    const s_name_display = data.supervisor_name ? escapeHtml(data.supervisor_name) : 'Supervisor';

    document.getElementById('feedbackContainer').innerHTML = `
      <div style="margin-bottom: 24px;">
        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent); margin-bottom: 6px; font-weight: 700;">Lecturer Feedback — ${l_name_display}</div>
        <div class="comments-text">${l_comments}</div>
      </div>
      <div>
        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent2); margin-bottom: 6px; font-weight: 700;">Supervisor Feedback — ${s_name_display}</div>
        <div class="comments-text">${s_comments}</div>
      </div>
    `;
  });
</script>
</body>
</html>