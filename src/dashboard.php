<?php
session_start();
require_once 'config.php';
$conn = getConnection();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role    = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$total_students = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0];
$total_assessed = $conn->query("SELECT COUNT(*) FROM assessments")->fetch_row()[0];

if ($role === 'assessor') {
    $my_students = $conn->prepare("SELECT COUNT(*) FROM internships WHERE assessor_id = ?");
    $my_students->bind_param("i", $user_id);
    $my_students->execute();
    $assigned_count = $my_students->get_result()->fetch_row()[0];

    $my_assessed = $conn->prepare("
        SELECT COUNT(*) FROM assessments a
        JOIN internships i ON a.internship_id = i.internship_id
        WHERE i.assessor_id = ?
    ");
    $my_assessed->bind_param("i", $user_id);
    $my_assessed->execute();
    $assessed_count = $my_assessed->get_result()->fetch_row()[0];

    $pending_count = $assigned_count - $assessed_count;
} else {
    $assigned_count = $total_students;
    $assessed_count = $total_assessed;
    $pending_count  = $total_students - $total_assessed;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Internship System</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root { --navy:#0f1b2d; --ink:#1a2e45; --teal:#0d7377; --gold:#e8a838; --cream:#f7f3ec; --white:#ffffff; --muted:#6b7a8d; --border:#d8dce3; --shadow:0 4px 24px rgba(15,27,45,.10); }
  *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:'DM Sans',sans-serif; background:var(--cream); color:var(--navy); min-height:100vh; }
  .topbar { background:var(--navy); padding:0 2rem; display:flex; align-items:center; justify-content:space-between; height:62px; position:sticky; top:0; z-index:100; }
  .topbar-brand { font-family:'DM Serif Display',serif; color:var(--white); font-size:1.1rem; }
  .topbar-brand span { color:var(--gold); }
  .topbar-nav { display:flex; gap:.5rem; align-items:center; }
  .topbar-nav a { color:rgba(255,255,255,.7); text-decoration:none; font-size:.85rem; padding:.4rem .9rem; border-radius:6px; transition:all .2s; }
  .topbar-nav a:hover, .topbar-nav a.active { background:rgba(255,255,255,.1); color:var(--white); }
  .topbar-nav a.active { color:var(--gold); }
  .page-wrap { max-width:900px; margin:2.5rem auto; padding:0 1.5rem 4rem; }
  .welcome-banner { background:linear-gradient(135deg,var(--teal) 0%,var(--ink) 100%); border-radius:16px; padding:2rem 2.5rem; margin-bottom:2rem; color:var(--white); }
  .welcome-banner h1 { font-family:'DM Serif Display',serif; font-size:1.8rem; margin-bottom:.3rem; }
  .welcome-banner p { color:rgba(255,255,255,.7); font-size:.92rem; }
  .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1rem; margin-bottom:2rem; }
  .stat-card { background:var(--white); border-radius:12px; padding:1.5rem; box-shadow:var(--shadow); text-align:center; }
  .stat-icon { font-size:2rem; margin-bottom:.5rem; }
  .stat-value { font-family:'DM Serif Display',serif; font-size:2.5rem; color:var(--navy); line-height:1; }
  .stat-label { font-size:.8rem; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); margin-top:.4rem; font-weight:600; }
  .actions-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:1rem; }
  .action-card { background:var(--white); border-radius:16px; padding:1.8rem; box-shadow:var(--shadow); text-decoration:none; color:inherit; display:flex; gap:1.2rem; align-items:center; transition:transform .2s,box-shadow .2s; border:2px solid transparent; }
  .action-card:hover { transform:translateY(-2px); box-shadow:0 8px 32px rgba(15,27,45,.14); border-color:var(--teal); }
  .action-icon { font-size:2.2rem; flex-shrink:0; }
  .action-card h3 { font-family:'DM Serif Display',serif; font-size:1.1rem; margin-bottom:.25rem; }
  .action-card p { font-size:.83rem; color:var(--muted); line-height:1.4; }
</style>
</head>
<body>
<nav class="topbar">
  <div class="topbar-brand">Internship <span>Results</span> System</div>
  <div class="topbar-nav">
    <a href="dashboard.php" class="active">Dashboard</a>
    <?php if ($role === 'assessor'): ?>
      <a href="result_entry.php">Enter Marks</a>
    <?php endif; ?>
    <a href="view_results.php">View Results</a>
    <a href="logout.php">Logout</a>
  </div>
</nav>

<div class="page-wrap">
  <div class="welcome-banner">
    <h1>Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?> 👋</h1>
    <p>Role: <strong><?= ucfirst($role) ?></strong> — <?= date('l, d F Y') ?></p>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-value"><?= $assigned_count ?></div>
      <div class="stat-label"><?= $role === 'assessor' ? 'My Students' : 'Total Students' ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">✅</div>
      <div class="stat-value"><?= $assessed_count ?></div>
      <div class="stat-label">Assessed</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">⏳</div>
      <div class="stat-value"><?= $pending_count ?></div>
      <div class="stat-label">Pending</div>
    </div>
  </div>

  <div class="actions-grid">
    <?php if ($role === 'assessor'): ?>
    <a href="result_entry.php" class="action-card">
      <div class="action-icon">📝</div>
      <div>
        <h3>Enter Assessment</h3>
        <p>Score students on 8 criteria with automatic total calculation</p>
      </div>
    </a>
    <?php endif; ?>
    <a href="view_results.php" class="action-card">
      <div class="action-icon">📊</div>
      <div>
        <h3>View Results</h3>
        <p>See detailed mark breakdowns and search/filter students</p>
      </div>
    </a>
  </div>
</div>
</body>
</html>