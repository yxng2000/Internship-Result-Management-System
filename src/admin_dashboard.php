<?php
require_once 'auth.php';
requireRole('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard | IRMSYS</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background: #050816;
      color: #ffffff;
      display: flex;
      min-height: 100vh;
    }

    .sidebar {
      width: 250px;
      background: #0b1120;
      border-right: 1px solid rgba(255,255,255,0.08);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 24px 0;
    }

    .logo {
      font-size: 18px;
      font-weight: 700;
      color: #69a7ff;
      padding: 0 24px 24px;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }

    .menu-section {
      padding-top: 20px;
    }

    .menu-title {
      font-size: 12px;
      letter-spacing: 2px;
      color: #7b8194;
      padding: 0 24px 14px;
      text-transform: uppercase;
    }

    .menu a {
      display: block;
      padding: 16px 24px;
      color: #c7d2fe;
      text-decoration: none;
      font-size: 16px;
      transition: 0.2s;
    }

    .menu a:hover,
    .menu a.active {
      background: #16213e;
      color: #6ea8ff;
      border-left: 4px solid #6ea8ff;
      padding-left: 20px;
    }

    .sidebar-footer {
      border-top: 1px solid rgba(255,255,255,0.08);
      padding: 20px 24px 0;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .avatar {
      width: 42px;
      height: 42px;
      border-radius: 50%;
      background: linear-gradient(135deg, #6ea8ff, #7b5cff);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
    }

    .admin-info h4 {
      font-size: 15px;
      font-weight: 600;
    }

    .admin-info p {
      font-size: 12px;
      color: #8b93a7;
    }

    .main {
      flex: 1;
      padding: 34px;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 28px;
    }

    .topbar h1 {
      font-size: 42px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .topbar p {
      color: #98a2b3;
      font-size: 15px;
    }

    .topbar-actions {
      display: flex;
      gap: 12px;
    }

    .btn {
      border: none;
      outline: none;
      padding: 13px 20px;
      border-radius: 14px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
      text-decoration: none;
      display: inline-block;
    }

    .btn-primary {
      background: linear-gradient(135deg, #5f8dff, #6aa5ff);
      color: white;
    }

    .btn-primary:hover {
      opacity: 0.9;
    }

    .btn-dark {
      background: #11182a;
      color: #d7def0;
      border: 1px solid rgba(255,255,255,0.08);
    }

    .btn-dark:hover {
      background: #172036;
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 18px;
      margin-bottom: 28px;
    }

    .card,
    .card-link {
      background: #0d1426;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 20px;
      padding: 22px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }

    .card-link {
      display: block;
      text-decoration: none;
      color: inherit;
      transition: 0.2s;
    }

    .card-link:hover {
      background: #111a31;
      transform: translateY(-2px);
    }

    .card h3,
    .card-link h3 {
      font-size: 13px;
      color: #8f96ab;
      font-weight: 500;
      letter-spacing: 1px;
      margin-bottom: 14px;
      text-transform: uppercase;
    }

    .card .number,
    .card-link .number {
      font-size: 42px;
      font-weight: 700;
    }

    .blue { color: #69a7ff; }
    .green { color: #34d399; }
    .yellow { color: #fbbf24; }
    .purple { color: #8b5cf6; }
    .red { color: #f87171; }
    .cyan { color: #22d3ee; }

    .content-grid {
      display: grid;
      grid-template-columns: 1.5fr 1fr;
      gap: 22px;
      margin-bottom: 24px;
    }

    .panel {
      background: #0d1426;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 22px;
      padding: 24px;
    }

    .panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
    }

    .panel-header h2 {
      font-size: 22px;
      font-weight: 600;
    }

    .quick-actions {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 14px;
    }

    .quick-card {
      background: #111a31;
      border: 1px solid rgba(255,255,255,0.05);
      border-radius: 18px;
      padding: 18px;
      text-decoration: none;
      color: white;
      transition: 0.2s;
    }

    .quick-card:hover {
      background: #16213e;
      transform: translateY(-2px);
    }

    .quick-card h4 {
      font-size: 16px;
      margin-bottom: 6px;
    }

    .quick-card p {
      font-size: 13px;
      color: #98a2b3;
      line-height: 1.5;
    }

    .activity-list,
    .alert-list {
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .activity-item,
    .alert-item {
      background: #111a31;
      border: 1px solid rgba(255,255,255,0.05);
      border-radius: 16px;
      padding: 15px 16px;
    }

    .activity-item h4,
    .alert-item h4 {
      font-size: 15px;
      margin-bottom: 5px;
      font-weight: 600;
    }

    .activity-item p,
    .alert-item p {
      color: #98a2b3;
      font-size: 13px;
      line-height: 1.5;
    }

    .small-tag {
      display: inline-block;
      margin-top: 8px;
      font-size: 12px;
      padding: 5px 10px;
      border-radius: 999px;
      font-weight: 600;
    }

    .tag-pending {
      background: rgba(251,191,36,0.16);
      color: #fbbf24;
    }

    .tag-danger {
      background: rgba(248,113,113,0.16);
      color: #f87171;
    }

    .tag-success {
      background: rgba(52,211,153,0.16);
      color: #34d399;
    }

    .bottom-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 22px;
    }

    .mini-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }

    .mini-table th,
    .mini-table td {
      text-align: left;
      padding: 14px 10px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      font-size: 14px;
    }

    .mini-table th {
      color: #8f96ab;
      font-weight: 500;
      font-size: 13px;
      text-transform: uppercase;
    }

    .status-badge {
      padding: 6px 12px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      display: inline-block;
    }

    .status-pending {
      background: rgba(251,191,36,0.16);
      color: #fbbf24;
    }

    .status-completed {
      background: rgba(52,211,153,0.16);
      color: #34d399;
    }

    .status-unassigned {
      background: rgba(139,92,246,0.16);
      color: #8b5cf6;
    }

    @media (max-width: 1400px) {
      .cards {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    @media (max-width: 1100px) {
      .content-grid,
      .bottom-grid {
        grid-template-columns: 1fr;
      }

      .cards {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 800px) {
      .sidebar {
        display: none;
      }

      .main {
        padding: 20px;
      }

      .cards {
        grid-template-columns: 1fr;
      }

      .topbar {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
      }

      .quick-actions {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

  <aside class="sidebar">
    <div>
      <div class="logo">IRMSYS</div>

      <div class="menu-section">
        <div class="menu-title">Admin Panel</div>
        <nav class="menu">
          <a href="admin_dashboard.php" class="active">Dashboard</a>
          <a href="user_management.php">User Management</a>
          <a href="internship_list.html">Internship Mgmt</a>
          <a href="view_results.html">Results</a>
        </nav>
      </div>
    </div>

    <div class="sidebar-footer">
      <div class="avatar">AD</div>
      <div class="admin-info">
        <h4>Admin User</h4>
        <p>System Administrator</p>
      </div>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <div>
        <h1>Dashboard</h1>
        <p>Overview of internship management system activities and current status</p>
      </div>
      <div class="topbar-actions">
        <a href="user_management.php" class="btn btn-dark">Manage Users</a>
        <a href="add_user.php" class="btn btn-primary">+ Add User</a>
      </div>
    </div>

    <section class="cards">
      <a class="card-link" href="user_management.php" title="Open user management">
        <h3>Total Users</h3>
        <div class="number blue">12</div>
      </a>
      <a class="card-link" href="user_management.php?role=student" title="Open student records in user management">
        <h3>Students</h3>
        <div class="number green">8</div>
      </a>
      <a class="card-link" href="user_management.php?role=assessor" title="Open assessor records in user management">
        <h3>Assessors</h3>
        <div class="number yellow">3</div>
      </a>
      <a class="card-link" href="user_management.php?role=admin" title="Open admin records in user management">
        <h3>Admins</h3>
        <div class="number purple">1</div>
      </a>
      <div class="card">
        <h3>Pending</h3>
        <div class="number red">11</div>
      </div>
      <div class="card">
        <h3>Completed</h3>
        <div class="number cyan">0</div>
      </div>
    </section>

    <section class="content-grid">
      <div class="panel">
        <div class="panel-header">
          <h2>Quick Actions</h2>
        </div>

        <div class="quick-actions">
          <a class="quick-card" href="user_management.php">
            <h4>Manage Users</h4>
            <p>Manage student, assessor, and admin accounts or update existing user information.</p>
          </a>

          <a class="quick-card" href="add_user.php">
            <h4>Add New User</h4>
            <p>Create a new student, assessor, or admin account directly from the user management module.</p>
          </a>

          <a class="quick-card" href="internship_list.php">
            <h4>Manage Internships</h4>
            <p>View, edit, and track all internship assignments and status records.</p>
          </a>

          <a class="quick-card" href="view_results.php">
            <h4>View Results</h4>
            <p>Review submitted evaluations and final assessment results for students.</p>
          </a>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h2>Attention Needed</h2>
        </div>

        <div class="alert-list">
          <div class="alert-item">
            <h4>1 student not assigned</h4>
            <p>There is still one student without an assessor or internship assignment.</p>
            <span class="small-tag tag-danger">Needs action</span>
          </div>

          <div class="alert-item">
            <h4>11 evaluations pending</h4>
            <p>Most internship assessments have not been completed by assessors yet.</p>
            <span class="small-tag tag-pending">Pending</span>
          </div>

          <div class="alert-item">
            <h4>System status normal</h4>
            <p>All major modules are available and user access is functioning properly.</p>
            <span class="small-tag tag-success">Normal</span>
          </div>
        </div>
      </div>
    </section>

    <section class="bottom-grid">
      <div class="panel">
        <div class="panel-header">
          <h2>Recent Activities</h2>
          <a href="user_management.php" class="btn btn-dark">Open Users</a>
        </div>

        <div class="activity-list">
          <div class="activity-item">
            <h4>Ahmad Zulkifli assigned to Dr. Amir</h4>
            <p>Internship company recorded as Axiata. Status is currently pending evaluation.</p>
          </div>

          <div class="activity-item">
            <h4>Nurul Aina assigned to Dr. Lina</h4>
            <p>Internship company recorded as CIMB Tech. Awaiting assessor result entry.</p>
          </div>

          <a class="quick-card" href="user_management.php" style="padding:15px 16px;">
            <h4>New assessor account created</h4>
            <p>Prof. Raj was added into the system and is now available for assignment.</p>
          </a>

          <div class="activity-item">
            <h4>Student internship details updated</h4>
            <p>Priya Rajan’s company information was updated to Grab Malaysia.</p>
          </div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h2>Pending Evaluations</h2>
          <a href="view_results.php" class="btn btn-dark">Open Results</a>
        </div>

        <table class="mini-table">
          <thead>
            <tr>
              <th>Student</th>
              <th>Assessor</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Ahmad Zulkifli</td>
              <td>Dr. Amir</td>
              <td><span class="status-badge status-pending">Pending</span></td>
            </tr>
            <tr>
              <td>Nurul Aina</td>
              <td>Dr. Lina</td>
              <td><span class="status-badge status-pending">Pending</span></td>
            </tr>
            <tr>
              <td>Khairul Hisham</td>
              <td>Prof. Raj</td>
              <td><span class="status-badge status-pending">Pending</span></td>
            </tr>
            <tr>
              <td>Siti Hajar</td>
              <td>Dr. Amir</td>
              <td><span class="status-badge status-pending">Pending</span></td>
            </tr>
            <tr>
              <td>1 Unassigned Student</td>
              <td>-</td>
              <td><span class="status-badge status-unassigned">Unassigned</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>

</body>
</html>