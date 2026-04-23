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

function scalar_prepared(mysqli $conn, string $sql, string $types = '', array $params = []): int {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;

    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $total = 0;
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && ($row = $result->fetch_assoc())) {
            $total = (int)($row['total'] ?? 0);
        }
    }

    $stmt->close();
    return $total;
}

function get_assessor_name(mysqli $conn, int $user_id, string $fallback): string {
    $sql = "SELECT full_name FROM users WHERE user_id = ? AND role IN ('lecturer', 'supervisor') LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return $fallback;

    $stmt->bind_param('i', $user_id);
    $name = $fallback;

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && ($row = $result->fetch_assoc())) {
            if (!empty($row['full_name'])) {
                $name = $row['full_name'];
            }
        }
    }

    $stmt->close();
    return $name;
}

function get_assessor_dashboard_stats(mysqli $conn, int $user_id): array {
    $totalAssigned = scalar_prepared(
        $conn,
        "SELECT COUNT(*) AS total
         FROM internships
         WHERE lecturer_id = ? OR supervisor_id = ?",
        'ii',
        [$user_id, $user_id]
    );

    $pendingCount = scalar_prepared(
        $conn,
        "SELECT COUNT(*) AS total
         FROM internships
         WHERE (lecturer_id = ? OR supervisor_id = ?)
           AND status = 'pending'",
        'ii',
        [$user_id, $user_id]
    );

    $completedCount = scalar_prepared(
        $conn,
        "SELECT COUNT(*) AS total
         FROM internships
         WHERE (lecturer_id = ? OR supervisor_id = ?)
           AND status = 'completed'",
        'ii',
        [$user_id, $user_id]
    );

    return [
        'totalAssigned'   => $totalAssigned,
        'pendingCount'    => $pendingCount,
        'completedCount'  => $completedCount,
    ];
}

function get_my_students_preview(mysqli $conn, int $user_id, int $limit = 5): array {
    $items = [];

    $sql = "
        SELECT
            s.student_id,
            s.full_name AS student_name,
            i.company_name,
            i.status
        FROM internships i
        JOIN students s ON i.student_id = s.student_id
        WHERE (i.lecturer_id = ? OR i.supervisor_id = ?)
        ORDER BY
            FIELD(i.status, 'pending', 'completed', 'unassigned'),
            i.updated_at DESC,
            i.internship_id DESC
        LIMIT ?
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) return $items;

    $stmt->bind_param('iii', $user_id, $user_id, $limit);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $items[] = [
                'student_id'   => $row['student_id'] ?? '',
                'student_name' => $row['student_name'] ?? 'Unknown Student',
                'company_name' => $row['company_name'] ?? '-',
                'status'       => $row['status'] ?? 'pending',
            ];
        }
    }

    $stmt->close();
    return $items;
}

$full_name = get_assessor_name($conn, $user_id, $full_name);
$avatar = get_initials($full_name);
$stats = get_assessor_dashboard_stats($conn, $user_id);
$studentPreview = get_my_students_preview($conn, $user_id, 5);

if (isset($_GET['ajax']) && $_GET['ajax'] === 'dashboard') {
    header('Content-Type: application/json');
    echo json_encode([
        'stats' => get_assessor_dashboard_stats($conn, $user_id),
        'students' => get_my_students_preview($conn, $user_id, 5),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assessor Dashboard | IRMSYS</title>
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
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 28px;
  }

  .stat-card,
  .panel,
  .mini-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
  }

  .stat-card {
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
    font-size: 30px;
    font-weight: 700;
    font-family: var(--mono);
  }

  .stat-value.blue { color: var(--accent); }
  .stat-value.amber { color: var(--warning); }
  .stat-value.green { color: var(--success); }

  .content-grid {
    display: grid;
    grid-template-columns: 1.25fr 0.95fr;
    gap: 16px;
    align-items: start;
  }

  .panel {
    padding: 20px;
  }

  .panel-title {
    font-size: 17px;
    font-weight: 700;
    margin-bottom: 14px;
  }

  .panel-sub {
    font-size: 12.5px;
    color: var(--muted);
    margin-bottom: 18px;
    line-height: 1.6;
  }

  .quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 14px;
  }

  .action-card {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px;
    text-decoration: none;
    color: var(--text);
    transition: all 0.15s ease;
    min-height: 180px;
  }

  .action-card:hover {
    border-color: rgba(79, 142, 247, 0.38);
    transform: translateY(-1px);
  }

  .action-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(79,142,247,0.10);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
  }

  .action-icon.purple {
    background: rgba(124,106,247,0.12);
    color: var(--accent2);
  }

  .action-title {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 10px;
  }

  .action-desc {
    font-size: 13px;
    color: var(--muted);
    line-height: 1.65;
  }

  .student-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .student-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface2);
  }

  .student-meta {
    min-width: 0;
  }

  .student-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 4px;
  }

  .student-sub {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.5;
  }

  .status-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    white-space: nowrap;
  }

  .status-pending {
    background: rgba(240,160,48,0.14);
    color: #f6b451;
    border: 1px solid rgba(240,160,48,0.22);
  }

  .status-completed {
    background: rgba(52,201,123,0.14);
    color: #4fd68a;
    border: 1px solid rgba(52,201,123,0.22);
  }

  .status-unassigned {
    background: rgba(224,85,85,0.12);
    color: #ff8383;
    border: 1px solid rgba(224,85,85,0.18);
  }

  .empty-state {
    padding: 16px;
    border: 1px dashed var(--border);
    border-radius: 10px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.6;
    background: rgba(255,255,255,0.01);
  }

  @media (max-width: 1000px) {
    .content-grid {
      grid-template-columns: 1fr;
    }

    .quick-actions {
      grid-template-columns: 1fr;
    }

    .stats-row {
      grid-template-columns: 1fr;
    }
  }
</style>
</head>
<body>

<nav class="sidebar">
  <div class="logo">IRM<span>SYS</span></div>

  <div class="nav-label">Assessor Panel</div>

  <a class="nav-item active" href="assessor_dashboard.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <rect x="3" y="3" width="7" height="7" rx="1"/>
      <rect x="14" y="3" width="7" height="7" rx="1"/>
      <rect x="3" y="14" width="7" height="7" rx="1"/>
      <rect x="14" y="14" width="7" height="7" rx="1"/>
    </svg>
    Dashboard
  </a>

  <a class="nav-item" href="result_entry.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path d="M12 20h9"/>
      <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
    </svg>
    Enter Results
  </a>

  <a class="nav-item" href="assessor_view_results.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path d="M9 11l3 3L22 4"/>
      <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
    </svg>
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
      <div class="page-title">Welcome back, <?= e($full_name) ?></div>
      <div class="page-sub">Here is a quick overview of your assigned internship evaluations.</div>
    </div>
  </div>

  <section class="stats-row">
    <div class="stat-card">
      <div class="stat-label">Total Assigned</div>
      <div class="stat-value blue"><?= (int)$stats['totalAssigned'] ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Pending Grading</div>
      <div class="stat-value amber"><?= (int)$stats['pendingCount'] ?></div>
    </div>

    <div class="stat-card">
      <div class="stat-label">Completed</div>
      <div class="stat-value green"><?= (int)$stats['completedCount'] ?></div>
    </div>
  </section>

  <section class="content-grid">
    <div class="panel">
      <div class="panel-title">Quick Actions</div>
      <div class="panel-sub">Access the core assessor functions for entering and reviewing internship evaluation results.</div>

      <div class="quick-actions">
        <a href="result_entry.php" class="action-card">
          <div class="action-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path d="M12 20h9"/>
              <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/>
            </svg>
          </div>
          <div class="action-title">Evaluate a Student</div>
          <div class="action-desc">
            Select an assigned student from your roster, enter their rubric scores, and submit the final evaluation.
          </div>
        </a>

        <a href="assessor_view_results.php" class="action-card">
          <div class="action-icon purple">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path d="M9 11l3 3L22 4"/>
              <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
          </div>
          <div class="action-title">Review Submitted Results</div>
          <div class="action-desc">
            View the complete breakdown of marks, final grades, and comments for students you have already assessed.
          </div>
        </a>
      </div>
    </div>

    <div class="panel">
      <div class="panel-title">Assigned Students</div>
      <div class="panel-sub">A quick preview of students currently linked to your internship evaluation roster.</div>

      <?php if (!empty($studentPreview)): ?>
        <div class="student-list">
          <?php foreach ($studentPreview as $item): ?>
            <?php
              $status = strtolower($item['status']);
              $statusClass = 'status-pending';
              if ($status === 'completed') $statusClass = 'status-completed';
              if ($status === 'unassigned') $statusClass = 'status-unassigned';
            ?>
            <div class="student-item">
              <div class="student-meta">
                <div class="student-name"><?= e($item['student_name']) ?></div>
                <div class="student-sub">
                  <?= e($item['student_id']) ?> · <?= e($item['company_name'] ?: '-') ?>
                </div>
              </div>
              <div class="status-badge <?= e($statusClass) ?>">
                <?= e($status) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          No students are currently assigned to your assessor account yet.
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

</body>
</html>