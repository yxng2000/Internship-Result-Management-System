<?php
session_start();
require_once 'config.php';
$conn = getConnection();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'assessor') {
    header("Location: login.php");
    exit();
}

$assessor_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg   = '';

$students_query = $conn->prepare("
    SELECT s.student_id, s.full_name, s.programme, 
           i.company_name, i.internship_id
    FROM students s
    JOIN internships i ON s.student_id = i.student_id
    LEFT JOIN assessments a ON i.internship_id = a.internship_id
    WHERE i.assessor_id = ? AND a.assessment_id IS NULL
    ORDER BY s.full_name
");
$students_query->bind_param("i", $assessor_id);
$students_query->execute();
$students_result = $students_query->get_result();
$students = $students_result->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id'] ?? '');
    $task       = floatval($_POST['task']      ?? 0);
    $safety     = floatval($_POST['safety']    ?? 0);
    $knowledge  = floatval($_POST['knowledge'] ?? 0);
    $report     = floatval($_POST['report']    ?? 0);
    $language   = floatval($_POST['language']  ?? 0);
    $lifelong   = floatval($_POST['lifelong']  ?? 0);
    $project    = floatval($_POST['project']   ?? 0);
    $time       = floatval($_POST['time']      ?? 0);
    $comment    = trim($_POST['comment']       ?? '');

    $scores = [$task, $safety, $knowledge, $report, $language, $lifelong, $project, $time];
    $valid  = true;

    if (empty($student_id)) {
        $error_msg = "Please select a student.";
        $valid = false;
    }

    foreach ($scores as $score) {
        if ($score < 0 || $score > 10) {
            $error_msg = "All scores must be between 0 and 10.";
            $valid = false;
            break;
        }
    }

    if ($valid) {
        $iStmt = $conn->prepare("
            SELECT internship_id FROM internships 
            WHERE student_id = ? AND assessor_id = ?
        ");
        $iStmt->bind_param("si", $student_id, $assessor_id);
        $iStmt->execute();
        $iRow = $iStmt->get_result()->fetch_assoc();
        $internship_id = $iRow ? $iRow['internship_id'] : null;

        if (!$internship_id) {
            $error_msg = "No internship record found for this student.";
            $valid = false;
        }
    }

    if ($valid) {
        $total = round(
            ($task      * 0.10) +
            ($safety    * 0.10) +
            ($knowledge * 0.10) +
            ($report    * 0.15) +
            ($language  * 0.10) +
            ($lifelong  * 0.15) +
            ($project   * 0.15) +
            ($time      * 0.15),
            2
        );

        $stmt = $conn->prepare("
            INSERT INTO assessments (
                internship_id,
                undertaking_tasks, health_safety, theoretical_knowledge,
                report_presentation, clarity_language, lifelong_learning,
                project_management, time_management,
                total_score, comments
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iddddddddds",
            $internship_id,
            $task, $safety, $knowledge, $report,
            $language, $lifelong, $project, $time,
            $total, $comment
        );

        if ($stmt->execute()) {
            $success_msg = "Assessment for student <strong>$student_id</strong> submitted successfully! Total score: <strong>$total / 10</strong>";
            $students_query->execute();
            $students_result = $students_query->get_result();
            $students = $students_result->fetch_all(MYSQLI_ASSOC);
        } else {
            $error_msg = "Error saving assessment: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Enter Assessment | Internship System</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --navy:    #0f1b2d;
    --ink:     #1a2e45;
    --teal:    #0d7377;
    --teal-lt: #14a8ad;
    --gold:    #e8a838;
    --cream:   #f7f3ec;
    --white:   #ffffff;
    --muted:   #6b7a8d;
    --danger:  #c0392b;
    --success: #1a7a4a;
    --border:  #d8dce3;
    --shadow:  0 4px 24px rgba(15,27,45,0.10);
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--cream);
    color: var(--navy);
    min-height: 100vh;
  }

  .topbar {
    background: var(--navy);
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 62px;
    position: sticky; top: 0; z-index: 100;
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

  .page-wrap { max-width: 820px; margin: 2.5rem auto; padding: 0 1.5rem 4rem; }
  .page-header { margin-bottom: 2rem; }
  .page-header h1 { font-family: 'DM Serif Display', serif; font-size: 2rem; color: var(--navy); line-height: 1.2; }
  .page-header p { color: var(--muted); margin-top: 0.4rem; font-size: 0.92rem; }

  .alert { padding: 1rem 1.2rem; border-radius: 10px; font-size: 0.9rem; margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 0.8rem; }
  .alert-success { background: #eaf7f0; border-left: 4px solid var(--success); color: var(--success); }
  .alert-error   { background: #fdf0ef; border-left: 4px solid var(--danger);  color: var(--danger); }
  .alert-icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }

  .card { background: var(--white); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; margin-bottom: 1.5rem; }
  .card-header { background: var(--ink); padding: 1.2rem 1.8rem; display: flex; align-items: center; gap: 0.8rem; }
  .card-header-icon { width: 36px; height: 36px; background: rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
  .card-header h2 { font-family: 'DM Serif Display', serif; color: var(--white); font-size: 1.1rem; font-weight: 400; }
  .card-body { padding: 1.8rem; }

  .form-group { margin-bottom: 1.3rem; }
  .form-group label { display: block; font-size: 0.82rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); margin-bottom: 0.45rem; }
  .form-control { width: 100%; padding: 0.7rem 1rem; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; color: var(--navy); background: var(--cream); transition: border-color 0.2s, box-shadow 0.2s; appearance: none; }
  .form-control:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 3px rgba(13,115,119,0.12); background: var(--white); }
  select.form-control { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b7a8d' d='M6 8L0 0h12z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; padding-right: 2.5rem; }
  textarea.form-control { resize: vertical; min-height: 90px; }

  .score-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem 1.5rem; }
  @media (max-width: 600px) { .score-grid { grid-template-columns: 1fr; } }

  .score-item { margin-bottom: 0; }
  .score-label-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem; }
  .score-label-row label { font-size: 0.82rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: var(--muted); margin: 0; }
  .weight-badge { background: var(--teal); color: var(--white); font-size: 0.68rem; font-weight: 600; padding: 2px 8px; border-radius: 20px; letter-spacing: 0.04em; }
  .score-input-row { display: flex; align-items: center; gap: 0.8rem; }
  .score-input-row input[type="number"] { width: 80px; padding: 0.65rem 0.8rem; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: 0.95rem; font-weight: 600; color: var(--navy); background: var(--cream); text-align: center; transition: border-color 0.2s, box-shadow 0.2s; }
  .score-input-row input[type="number"]:focus { outline: none; border-color: var(--teal); box-shadow: 0 0 0 3px rgba(13,115,119,0.12); background: var(--white); }
  input[type="range"] { flex: 1; height: 5px; accent-color: var(--teal); cursor: pointer; }

  .total-preview { background: linear-gradient(135deg, var(--teal) 0%, var(--ink) 100%); border-radius: 12px; padding: 1.2rem 1.8rem; display: flex; align-items: center; justify-content: space-between; margin-top: 0.5rem; }
  .total-preview-label { color: rgba(255,255,255,0.8); font-size: 0.85rem; font-weight: 500; }
  .total-preview-value { font-family: 'DM Serif Display', serif; font-size: 2.2rem; color: var(--white); }
  .total-preview-value span { font-family: 'DM Sans', sans-serif; font-size: 0.9rem; color: rgba(255,255,255,0.6); }

  .btn-submit { width: 100%; padding: 0.95rem; background: var(--teal); color: var(--white); border: none; border-radius: 10px; font-family: 'DM Sans', sans-serif; font-size: 1rem; font-weight: 600; cursor: pointer; letter-spacing: 0.03em; transition: background 0.2s, transform 0.1s; margin-top: 1.5rem; }
  .btn-submit:hover { background: #0a5e62; }
  .btn-submit:active { transform: scale(0.99); }

  .empty-state { text-align: center; padding: 3rem 2rem; color: var(--muted); }
  .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; }
  .empty-state h3 { font-family: 'DM Serif Display', serif; color: var(--navy); margin-bottom: 0.5rem; }
</style>
</head>
<body>

<nav class="topbar">
  <div class="topbar-brand">Internship <span>Results</span> System</div>
  <div class="topbar-nav">
    <a href="dashboard.php">Dashboard</a>
    <a href="result_entry.php" class="active">Enter Marks</a>
    <a href="view_results.php">View Results</a>
    <a href="logout.php">Logout</a>
  </div>
</nav>

<div class="page-wrap">
  <div class="page-header">
    <h1>Enter Assessment Marks</h1>
    <p>Welcome, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Assessor') ?> — score your assigned students below.</p>
  </div>

  <?php if ($success_msg): ?>
    <div class="alert alert-success"><div class="alert-icon">✓</div><div><?= $success_msg ?></div></div>
  <?php endif; ?>

  <?php if ($error_msg): ?>
    <div class="alert alert-error"><div class="alert-icon">✕</div><div><?= htmlspecialchars($error_msg) ?></div></div>
  <?php endif; ?>

  <?php if (empty($students)): ?>
    <div class="card">
      <div class="card-body">
        <div class="empty-state">
          <div class="icon">🎓</div>
          <h3>All students assessed</h3>
          <p>You have no remaining students to assess. Check the results page to view submitted assessments.</p>
        </div>
      </div>
    </div>
  <?php else: ?>

  <form method="POST" action="" id="assessForm" novalidate>

    <div class="card">
      <div class="card-header"><div class="card-header-icon">👤</div><h2>Student Selection</h2></div>
      <div class="card-body">
        <div class="form-group">
          <label for="student_id">Select Student</label>
          <select name="student_id" id="student_id" class="form-control" required>
            <option value="">— Choose a student —</option>
            <?php foreach ($students as $s): ?>
              <option value="<?= htmlspecialchars($s['student_id']) ?>"
                <?= (($_POST['student_id'] ?? '') === $s['student_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['student_id']) ?> — <?= htmlspecialchars($s['full_name']) ?>
                (<?= htmlspecialchars($s['programme']) ?> | <?= htmlspecialchars($s['company_name']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-header-icon">📊</div><h2>Assessment Scores — each criterion scored 0 to 10</h2></div>
      <div class="card-body">
        <div class="score-grid">
          <?php
          $criteria = [
            'task'      => ['Undertaking Tasks / Projects',         '10%'],
            'safety'    => ['Health & Safety at Workplace',         '10%'],
            'knowledge' => ['Connectivity & Theoretical Knowledge', '10%'],
            'report'    => ['Presentation of Report (Written)',     '15%'],
            'language'  => ['Clarity of Language & Illustration',  '10%'],
            'lifelong'  => ['Lifelong Learning Activities',        '15%'],
            'project'   => ['Project Management',                  '15%'],
            'time'      => ['Time Management',                     '15%'],
          ];
          foreach ($criteria as $key => [$label, $weight]):
            $val = isset($_POST[$key]) ? (float)$_POST[$key] : 5;
          ?>
          <div class="score-item">
            <div class="score-label-row">
              <label for="<?= $key ?>"><?= $label ?></label>
              <span class="weight-badge"><?= $weight ?></span>
            </div>
            <div class="score-input-row">
              <input type="range" min="0" max="10" step="0.5" value="<?= $val ?>"
                     oninput="syncScore('<?= $key ?>', this.value)" id="range_<?= $key ?>">
              <input type="number" name="<?= $key ?>" id="<?= $key ?>"
                     min="0" max="10" step="0.5" value="<?= $val ?>"
                     oninput="syncRange('<?= $key ?>', this.value)" required>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="total-preview" style="margin-top:1.5rem">
          <div class="total-preview-label">Calculated Total Score (out of 10)</div>
          <div class="total-preview-value" id="liveTotal">—<span> / 10</span></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-header-icon">💬</div><h2>Assessor Comments</h2></div>
      <div class="card-body">
        <div class="form-group" style="margin-bottom:0">
          <label for="comment">Qualitative Feedback</label>
          <textarea name="comment" id="comment" class="form-control"
                    placeholder="Provide feedback on the student's performance, strengths, and areas for improvement..."><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <button type="submit" class="btn-submit">Submit Assessment</button>
  </form>

  <?php endif; ?>
</div>

<script>
function syncScore(key, val) {
  document.getElementById(key).value = val;
  updateTotal();
}
function syncRange(key, val) {
  const v = Math.min(10, Math.max(0, parseFloat(val) || 0));
  document.getElementById('range_' + key).value = v;
  document.getElementById(key).value = v;
  updateTotal();
}

const weights = {
  task: 0.10, safety: 0.10, knowledge: 0.10,
  report: 0.15, language: 0.10, lifelong: 0.15,
  project: 0.15, time: 0.15
};

function updateTotal() {
  let total = 0;
  for (const [key, w] of Object.entries(weights)) {
    total += (parseFloat(document.getElementById(key).value) || 0) * w;
  }
  document.getElementById('liveTotal').innerHTML =
    '<strong>' + total.toFixed(2) + '</strong><span> / 10</span>';
}

document.getElementById('assessForm')?.addEventListener('submit', function(e) {
  const student = document.getElementById('student_id').value;
  if (!student) { e.preventDefault(); alert('Please select a student before submitting.'); return; }
  for (const key of Object.keys(weights)) {
    const val = parseFloat(document.getElementById(key).value);
    if (isNaN(val) || val < 0 || val > 10) { e.preventDefault(); alert('All scores must be between 0 and 10.'); return; }
  }
});

updateTotal();
</script>
</body>
</html>