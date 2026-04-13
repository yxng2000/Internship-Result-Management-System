-- ============================================================
--  COMP1044 — Internship Result Management System
--  Database: comp1044_irms
-- ============================================================

CREATE DATABASE IF NOT EXISTS comp1044_irms;
USE comp1044_irms;

-- ------------------------------------------------------------
-- 1. Users (Admin + Assessors)
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,          -- store hashed password
    full_name   VARCHAR(100) NOT NULL,
    role        ENUM('admin','assessor') NOT NULL,
    email       VARCHAR(100),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 2. Students
-- ------------------------------------------------------------
CREATE TABLE students (
    student_id  VARCHAR(10)  PRIMARY KEY,       -- e.g. S0025
    full_name   VARCHAR(100) NOT NULL,
    programme   VARCHAR(50)  NOT NULL,
    email       VARCHAR(100),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 3. Internships
-- ------------------------------------------------------------
CREATE TABLE internships (
    internship_id   INT AUTO_INCREMENT PRIMARY KEY,
    student_id      VARCHAR(10)  NOT NULL,
    assessor_id     INT,
    company_name    VARCHAR(150) NOT NULL,
    industry        VARCHAR(100),
    start_date      DATE,
    end_date        DATE,
    status          ENUM('assigned','pending','unassigned') DEFAULT 'unassigned',
    notes           TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id)  REFERENCES students(student_id)  ON DELETE CASCADE,
    FOREIGN KEY (assessor_id) REFERENCES users(user_id)        ON DELETE SET NULL
);

-- ------------------------------------------------------------
-- 4. Assessment Results
-- ------------------------------------------------------------
CREATE TABLE assessments (
    assessment_id               INT AUTO_INCREMENT PRIMARY KEY,
    internship_id               INT NOT NULL,
    undertaking_tasks           DECIMAL(5,2) DEFAULT 0,   -- 10%
    health_safety               DECIMAL(5,2) DEFAULT 0,   -- 10%
    theoretical_knowledge       DECIMAL(5,2) DEFAULT 0,   -- 10%
    report_presentation         DECIMAL(5,2) DEFAULT 0,   -- 15%
    clarity_language            DECIMAL(5,2) DEFAULT 0,   -- 10%
    lifelong_learning           DECIMAL(5,2) DEFAULT 0,   -- 15%
    project_management          DECIMAL(5,2) DEFAULT 0,   -- 15%
    time_management             DECIMAL(5,2) DEFAULT 0,   -- 15%
    total_score                 DECIMAL(5,2) DEFAULT 0,   -- auto-calculated
    comments                    TEXT,
    submitted_at                DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (internship_id) REFERENCES internships(internship_id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Sample Data
-- ------------------------------------------------------------

-- Users (passwords are MD5 hashed — use password_hash() in real PHP)
INSERT INTO users (username, password, full_name, role, email) VALUES
('admin',    MD5('admin123'),    'Admin User',    'admin',    'admin@university.edu.my'),
('dr_amir',  MD5('amir123'),     'Dr. Amir',      'assessor', 'amir@university.edu.my'),
('dr_lina',  MD5('lina123'),     'Dr. Lina',      'assessor', 'lina@university.edu.my'),
('prof_raj', MD5('raj123'),      'Prof. Raj',     'assessor', 'raj@university.edu.my');

-- Students
INSERT INTO students (student_id, full_name, programme, email) VALUES
('S0021', 'Ahmad Zulkifli',  'CS', 'ahmad@student.edu.my'),
('S0022', 'Nurul Aina',      'IT', 'nurul@student.edu.my'),
('S0023', 'Khairul Hisham',  'SE', 'khairul@student.edu.my'),
('S0024', 'Siti Hajar',      'CS', 'siti@student.edu.my'),
('S0025', 'Lee Wei Jian',    'IT', 'lee@student.edu.my'),
('S0026', 'Priya Rajan',     'SE', 'priya@student.edu.my'),
('S0027', 'Hafizuddin Malik','CS', 'hafiz@student.edu.my'),
('S0028', 'Amirah Zainudin', 'IT', 'amirah@student.edu.my'),
('S0029', 'Tan Jia Hui',     'CS', 'tan@student.edu.my'),
('S0030', 'Muhammad Faris',  'SE', 'faris@student.edu.my'),
('S0031', 'Nur Syahirah',    'IT', 'nur@student.edu.my'),
('S0032', 'Azrul Nizam',     'CS', 'azrul@student.edu.my');

-- Internships
INSERT INTO internships (student_id, assessor_id, company_name, industry, start_date, end_date, status, notes) VALUES
('S0021', 2, 'Petronas Digital',    'Technology / IT',     '2026-06-01', '2026-10-31', 'assigned',   ''),
('S0022', 3, 'CIMB Tech',          'Finance / Banking',   '2026-06-01', '2026-10-31', 'assigned',   ''),
('S0023', 4, 'Axiata',             'Telecommunications',  '2026-06-01', '2026-10-31', 'pending',    ''),
('S0024', 2, 'Maxis Bhd',          'Telecommunications',  '2026-06-01', '2026-10-31', 'assigned',   ''),
('S0025', NULL, '',                '',                    NULL,         NULL,         'unassigned', ''),
('S0026', 3, 'Grab Malaysia',      'Technology / IT',     '2026-06-01', '2026-10-31', 'pending',    ''),
('S0027', 4, 'Dell Technologies',  'Technology / IT',     '2026-06-01', '2026-10-31', 'assigned',   ''),
('S0028', NULL, '',                '',                    NULL,         NULL,         'unassigned', ''),
('S0029', 2, 'Intel Penang',       'Technology / IT',     '2026-06-01', '2026-10-31', 'assigned',   ''),
('S0030', 3, 'Huawei Malaysia',    'Telecommunications',  '2026-06-01', '2026-10-31', 'pending',    ''),
('S0031', NULL, '',                '',                    NULL,         NULL,         'unassigned', ''),
('S0032', 4, 'TM One',             'Telecommunications',  '2026-06-01', '2026-10-31', 'assigned',   '');
