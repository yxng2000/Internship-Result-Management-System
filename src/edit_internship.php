<?php
session_start();
require_once 'auth.php';
requireRole('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Internship — Internship Management</title>
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

  .nav-item:hover { color: var(--text); background: var(--surface2); }
  .nav-item.active { color: var(--accent); border-left-color: var(--accent); background: rgba(79,142,247,0.07); }

  .nav-item svg { flex-shrink: 0; }

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

  .main { margin-left: 220px; flex: 1; padding: 32px 36px; max-width: 980px; }

  .breadcrumb { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--muted); margin-bottom: 20px; }
  .breadcrumb a { color: var(--muted); text-decoration: none; transition: color 0.15s; }
  .breadcrumb a:hover { color: var(--text); }

  .page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 28px; }
  .page-title { font-size: 24px; font-weight: 700; letter-spacing: -0.02em; }
  .page-sub { font-size: 13px; color: var(--muted); margin-top: 4px; }

  .student-banner {
    display: flex;
    align-items: center;
    gap: 16px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 20px;
    margin-bottom: 20px;
  }
  .banner-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0;
  }
  .banner-name { font-size: 15px; font-weight: 600; }
  .banner-meta { font-size: 12px; color: var(--muted); margin-top: 3px; font-family: var(--mono); }
  .banner-right { margin-left: auto; display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
  .status-badge { display: inline-block; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; }
  .status-pending    { background: rgba(240,160,48,0.12); color: var(--warning); }
  .status-unassigned { background: rgba(107,112,128,0.12); color: var(--muted); }
  .status-completed  { background: rgba(52,201,123,0.12); color: var(--success); }
  .last-updated { font-size: 11px; color: var(--muted); }

  .change-banner {
    display: none;
    align-items: center;
    gap: 10px;
    background: rgba(240,160,48,0.07);
    border: 1px solid rgba(240,160,48,0.2);
    border-radius: 8px;
    padding: 10px 16px;
    font-size: 12.5px;
    color: var(--warning);
    margin-bottom: 20px;
  }
  .change-banner.visible { display: flex; }

  .form-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
  .form-section { padding: 22px 24px; border-bottom: 1px solid var(--border); }
  .form-section:last-of-type { border-bottom: none; }

  .section-label { font-size: 11px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
  .section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .field.full { grid-column: 1 / -1; }

  label { font-size: 12.5px; font-weight: 500; color: var(--muted); letter-spacing: 0.02em; }
  .required-star { color: var(--danger); margin-left: 3px; }

  input[type=text], input[type=date], select, textarea {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-family: var(--font);
    font-size: 13.5px;
    padding: 10px 14px;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    width: 100%;
  }

  input[type=text]::placeholder,
  input[type=date]::placeholder,
  textarea::placeholder {
    color: var(--muted);
  }

  input[type=text]:focus,
  input[type=date]:focus,
  select:focus,
  textarea:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(79,142,247,0.12);
  }
  
  input[type=date]::-webkit-calendar-picker-indicator {
    filter: invert(1);
    cursor: pointer;
  }
  
  input.error, select.error, textarea.error { border-color: var(--danger); box-shadow: 0 0 0 3px rgba(224,85,85,0.1); }
  input.changed, select.changed, textarea.changed { border-color: var(--warning); }

  select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7080' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
  }

  textarea { resize: vertical; min-height: 90px; line-height: 1.6; }

  .err-msg { font-size: 11.5px; color: var(--danger); min-height: 16px; display: flex; align-items: center; gap: 4px; opacity: 0; transition: opacity 0.15s; }
  .err-msg.visible { opacity: 1; }

  .assessor-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 4px; }
  .assessor-card { border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; cursor: pointer; transition: all 0.15s; background: var(--surface2); }
  .assessor-card:hover { border-color: var(--accent); background: rgba(79,142,247,0.05); }
  .assessor-card.selected { border-color: var(--accent); background: rgba(79,142,247,0.1); }
  .assessor-card.selected .assessor-name { color: var(--accent); }
  .assessor-card.changed-card { border-color: var(--warning); }
  .assessor-card input[type=radio] { display: none; }
  .assessor-name { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
  .assessor-dept { font-size: 11px; color: var(--muted); }
  .assessor-load { font-size: 11px; color: var(--muted); margin-top: 6px; }
  .load-bar-wrap { height: 3px; background: var(--border); border-radius: 99px; margin-top: 4px; }
  .load-bar { height: 3px; border-radius: 99px; background: var(--accent); }
  .assessor-err { font-size: 11.5px; color: var(--danger); margin-top: 6px; min-height: 16px; opacity: 0; transition: opacity 0.15s; }
  .assessor-err.visible { opacity: 1; }

  .status-row { display: flex; gap: 10px; margin-top: 4px; }
  .status-opt { flex: 1; border: 1px solid var(--border); border-radius: 8px; padding: 10px 14px; cursor: pointer; text-align: center; font-size: 13px; font-weight: 500; background: var(--surface2); transition: all 0.15s; }
  .status-opt:hover { border-color: var(--muted); }
  .status-opt.sel-pending   { border-color: var(--warning); background: rgba(240,160,48,0.1); color: var(--warning); }
  .status-opt.sel-unassigned{ border-color: var(--muted); background: rgba(107,112,128,0.1); color: var(--muted); }

  .form-footer { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; background: var(--surface2); border-top: 1px solid var(--border); }
  .footer-actions { display: flex; gap: 10px; }

  .btn { display: inline-flex; align-items: center; gap: 7px; padding: 10px 20px; border-radius: var(--radius); font-family: var(--font); font-size: 13.5px; font-weight: 600; cursor: pointer; border: none; transition: all 0.15s; text-decoration: none; }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-primary:hover { background: #3d7ef5; }
  .btn-primary:active { transform: scale(0.98); }
  .btn-ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
  .btn-ghost:hover { color: var(--text); border-color: var(--text); }
  .btn-danger { background: transparent; color: var(--danger); border: 1px solid rgba(224,85,85,0.3); }
  .btn-danger:hover { background: rgba(224,85,85,0.08); border-color: var(--danger); }

  .success-overlay { display: none; flex-direction: column; align-items: center; justify-content: center; padding: 56px 24px; text-align: center; }
  .success-overlay.visible { display: flex; }
  .success-icon { width: 56px; height: 56px; border-radius: 50%; background: rgba(52,201,123,0.12); display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
  .success-title { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
  .success-sub { font-size: 13px; color: var(--muted); margin-bottom: 24px; }
  .success-actions { display: flex; gap: 10px; }

  .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 100; align-items: center; justify-content: center; }
  .modal-backdrop.visible { display: flex; }
  .modal { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 28px; max-width: 380px; width: 90%; }
  .modal-title { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
  .modal-body { font-size: 13.5px; color: var(--muted); line-height: 1.6; margin-bottom: 22px; }
  .modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
</style>
</head>
<body>

<nav class="sidebar">
  <div class="logo">IRM<span>sys</span></div>
  <div class="nav-label">Admin Panel</div>

  <a class="nav-item" href="admin_dashboard.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    Dashboard
  </a>
  <a class="nav-item" href="user_management.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    User Management
  </a>
  <a class="nav-item active" href="internship_list.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
    Internship Mgmt
  </a>
  <a class="nav-item" href="view_results.php">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
    Results
  </a>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="avatar">
        <?php
          $displayName = $_SESSION['full_name'] ?? 'Admin User';
          $parts = preg_split('/\s+/', trim($displayName));
          $initials = '';
          foreach ($parts as $part) {
            if ($part !== '') {
              $initials .= strtoupper($part[0]);
            }
            if (strlen($initials) >= 2) break;
          }
          echo htmlspecialchars($initials ?: 'AD');
        ?>
      </div>
      <div class="user-name">
        <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin User'); ?>
      </div>
    </div>

    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</nav>

<main class="main">
  <div class="breadcrumb">
    <a href="internship_list.php">Internship Management</a>
    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span>Edit Internship</span>
  </div>

  <div class="page-header">
    <div>
      <div class="page-title">Edit Internship Record</div>
      <div class="page-sub">Update assignment, company, or status details</div>
    </div>
  </div>

  <div class="student-banner">
    <div class="banner-avatar" id="bannerAvatar">AZ</div>
    <div>
      <div class="banner-name" id="bannerName">Amirah Zainudin</div>
      <div class="banner-meta" id="bannerMeta">S0028 · IT</div>
    </div>
    <div class="banner-right">
      <span class="status-badge status-pending" id="bannerStatus">Pending</span>
      <span class="last-updated">Last updated: 12/03/2026</span>
    </div>
  </div>

  <div class="change-banner" id="changeBanner">
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    You have unsaved changes — remember to save before leaving this page.
  </div>

  <div class="form-card" id="formCard">

    <div class="form-section">
      <div class="section-label">Lecturer Assignment</div>
      <label style="font-size:12.5px; font-weight:500; color:var(--muted); margin-bottom:8px; display:block;">
        Assigned Lecturer <span class="required-star">*</span>
      </label>
      <div class="assessor-grid" id="assessorGrid"></div>
      <div class="assessor-err" id="err-lecturer">Please select a lecturer.</div>
    </div>

    <div class="form-section">
      <div class="section-label">Internship Details</div>
      <div class="form-grid">

        <div class="field">
          <label>Company Name <span class="required-star">*</span></label>
          <select id="companyName" onchange="handleCompanyChange(); markChanged(this);">
            <option value="">— Select company —</option>
          </select>
          <div class="err-msg" id="err-company">Company name is required.</div>
          <div style="font-size:12px; color:var(--muted); margin-top:8px;">
            Supervisor: <span id="supervisorDisplay" style="color:var(--text); font-weight:600;">—</span>
          </div>
          <input type="hidden" id="supervisorId" value="">
        </div>

        <div class="field">
          <label>Industry <span class="required-star">*</span></label>
          <select id="industry" onchange="markChanged(this)">
            <option value="">— Select industry —</option>
            <option value="Technology / IT">Technology / IT</option>
            <option value="Finance / Banking">Finance / Banking</option>
            <option value="Telecommunications">Telecommunications</option>
            <option value="Oil & Gas">Oil & Gas</option>
            <option value="Manufacturing">Manufacturing</option>
            <option value="Healthcare">Healthcare</option>
            <option value="Education">Education</option>
            <option value="Other">Other</option>
          </select>
          <div class="err-msg" id="err-industry">Industry is required.</div>
        </div>

        <div class="field">
          <label>Start Date <span class="required-star">*</span></label>
          <input type="date" id="startDate" value="" oninput="markChanged(this);">
          <div class="err-msg" id="err-start">Please enter a valid start date.</div>
        </div>

        <div class="field">
          <label>End Date <span class="required-star">*</span></label>
          <input type="date" id="endDate" value="" oninput="markChanged(this);">
          <div class="err-msg" id="err-end">End date must be after start date.</div>
        </div>

        <div class="field full">
          <label>Additional Notes</label>
          <textarea id="notes" oninput="markChanged(this);"></textarea>
          <div class="err-msg"></div>
        </div>

      </div>
    </div>

    <div class="form-section">
      <div class="section-label">Assignment Status</div>
      <label style="font-size:12.5px; font-weight:500; color:var(--muted); margin-bottom:8px; display:block;">Current Status</label>
      <div class="status-row">
        <div class="status-opt" id="opt-pending" onclick="selectStatus('pending')">Pending</div>
        <div class="status-opt" id="opt-unassigned" onclick="selectStatus('unassigned')">Unassigned</div>
      </div>
    </div>

    <div class="form-footer">
      <button class="btn btn-danger" id="deleteBtn" onclick="showDeleteModal()">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M9 6V4h6v2"/></svg>
        Delete Record
      </button>
      <div class="footer-actions">
        <a href="internship_list.php" class="btn btn-ghost">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          Cancel
        </a>
        <button class="btn btn-primary" id="saveBtn" onclick="submitForm()">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Save Changes
        </button>
      </div>
    </div>

  </div>

  <div class="form-card success-overlay" id="successOverlay">
    <div class="success-icon">
      <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#34c97b" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <div class="success-title">Changes Saved</div>
    <div class="success-sub" id="successMsg">The internship record has been updated successfully.</div>
    <div class="success-actions">
      <a href="internship_list.php" class="btn btn-ghost">Back to List</a>
      <a href="internship_list.php" class="btn btn-primary">Done</a>
    </div>
  </div>
</main>

<div class="modal-backdrop" id="deleteModal">
  <div class="modal">
    <div class="modal-title">Delete this record?</div>
    <div class="modal-body">This will permanently remove the internship record for <strong id="deleteStudentName">Student</strong>. This action cannot be undone.</div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="hideDeleteModal()">Cancel</button>
      <button class="btn btn-danger" onclick="confirmDelete()">Yes, delete</button>
    </div>
  </div>
</div>

<script>
  const params = new URLSearchParams(window.location.search);
  const internshipId = parseInt(params.get("id"), 10);

  let record = {};
  let lecturers = [];
  let companies = [];
  let companySupervisorMap = {};
  let originalValues = {};
  let selectedLecturerId = 0;
  let selectedLecturerName = "";
  let selectedSupervisorId = 0;
  let selectedStatus = "";
  let hasChanges = false;

  const MAX_STUDENTS_PER_LECTURER = 10;

  async function loadRecord() {
    if (!internshipId) {
      alert("Invalid internship ID.");
      return;
    }

    try {
      const response = await fetch(`edit_internship_handler.php?id=${internshipId}`);
      const result = await response.json();
      console.log("GET result:", result);

      if (!result.success) {
        alert(result.error || "Failed to load record.");
        return;
      }

      record = result.record;
      lecturers = result.lecturers || [];
      companies = result.companies || [];
      companySupervisorMap = result.company_supervisor_map || {};

      selectedLecturerId = parseInt(record.lecturer_id, 10) || 0;
      selectedLecturerName = record.lecturer_name || "";
      selectedSupervisorId = parseInt(record.supervisor_id, 10) || 0;
      selectedStatus = record.status || "pending";

      originalValues = {
        lecturer: record.lecturer_name || "",
        supervisor_id: record.supervisor_id || "",
        company: record.company_name || "",
        industry: record.industry || "",
        start: record.start_date || "",
        end: record.end_date || "",
        status: record.status || "",
        notes: record.notes || ""
      };

      document.getElementById("bannerName").textContent = record.full_name || "";
      document.getElementById("bannerMeta").textContent = `${record.student_id || ""} · ${record.programme || ""}`;
      document.getElementById("bannerAvatar").textContent =
        (record.full_name || "")
          .split(" ")
          .map(x => x[0])
          .slice(0, 2)
          .join("")
          .toUpperCase();

      document.getElementById("bannerStatus").textContent =
        selectedStatus.charAt(0).toUpperCase() + selectedStatus.slice(1);
      document.getElementById("bannerStatus").className =
        "status-badge status-" + selectedStatus;

      document.querySelector(".last-updated").textContent =
        "Last updated: " + (record.last_updated || "-");

      renderLecturers();
      renderCompanyOptions();

      document.getElementById("industry").value = record.industry || "";
      document.getElementById("startDate").value = record.start_date || "";
      document.getElementById("endDate").value = record.end_date || "";
      document.getElementById("notes").value = record.notes || "";

      document.querySelectorAll(".status-opt").forEach(o => o.className = "status-opt");
      const currentStatusOption = document.getElementById("opt-" + selectedStatus);
      if (currentStatusOption) {
        currentStatusOption.classList.add("sel-" + selectedStatus);
      }

      if (result.is_locked) {
        disableForm();
      }

      hasChanges = false;
      document.getElementById("changeBanner").classList.remove("visible");

    } catch (err) {
      console.error("loadRecord error:", err);
      alert("Failed to load record.");
    }
  }

  function renderLecturers() {
    const grid = document.getElementById("assessorGrid");
    grid.innerHTML = "";

    const filteredLecturers = lecturers.filter(l => l.programme === record.programme);

    if (!filteredLecturers.length) {
      grid.innerHTML = `<div style="color:var(--muted); font-size:13px;">No lecturers available for ${record.programme}.</div>`;
      return;
    }

    filteredLecturers.forEach(l => {
      const userId = parseInt(l.user_id, 10);
      const count = parseInt(l.student_count, 10) || 0;
      const percent = Math.min((count / MAX_STUDENTS_PER_LECTURER) * 100, 100);

      const card = document.createElement("label");
      card.className = "assessor-card";
      card.dataset.id = userId;
      card.dataset.name = l.full_name;

      if (userId === selectedLecturerId) {
        card.classList.add("selected");
      }

      card.innerHTML = `
        <input type="radio" name="lecturer" value="${userId}" ${userId === selectedLecturerId ? "checked" : ""}>
        <div class="assessor-name">${l.full_name}</div>
        <div class="assessor-dept">${l.programme || ""}</div>
        <div class="assessor-load">${count} / ${MAX_STUDENTS_PER_LECTURER} students</div>
        <div class="load-bar-wrap">
          <div class="load-bar" style="width:${percent}%"></div>
        </div>
      `;

      card.addEventListener("click", () => {
        selectLecturer(card, userId, l.full_name);
      });

      grid.appendChild(card);
    });
  }

  function renderCompanyOptions() {
    const companySelect = document.getElementById("companyName");
    companySelect.innerHTML = `<option value="">— Select company —</option>`;

    companies.forEach(company => {
      const option = document.createElement("option");
      option.value = company.company_name;
      option.textContent = company.company_name;
      companySelect.appendChild(option);
    });

    if (record.company_name) {
      companySelect.value = record.company_name;
      handleCompanyChange(true);
    }
  }

  function handleCompanyChange(isInitialLoad = false) {
    const company = document.getElementById("companyName").value;
    const supervisorDisplay = document.getElementById("supervisorDisplay");
    const supervisorIdInput = document.getElementById("supervisorId");

    if (!company || !companySupervisorMap[company]) {
      supervisorDisplay.textContent = "—";
      supervisorIdInput.value = "";
      selectedSupervisorId = 0;
      return;
    }

    supervisorDisplay.textContent = companySupervisorMap[company].supervisor_name || "—";
    supervisorIdInput.value = companySupervisorMap[company].supervisor_id || "";
    selectedSupervisorId = parseInt(companySupervisorMap[company].supervisor_id, 10) || 0;

    if (!isInitialLoad) {
      showChangeBanner();
    }
  }

  function selectLecturer(card, id, name) {
    document.querySelectorAll(".assessor-card").forEach(c => {
      c.classList.remove("selected", "changed-card");
    });

    document.querySelectorAll('input[name="lecturer"]').forEach(r => {
      r.checked = false;
    });

    card.classList.add("selected");
    const radio = card.querySelector('input[name="lecturer"]');
    if (radio) radio.checked = true;

    if (name !== originalValues.lecturer) {
      card.classList.add("changed-card");
    }

    selectedLecturerId = id;
    selectedLecturerName = name;

    document.getElementById("err-lecturer").classList.remove("visible");
    showChangeBanner();
  }

  function showChangeBanner() {
    hasChanges = true;
    document.getElementById("changeBanner").classList.add("visible");
  }

  function markChanged(el) {
    if (el) el.classList.add("changed");
    showChangeBanner();
  }

  function selectStatus(s) {
    selectedStatus = s;
    document.querySelectorAll(".status-opt").forEach(o => o.className = "status-opt");

    const currentStatusOption = document.getElementById("opt-" + selectedStatus);
    if (currentStatusOption) {
      currentStatusOption.classList.add("sel-" + selectedStatus);
    }

    const badge = document.getElementById("bannerStatus");
    badge.className = "status-badge status-" + s;
    badge.textContent = s.charAt(0).toUpperCase() + s.slice(1);

    if (s === "unassigned") {
      document.getElementById("companyName").value = "";
      document.getElementById("supervisorDisplay").textContent = "—";
      document.getElementById("supervisorId").value = "";
      selectedSupervisorId = 0;
    } else if (record.company_name && document.getElementById("companyName").value) {
      handleCompanyChange(true);
    }

    showChangeBanner();
  }

  function isValidDate(str) {
    return str && !isNaN(new Date(str).getTime());
  }

  function dateToNum(str) {
    return Number(str.replaceAll("-", ""));
  }

  function showError(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = "⚠ " + msg;
    el.classList.add("visible");
  }

  function clearError(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove("visible");
  }

  function markField(id, hasError) {
    const el = document.getElementById(id);
    if (!el) return;
    if (hasError) el.classList.add("error");
    else el.classList.remove("error");
  }

  async function submitForm() {
    let valid = true;

    if (selectedStatus !== "unassigned" && (!selectedLecturerId || selectedLecturerId <= 0)) {
      document.getElementById("err-lecturer").classList.add("visible");
      valid = false;
    } else {
      document.getElementById("err-lecturer").classList.remove("visible");
    }

    const company = document.getElementById("companyName").value.trim();
    const industry = document.getElementById("industry").value;
    const start = document.getElementById("startDate").value.trim();
    const end = document.getElementById("endDate").value;

    const supervisorId = document.getElementById("supervisorId").value;

    if (selectedStatus !== "unassigned" && !company) {
      showError("err-company", "Company name is required.");
      markField("companyName", true);
      valid = false;
    } else {
      clearError("err-company");
      markField("companyName", false);
    }

    if (selectedStatus !== "unassigned" && !industry) {
      showError("err-industry", "Industry is required.");
      markField("industry", true);
      valid = false;
    } else {
      clearError("err-industry");
      markField("industry", false);
    }

    if (selectedStatus !== "unassigned") {
      if (!isValidDate(start)) {
        showError("err-start", "Please enter a valid start date (DD/MM/YYYY).");
        markField("startDate", true);
        valid = false;
      } else {
        clearError("err-start");
        markField("startDate", false);
      }

      if (!isValidDate(end)) {
        showError("err-end", "Please enter a valid end date (DD/MM/YYYY).");
        markField("endDate", true);
        valid = false;
      } else if (isValidDate(start) && dateToNum(end) <= dateToNum(start)) {
        showError("err-end", "End date must be after start date.");
        markField("endDate", true);
        valid = false;
      } else {
        clearError("err-end");
        markField("endDate", false);
      }

      if (!supervisorId || parseInt(supervisorId, 10) <= 0) {
        alert("Please select a valid company with supervisor.");
        valid = false;
      }
    } else {
      clearError("err-start");
      clearError("err-end");
      markField("startDate", false);
      markField("endDate", false);
    }

    if (!valid) return;

    const payload = {
      internship_id: internshipId,
      lecturer_id: selectedStatus === "unassigned" ? "" : selectedLecturerId,
      supervisor_id: selectedStatus === "unassigned" ? "" : supervisorId,
      company_name: selectedStatus === "unassigned" ? "" : company,
      industry: selectedStatus === "unassigned" ? "" : industry,
      start_date: selectedStatus === "unassigned" ? "" : start,
      end_date: selectedStatus === "unassigned" ? "" : end,
      status: selectedStatus,
      notes: document.getElementById("notes").value.trim()
    };

    try {
      const response = await fetch("edit_internship_handler.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify(payload)
      });

      const result = await response.json();
      console.log(result);

      if (!result.success) {
        alert((result.errors && result.errors.join("\n")) || result.error || "Update failed.");
        return;
      }

      hasChanges = false;
      document.getElementById("changeBanner").classList.remove("visible");

      await loadRecord();

      document.getElementById("successMsg").textContent =
        "Internship record for " + (record.full_name || "this student") + " has been updated successfully.";

      document.getElementById("formCard").style.display = "none";
      document.getElementById("successOverlay").classList.add("visible");
    } catch (err) {
      console.error(err);
      alert("Request failed.");
    }
  }

  function showDeleteModal() {
    document.getElementById("deleteStudentName").textContent = record.full_name || "this student";
    document.getElementById("deleteModal").classList.add("visible");
  }

  function hideDeleteModal() {
    document.getElementById("deleteModal").classList.remove("visible");
  }

  function confirmDelete() {
    hideDeleteModal();
    window.location.href = "internship_list.php";
  }

  function disableForm() {
    document.querySelectorAll("input, textarea, select").forEach(el => {
      el.disabled = true;
    });

    document.querySelectorAll(".status-opt, .assessor-card").forEach(el => {
      el.style.pointerEvents = "none";
      el.style.opacity = "0.5";
    });

    const saveBtn = document.getElementById("saveBtn");
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.style.opacity = "0.5";
      saveBtn.style.pointerEvents = "none";
    }

    const deleteBtn = document.getElementById("deleteBtn");
    if (deleteBtn) {
      deleteBtn.disabled = true;
      deleteBtn.style.opacity = "0.5";
      deleteBtn.style.pointerEvents = "none";
    }
  }

  document.getElementById("companyName").addEventListener("change", () => {
    clearError("err-company");
    markField("companyName", false);
  });

  document.getElementById("industry").addEventListener("change", () => {
    clearError("err-industry");
    markField("industry", false);
    showChangeBanner();
  });

  document.getElementById("startDate").addEventListener("input", () => {
    clearError("err-start");
    markField("startDate", false);
  });

  document.getElementById("endDate").addEventListener("input", () => {
    clearError("err-end");
    markField("endDate", false);
  });

  window.addEventListener("beforeunload", e => {
    if (hasChanges) {
      e.preventDefault();
      e.returnValue = "";
    }
  });

  window.addEventListener("DOMContentLoaded", loadRecord);
</script>
</body>
</html>