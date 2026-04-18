<?php
session_start();
require_once 'auth.php';
requireRole('student');
require_once 'config.php';

$conn = getConnection();

$user_id = (int)($_SESSION['user_id'] ?? 0);
$full_name = $_SESSION['full_name'] ?? 'Student User';
$student_id = $_SESSION['student_id'] ?? '';

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
<title>My Internship</title>
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
    top: 0; left: 0; bottom: 0;
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
  }

  .page-title {
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -0.02em;
  }

  .page-sub {
    font-size: 13px;
    color: var(--muted);
    margin-top: 4px;
  }

  .status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
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

  .grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
    margin-bottom: 18px;
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
    font-size: 22px;
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
    margin-bottom: 10px;
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

  .details-card {
    margin-bottom: 18px;
  }

  .details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px 22px;
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

  .long-box {
    margin-top: 18px;
    padding: 16px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: var(--surface2);
  }

  .long-text {
    color: var(--text);
    font-size: 14px;
    line-height: 1.6;
  }

  @media (max-width: 920px) {
    .grid-2,
    .details-grid {
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

  <a class="nav-item" href="student_dashboard.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <rect x="3" y="3" width="7" height="7" rx="1"/>
      <rect x="14" y="3" width="7" height="7" rx="1"/>
      <rect x="3" y="14" width="7" height="7" rx="1"/>
      <rect x="14" y="14" width="7" height="7" rx="1"/>
    </svg>
    Dashboard
  </a>

  <a class="nav-item active" href="student_view_internship.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <rect x="2" y="7" width="20" height="14" rx="2"/>
      <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
    </svg>
    View Internship
  </a>

  <a class="nav-item" href="student_view_result.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path d="M9 11l3 3L22 4"/>
      <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
    </svg>
    View Result
  </a>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar" id="userAvatar"><?= e($avatar) ?></div>
      <div class="user-name" id="userName"><?= e($full_name) ?></div>
    </div>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</nav>

<main class="main">

  <div class="page-header">
    <div>
      <div class="page-title">My Internship</div>
      <div class="page-sub">View your internship placement details and contact information</div>
    </div>
    <span class="status-badge status-unassigned" id="statusBadge">—</span>
  </div>

  <div class="grid-2">
    <section class="card">
      <div class="card-title">Student Profile</div>
      <div class="student-name" id="studentName">—</div>
      <div class="student-meta" id="studentProgramme">—</div>
      <div class="student-id" id="studentId">—</div>

      <div class="info-list">
        <div class="info-row">
          <span class="info-label">Student Email</span>
          <span class="info-value" id="studentEmail">—</span>
        </div>
      </div>
    </section>

    <section class="card">
      <div class="card-title">Internship Overview</div>
      <div class="info-list">
        <div class="info-row">
          <span class="info-label">Company</span>
          <span class="info-value" id="overviewCompany">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Assessor</span>
          <span class="info-value" id="overviewAssessor">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Assessor Email</span>
          <span class="info-value" id="overviewAssessorEmail">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Internship ID</span>
          <span class="info-value" id="overviewInternshipId">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Status</span>
          <span class="info-value" id="overviewStatus">—</span>
        </div>
      </div>
    </section>
  </div>

  <section class="card details-card">
    <div class="card-title">Placement Details</div>
    <div class="details-grid">
      <div class="field">
        <div class="field-label">Industry</div>
        <div class="field-value" id="placementIndustry">—</div>
      </div>

      <div class="field">
        <div class="field-label">Internship Period</div>
        <div class="field-value" id="placementPeriod">—</div>
      </div>

      <div class="field">
        <div class="field-label">Duration</div>
        <div class="field-value" id="placementDuration">—</div>
      </div>

      <div class="field">
        <div class="field-label">Last Updated</div>
        <div class="field-value" id="placementUpdated">—</div>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card-title">Notes</div>
    <div class="long-box" style="margin-top:0;">
      <div class="field-label" style="margin-bottom:10px;">Placement Remarks</div>
      <div class="long-text" id="placementNotes">—</div>
    </div>
  </section>

</main>

<script>
  const SESSION_STUDENT_ID = <?= json_encode($student_id) ?>;

  function getInitials(name) {
    if (!name) return "—";

    const words = name.trim().split(" ").filter(Boolean);

    if (words.length === 0) return "—";
    if (words.length === 1) return words[0][0].toUpperCase();

    return (words[0][0] + words[words.length - 1][0]).toUpperCase();
  }

  function formatDate(dateStr) {
    if (!dateStr) return "—";
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString("en-GB", {
      day: "2-digit",
      month: "short",
      year: "numeric"
    });
  }

  function formatDateTime(dateStr) {
    if (!dateStr) return "—";
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleString("en-GB", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit"
    });
  }

  function getStatusClass(status) {
    if (status === "completed") return "status-completed";
    if (status === "pending") return "status-pending";
    return "status-unassigned";
  }

  function capitalize(text) {
    if (!text) return "—";
    return text.charAt(0).toUpperCase() + text.slice(1);
  }

  function calculateDuration(startDate, endDate) {
    if (!startDate || !endDate) return "—";

    const start = new Date(startDate);
    const end = new Date(endDate);

    if (isNaN(start) || isNaN(end) || end < start) return "—";

    let months = (end.getFullYear() - start.getFullYear()) * 12;
    months += end.getMonth() - start.getMonth();

    if (end.getDate() >= start.getDate()) {
      months += 1;
    }

    if (months <= 0) return "Less than 1 month";
    if (months === 1) return "1 month";
    return `${months} months`;
  }

  function buildPeriod(startDate, endDate) {
    if (!startDate || !endDate) return "Not available";
    return `${formatDate(startDate)} – ${formatDate(endDate)}`;
  }

  function loadInternshipDetails(data) {
    document.getElementById("userName").textContent = data.full_name || "Student";
    document.getElementById("userAvatar").textContent = getInitials(data.full_name);

    document.getElementById("studentName").textContent = data.full_name || "—";
    document.getElementById("studentProgramme").textContent = data.programme || "—";
    document.getElementById("studentId").textContent = data.student_id || "—";
    document.getElementById("studentEmail").textContent = data.student_email || "—";

    document.getElementById("overviewCompany").textContent = data.company_name || "Not assigned";
    document.getElementById("overviewAssessor").textContent = data.assessor_name || "Not assigned";
    document.getElementById("overviewAssessorEmail").textContent = data.assessor_email || "Not available";
    document.getElementById("overviewInternshipId").textContent = data.internship_id || "—";
    document.getElementById("overviewStatus").textContent = capitalize(data.status);

    document.getElementById("placementIndustry").textContent = data.industry || "Not available";
    document.getElementById("placementPeriod").textContent = buildPeriod(data.start_date, data.end_date);
    document.getElementById("placementDuration").textContent = calculateDuration(data.start_date, data.end_date);
    document.getElementById("placementUpdated").textContent = data.updated_at ? formatDateTime(data.updated_at) : "Not available";
    document.getElementById("placementNotes").textContent = data.notes || "No notes available.";

    const badge = document.getElementById("statusBadge");
    badge.textContent = capitalize(data.status);
    badge.className = `status-badge ${getStatusClass(data.status)}`;
  }

  function showError(message) {
    document.getElementById("studentName").textContent = "Unable to load";
    document.getElementById("studentProgramme").textContent = message;
    document.getElementById("studentId").textContent = "—";
    document.getElementById("studentEmail").textContent = "—";

    document.getElementById("overviewCompany").textContent = "—";
    document.getElementById("overviewAssessor").textContent = "—";
    document.getElementById("overviewAssessorEmail").textContent = "—";
    document.getElementById("overviewInternshipId").textContent = "—";
    document.getElementById("overviewStatus").textContent = "—";

    document.getElementById("placementIndustry").textContent = "—";
    document.getElementById("placementPeriod").textContent = "—";
    document.getElementById("placementDuration").textContent = "—";
    document.getElementById("placementUpdated").textContent = "—";
    document.getElementById("placementNotes").textContent = "No data available.";

    const badge = document.getElementById("statusBadge");
    badge.textContent = "Unavailable";
    badge.className = "status-badge status-unassigned";
  }

  function loadPage() {
    if (!SESSION_STUDENT_ID) {
      showError("Student session not found.");
      return;
    }

    fetch("get_student_internship.php?student_id=" + encodeURIComponent(SESSION_STUDENT_ID))
      .then(response => response.json())
      .then(result => {
        if (result.success) {
          loadInternshipDetails(result.data);
        } else {
          showError(result.error || "Failed to load internship details.");
        }
      })
      .catch(error => {
        console.error("Error loading internship details:", error);
        showError("Request failed.");
      });
  }

  document.addEventListener("DOMContentLoaded", loadPage);
</script>

</body>
</html>