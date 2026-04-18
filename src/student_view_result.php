<?php
session_start();
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

if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? AND role = 'student' LIMIT 1");
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

  /* admin dashboard style footer/logout */
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

  .main { margin-left: 220px; flex: 1; padding: 40px 48px; max-width: 900px; }

  .page-header { margin-bottom: 32px; }
  .page-title { font-size: 28px; font-weight: 700; letter-spacing: -0.02em; margin-bottom: 6px; }
  .page-sub { font-size: 14px; color: var(--muted); }

  .pending-state { display: none; background: var(--surface); border: 1px dashed var(--border); border-radius: var(--radius); padding: 56px 24px; text-align: center; flex-direction: column; align-items: center; justify-content: center; }
  .pending-icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(240,160,48,0.1); color: var(--warning); display: flex; align-items: center; justify-content: center; margin-bottom: 20px; }
  .pending-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
  .pending-desc { font-size: 14px; color: var(--muted); max-width: 400px; line-height: 1.5; }

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

  .breakdown-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 0; border-bottom: 1px solid var(--border); }
  .breakdown-row:last-child { border-bottom: none; }
  .breakdown-name { font-size: 14px; font-weight: 500; }

  .breakdown-right { display: flex; align-items: center; gap: 16px; width: 240px; justify-content: flex-end; }
  .breakdown-bar-wrap { flex: 1; height: 6px; background: var(--surface2); border-radius: 99px; overflow: hidden; }
  .breakdown-bar { height: 100%; border-radius: 99px; background: var(--accent); }

  .breakdown-score { font-family: var(--mono); font-weight: 700; font-size: 15px; width: 95px; text-align: right; white-space: nowrap; }
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
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <rect x="3" y="3" width="7" height="7" rx="1"></rect>
      <rect x="14" y="3" width="7" height="7" rx="1"></rect>
      <rect x="3" y="14" width="7" height="7" rx="1"></rect>
      <rect x="14" y="14" width="7" height="7" rx="1"></rect>
    </svg>
    Dashboard
  </a>

  <a class="nav-item" href="student_view_internship.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <rect x="2" y="7" width="20" height="14" rx="2"></rect>
      <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
    </svg>
    View Internship
  </a>

  <a class="nav-item active" href="student_view_result.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
      <polyline points="14 2 14 8 20 8"></polyline>
      <line x1="16" y1="13" x2="8" y2="13"></line>
      <line x1="16" y1="17" x2="8" y2="17"></line>
      <polyline points="10 9 9 9 8 9"></polyline>
    </svg>
    View Result
  </a>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar" id="sidebarAvatar"><?= e($avatar) ?></div>
      <div class="user-name" id="sidebarName"><?= e($full_name) ?></div>
    </div>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</nav>

<main class="main">

  <div class="page-header">
    <div class="page-title">Internship Assessment Result</div>
    <div class="page-sub">View your final evaluation and feedback from your company assessor.</div>
  </div>

  <div id="loadingText" style="color: var(--muted); font-size: 14px;">Loading your results...</div>

  <div class="pending-state" id="pendingState">
    <div class="pending-icon">
      <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div class="pending-title">Assessment Pending</div>
    <div class="pending-desc">Your internship result has not been submitted yet. Please check back later or contact your assessor (<span id="pendingAssessor">your assigned assessor</span>) for an update.</div>
  </div>

  <div class="result-container" id="resultContainer">

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
          <div class="detail-label">Assessor</div>
          <div class="detail-value" id="b-assessor">—</div>
        </div>
      </div>
    </div>

    <div class="score-card">
      <div>
        <div class="score-label">Final Evaluation Score</div>
        <div class="score-main"><span id="totalScore" style="color: var(--text);">0.0</span> <span style="font-size: 20px; color: var(--muted);">/ 100.0</span></div>
      </div>
      <div class="grade-badge-large" id="gradeBadge">A</div>
    </div>

    <div class="breakdown-card">
      <div class="section-title">Criteria Breakdown</div>
      <div id="breakdownList"></div>
    </div>

    <div class="comments-card" id="commentsCard">
      <div class="section-title" style="border: none; margin-bottom: 12px; padding: 0;">Assessor Feedback</div>
      <div class="comments-text" id="assessorComments">—</div>
    </div>

  </div>

</main>

<script>
  const CRITERIA_META = [
    { key: 'undertaking_tasks',     label: 'Undertaking Tasks',     max: 10 },
    { key: 'health_safety',         label: 'Health & Safety',        max: 10 },
    { key: 'theoretical_knowledge', label: 'Theoretical Knowledge',  max: 10 },
    { key: 'report_presentation',   label: 'Report & Presentation',  max: 15 },
    { key: 'clarity_language',      label: 'Clarity & Language',     max: 10 },
    { key: 'lifelong_learning',     label: 'Lifelong Learning',      max: 15 },
    { key: 'project_management',    label: 'Project Management',     max: 15 },
    { key: 'time_management',       label: 'Time Management',        max: 15 },
  ];

  function getInitials(name) {
    if (!name) return '--';
    return name.split(' ').map(p => p[0]).join('').substring(0, 2).toUpperCase();
  }

  function getGrade(score) {
    const s = parseFloat(score);
    if (s >= 80) return { letter: 'A', color: 'var(--success)', bg: '52,201,123' };
    if (s >= 70) return { letter: 'B', color: 'var(--accent)', bg: '79,142,247' };
    if (s >= 60) return { letter: 'C', color: 'var(--warning)', bg: '240,160,48' };
    if (s >= 50) return { letter: 'D', color: 'var(--danger)', bg: '224,85,85' };
    return { letter: 'F', color: 'var(--muted)', bg: '107,112,128' };
  }

  function getBarColor(pct) {
    if (pct >= 70) return 'var(--success)';
    if (pct >= 50) return 'var(--warning)';
    return 'var(--danger)';
  }

  function loadStudentResult() {
    fetch('get_student_result.php')
      .then(r => r.json())
      .then(response => {
        document.getElementById('loadingText').style.display = 'none';

        if (!response.success || !response.data) {
          alert('Could not find student data.');
          return;
        }

        const data = response.data;

        document.getElementById('sidebarName').textContent = data.full_name || 'Student User';
        document.getElementById('sidebarAvatar').textContent = getInitials(data.full_name || 'Student User');

        if (data.total_score === null || data.total_score === undefined) {
          document.getElementById('pendingAssessor').textContent = data.assessor_name || 'your assigned assessor';
          document.getElementById('pendingState').style.display = 'flex';
        } else {
          document.getElementById('resultContainer').style.display = 'block';

          document.getElementById('b-name').textContent = data.full_name || '—';
          document.getElementById('b-avatar').textContent = getInitials(data.full_name || '');
          document.getElementById('b-meta').textContent = `${data.student_id || '—'} · ${data.programme || '—'}`;
          document.getElementById('b-company').textContent = data.company_name || 'Not assigned';
          document.getElementById('b-assessor').textContent = data.assessor_name || 'Not assigned';

          const score = parseFloat(data.total_score).toFixed(1);
          const gradeInfo = getGrade(data.total_score);

          document.getElementById('totalScore').textContent = score;

          const badge = document.getElementById('gradeBadge');
          badge.textContent = gradeInfo.letter;
          badge.style.color = gradeInfo.color;
          badge.style.borderColor = gradeInfo.color;
          badge.style.background = `rgba(${gradeInfo.bg}, 0.15)`;

          let breakdownHTML = '';
          CRITERIA_META.forEach(c => {
            const val = parseFloat(data[c.key] || 0);
            const pct = (val / c.max) * 100;
            const barColor = getBarColor(pct);

            breakdownHTML += `
              <div class="breakdown-row">
                <div>
                  <div class="breakdown-name">${c.label}</div>
                </div>
                <div class="breakdown-right">
                  <div class="breakdown-bar-wrap">
                    <div class="breakdown-bar" style="width:${pct}%;background:${barColor}"></div>
                  </div>
                  <div class="breakdown-score" style="color:${barColor}">
                    ${val.toFixed(1)} <span class="score-max-text">/ ${c.max.toFixed(1)}</span>
                  </div>
                </div>
              </div>
            `;
          });
          document.getElementById('breakdownList').innerHTML = breakdownHTML;

          if (data.comments && data.comments.trim() !== '') {
            document.getElementById('assessorComments').textContent = data.comments;
          } else {
            document.getElementById('assessorComments').innerHTML = '<span style="color: var(--muted); font-style: italic;">No additional comments provided.</span>';
          }
        }
      })
      .catch(err => {
        console.error('Fetch error:', err);
        document.getElementById('loadingText').textContent = 'Error loading results. Please try again later.';
      });
  }

  document.addEventListener('DOMContentLoaded', loadStudentResult);
</script>
</body>
</html>