DROP DATABASE IF EXISTS comp1044_irms;
CREATE DATABASE comp1044_irms;
USE comp1044_irms;

-- 1. Students
CREATE TABLE students (
    student_id  VARCHAR(10)  PRIMARY KEY,
    full_name   VARCHAR(100) NOT NULL,
    programme   ENUM('Engineering','Arts and Design','Computer Science','Finance') NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    status      ENUM('active','inactive') DEFAULT 'active',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Users
--lecturer: belongs to programme
--supervisor: belongs to company
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin','lecturer','supervisor','student') NOT NULL,
    programme     ENUM('Engineering','Arts and Design','Computer Science','Finance') DEFAULT NULL,
    company_name  VARCHAR(150) DEFAULT NULL,
    email         VARCHAR(100) NOT NULL UNIQUE,
    student_id    VARCHAR(10) DEFAULT NULL,
    status        ENUM('active','inactive') DEFAULT 'active',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE SET NULL
);


-- 3. Internships
CREATE TABLE internships (
    internship_id   INT AUTO_INCREMENT PRIMARY KEY,
    student_id      VARCHAR(10) NOT NULL UNIQUE,
    lecturer_id     INT DEFAULT NULL,
    supervisor_id   INT DEFAULT NULL,
    company_name    VARCHAR(150) DEFAULT NULL,
    industry        VARCHAR(100) DEFAULT NULL,
    start_date      DATE DEFAULT NULL,
    end_date        DATE DEFAULT NULL,
    status          ENUM('unassigned','pending','completed') DEFAULT 'unassigned',
    notes           TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id)    REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (lecturer_id)   REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (supervisor_id) REFERENCES users(user_id) ON DELETE SET NULL
);

-- 4. Assessment Results
CREATE TABLE assessments (
    assessment_id               INT AUTO_INCREMENT PRIMARY KEY,
    internship_id               INT NOT NULL,
    assessor_type               ENUM('lecturer', 'supervisor') NOT NULL,
    undertaking_tasks           DECIMAL(5,2) DEFAULT NULL,   -- 10%
    health_safety               DECIMAL(5,2) DEFAULT NULL,   -- 10%
    theoretical_knowledge       DECIMAL(5,2) DEFAULT NULL,   -- 10%
    report_presentation         DECIMAL(5,2) DEFAULT NULL,   -- 15%
    clarity_language            DECIMAL(5,2) DEFAULT NULL,   -- 10%
    lifelong_learning           DECIMAL(5,2) DEFAULT NULL,   -- 15%
    project_management          DECIMAL(5,2) DEFAULT NULL,   -- 15%
    time_management             DECIMAL(5,2) DEFAULT NULL,   -- 15%
    total_score                 DECIMAL(5,2) DEFAULT NULL,   -- auto-calculated
    comments                    TEXT,
    submitted_at                DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_assessment (internship_id, assessor_type),
    FOREIGN KEY (internship_id) REFERENCES internships(internship_id) ON DELETE CASCADE
);

-- 5. Activity Logs
CREATE TABLE activity_logs (
    log_id       INT AUTO_INCREMENT PRIMARY KEY,
    action_type  VARCHAR(50) NOT NULL,
    target_type  VARCHAR(50) NOT NULL,
    target_id    INT DEFAULT NULL,
    title        VARCHAR(255) NOT NULL,
    description  TEXT NOT NULL,
    link_url     VARCHAR(255) DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);


-- Sample Data
-- Students (10 only)
INSERT INTO students (student_id, full_name, programme, email, status) VALUES
('S0021', 'Ahmad Zulkifli',   'Computer Science', 'ahmad@student.edu.my', 'active'),
('S0022', 'Nurul Aina',       'Finance',          'nurul@student.edu.my', 'active'),
('S0023', 'Khairul Hisham',   'Engineering',      'khairul@student.edu.my', 'active'),
('S0024', 'Siti Hajar',       'Computer Science', 'siti@student.edu.my', 'active'),
('S0025', 'Lee Wei Jian',     'Arts and Design',  'lee@student.edu.my', 'active'),
('S0026', 'Priya Rajan',      'Computer Science', 'priya@student.edu.my', 'active'),
('S0027', 'Hafizuddin Malik', 'Computer Science', 'hafiz@student.edu.my', 'active'),
('S0028', 'Amirah Zainudin',  'Arts and Design',  'amirah@student.edu.my', 'inactive'),
('S0029', 'Tan Jia Hui',      'Finance',          'tan@student.edu.my', 'active'),
('S0030', 'Muhammad Faris',   'Engineering',      'faris@student.edu.my', 'active');

-- Users
INSERT INTO users (username, password, full_name, role, programme, company_name, email, student_id, status) VALUES
('admin',    MD5('admin123'),   'Admin User',       'admin',      NULL,               NULL,              'admin@university.edu.my', NULL, 'active'),

-- Computer Science lecturers (2)
('lec_1001', MD5('lina1234'),   'Dr. Lina',         'lecturer',   'Computer Science', NULL,              'lina@university.edu.my', NULL, 'active'),
('lec_1002', MD5('raj12345'),   'Prof. Raj',        'lecturer',   'Computer Science', NULL,              'raj@university.edu.my', NULL, 'inactive'),

-- Arts and Design lecturers (1)
('lec_1003', MD5('amin1234'),   'Dr. Amin Hassan',  'lecturer',   'Arts and Design',  NULL,              'amin@university.edu.my', NULL, 'active'),

-- Engineering lecturers (2)
('lec_1004', MD5('farah123'),   'Dr. Farah',        'lecturer',   'Engineering',      NULL,              'farah@university.edu.my', NULL, 'active'),
('lec_1005', MD5('kumar123'),   'Dr. Kumar',        'lecturer',   'Engineering',      NULL,              'kumar@university.edu.my', NULL, 'active'),

-- Finance lecturers (2)
('lec_1006', MD5('brenda123'),  'Prof. Brenda Lim', 'lecturer',   'Finance',          NULL,              'brenda@university.edu.my', NULL, 'active'),
('lec_1007', MD5('kelvin123'),  'Dr. Kelvin Goh',   'lecturer',   'Finance',          NULL,              'kelvin@university.edu.my', NULL, 'active'),

-- Supervisors (2)
('sup_2001', MD5('intel123'),   'Mr. John Tan',     'supervisor', NULL,               'Intel Penang',    'john.tan@intel.com', NULL, 'active'),
('sup_2002', MD5('maybank123'), 'Ms. Sarah Lim',    'supervisor', NULL,               'Maybank',         'sarah.lim@maybank.com', NULL, 'active'),

-- Student users (10 only)
('S0021', MD5('stud0021'), 'Ahmad Zulkifli',   'student', 'Computer Science', NULL, 'ahmad@student.irms.com',   'S0021', 'active'),
('S0022', MD5('stud0022'), 'Nurul Aina',       'student', 'Finance',          NULL, 'nurul@student.irms.com',   'S0022', 'active'),
('S0023', MD5('stud0023'), 'Khairul Hisham',   'student', 'Engineering',      NULL, 'khairul@student.irms.com', 'S0023', 'active'),
('S0024', MD5('stud0024'), 'Siti Hajar',       'student', 'Computer Science', NULL, 'siti@student.irms.com',    'S0024', 'active'),
('S0025', MD5('stud0025'), 'Lee Wei Jian',     'student', 'Arts and Design',  NULL, 'lee@student.irms.com',     'S0025', 'active'),
('S0026', MD5('stud0026'), 'Priya Rajan',      'student', 'Computer Science', NULL, 'priya@student.irms.com',   'S0026', 'active'),
('S0027', MD5('stud0027'), 'Hafizuddin Malik', 'student', 'Computer Science', NULL, 'hafiz@student.irms.com',   'S0027', 'active'),
('S0028', MD5('stud0028'), 'Amirah Zainudin',  'student', 'Arts and Design',  NULL, 'amirah@student.irms.com',  'S0028', 'inactive'),
('S0029', MD5('stud0029'), 'Tan Jia Hui',      'student', 'Finance',          NULL, 'tan@student.irms.com',     'S0029', 'active'),
('S0030', MD5('stud0030'), 'Muhammad Faris',   'student', 'Engineering',      NULL, 'faris@student.irms.com',   'S0030', 'active');

-- Internships
-- 1 completed record, 4 pending records, 5 unassigned records.
INSERT INTO internships (student_id, lecturer_id, supervisor_id, company_name, industry, start_date, end_date, status, notes) VALUES
-- Completed: both lecturer and supervisor have submitted assessment rows.
('S0021', 2,  9,  'Intel Penang', 'Technology / IT',   '2025-11-01', '2026-03-31', 'completed',  'Completed demo record. Both assessor results are available.'),

-- Pending with lecturer score only: supervisor can submit during demo to turn it completed.
('S0022', 7, 9,   'Intel Penang', 'Finance / Banking', '2025-11-01', '2026-03-31', 'pending',    'Pending demo record. Lecturer has submitted; supervisor result is still missing.'),

-- Pending with no assessment yet: first result submission keeps it pending.
('S0023', 5,  9,  'Intel Penang', 'Engineering',       '2025-11-01', '2026-03-31', 'pending',    'Pending demo record. No assessment has been submitted yet.'),

-- Pending with supervisor score only: lecturer can submit during demo to turn it completed.
('S0024', 2,  9,  'Maybank',      'Technology / IT',   '2025-11-01', '2026-03-31', 'pending',    'Pending demo record. Supervisor has submitted; lecturer result is still missing.'),

-- Future pending: shows the not-ended warning in result entry and prevents assessment.
('S0025', 4, 10,  'Maybank',      'Education',         '2026-06-01', '2026-10-31', 'pending',    'Future internship demo record. Assessment should be blocked until the end date passes.'),

-- Unassigned: available for Assign Student flow.
('S0026', NULL, NULL, NULL,       NULL,                NULL,         NULL,         'unassigned', ''),
('S0027', NULL, NULL, NULL,       NULL,                NULL,         NULL,         'unassigned', ''),
('S0028', NULL, NULL, NULL,       NULL,                NULL,         NULL,         'unassigned', ''),
('S0029', NULL, NULL, NULL,       NULL,                NULL,         NULL,         'unassigned', ''),
('S0030', NULL, NULL, NULL,       NULL,                NULL,         NULL,         'unassigned', '');

-- Assessment Results
-- Totals equal the sum of the eight criteria columns.
-- S0021 has both scores, S0022 has lecturer-only score, S0024 has supervisor-only score.
INSERT INTO assessments (
    internship_id,
    assessor_type,
    undertaking_tasks,
    health_safety,
    theoretical_knowledge,
    report_presentation,
    clarity_language,
    lifelong_learning,
    project_management,
    time_management,
    total_score,
    comments
) VALUES
(1, 'lecturer',   8.50, 9.00, 8.50, 13.00, 9.00, 14.00, 13.00, 13.00, 88.00, 'Strong technical work and consistent progress throughout the internship.'),
(1, 'supervisor', 9.00, 9.50, 9.00, 14.00, 9.00, 14.00, 13.50, 14.00, 92.00, 'Excellent workplace attitude, communication, and task ownership.'),
(2, 'lecturer',   8.00, 8.00, 8.00, 12.00, 8.00, 12.00, 12.00, 13.00, 81.00, 'Good academic reflection. Waiting for supervisor evaluation to finalize the result.'),
(4, 'supervisor', 8.00, 8.00, 7.00, 12.00, 8.00, 12.00, 11.00, 12.00, 78.00, 'Workplace performance is satisfactory. Lecturer evaluation is still pending.');

-- Activity Logs
-- Seeded so the Admin Dashboard can immediately demonstrate recent activity querying.
INSERT INTO activity_logs 
(action_type, target_type, target_id, title, description, link_url, created_at)
VALUES
('system', 'database', 0, 'Demo-ready data prepared', 'The database was prepared with completed, pending, future, inactive, and unassigned records for system testing.', 'admin_dashboard.php', NOW());