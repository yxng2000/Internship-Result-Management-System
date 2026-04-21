-- ============================================================
--  COMP1044 — Internship Result Management System
--  Updated for lecturer + supervisor separation
--  Database: comp1044_irms
-- ============================================================

DROP DATABASE IF EXISTS comp1044_irms;
CREATE DATABASE comp1044_irms;
USE comp1044_irms;

-- ------------------------------------------------------------
-- 1. Students
-- ------------------------------------------------------------
CREATE TABLE students (
    student_id  VARCHAR(10)  PRIMARY KEY,
    full_name   VARCHAR(100) NOT NULL,
    programme   ENUM('Engineering','Arts and Design','Computer Science','Finance') NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    status      ENUM('active','inactive') DEFAULT 'active',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 2. Users
--    - lecturer: belongs to programme
--    - supervisor: belongs to company
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- 3. Internships
--    Split old assessor_id into lecturer_id + supervisor_id
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- 4. Assessment Results
-- ------------------------------------------------------------
CREATE TABLE assessments (
    assessment_id               INT AUTO_INCREMENT PRIMARY KEY,
    internship_id               INT NOT NULL UNIQUE,
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

    FOREIGN KEY (internship_id) REFERENCES internships(internship_id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- 5. Activity Logs
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
-- Sample Data
-- ------------------------------------------------------------

-- Students
INSERT INTO students (student_id, full_name, programme, email, status) VALUES
('S0021', 'Ahmad Zulkifli',   'Computer Science', 'ahmad@student.edu.my', 'active'),
('S0022', 'Nurul Aina',       'Finance',          'nurul@student.edu.my', 'active'),
('S0023', 'Khairul Hisham',   'Engineering',      'khairul@student.edu.my', 'active'),
('S0024', 'Siti Hajar',       'Computer Science', 'siti@student.edu.my', 'active'),
('S0025', 'Lee Wei Jian',     'Arts and Design',  'lee@student.edu.my', 'active'),
('S0026', 'Priya Rajan',      'Finance',          'priya@student.edu.my', 'active'),
('S0027', 'Hafizuddin Malik', 'Computer Science', 'hafiz@student.edu.my', 'active'),
('S0028', 'Amirah Zainudin',  'Arts and Design',  'amirah@student.edu.my', 'active'),
('S0029', 'Tan Jia Hui',      'Computer Science', 'tan@student.edu.my', 'active'),
('S0030', 'Muhammad Faris',   'Engineering',      'faris@student.edu.my', 'active'),
('S0031', 'Nur Syahirah',     'Finance',          'nur@student.edu.my', 'active'),
('S0032', 'Azrul Nizam',      'Engineering',      'azrul@student.edu.my', 'active'),
('S0033', 'Aiman Hakim',      'Computer Science', 'aiman@student.edu.my', 'active'),
('S0034', 'Siti Nur Aisyah',  'Finance',          'aisyah@student.edu.my', 'active'),
('S0035', 'Jason Lim',        'Computer Science', 'jason@student.edu.my', 'active'),
('S0036', 'Nurul Izzah',      'Arts and Design',  'nurul.izzah@student.edu.my', 'active'),
('S0037', 'Daniel Tan',       'Arts and Design',  'daniel@student.edu.my', 'active'),
('S0038', 'Farah Nabila',     'Finance',          'farah@student.edu.my', 'active'),
('S0039', 'Lim Wei Jian',     'Engineering',      'weijian@student.edu.my', 'active'),
('S0040', 'Muhammad Danish',  'Computer Science', 'danish@student.edu.my', 'active'),
('S0041', 'Chloe Ong',        'Arts and Design',  'chloe@student.edu.my', 'active'),
('S0042', 'Ethan Wong',       'Arts and Design',  'ethan@student.edu.my', 'active'),
('S0043', 'Nur Amira',        'Finance',          'amira@student.edu.my', 'active'),
('S0044', 'Goh Jun Hao',      'Engineering',      'junhao@student.edu.my', 'active'),
('S0045', 'Adam Lee',         'Computer Science', 'adam@student.edu.my', 'active');

-- Users (still using MD5 to stay compatible with your current project flow)
-- user_id mapping after insert:
-- 1  = admin
-- 2-13 = lecturers
-- 14-15 = supervisors
-- 16+ = students
INSERT INTO users (username, password, full_name, role, programme, company_name, email, student_id, status) VALUES
('admin',    MD5('admin123'),   'Admin User',       'admin',      NULL,               NULL,              'admin@university.edu.my', NULL, 'active'),

('lec_1001', MD5('lina1234'),    'Dr. Lina',         'lecturer',   'Computer Science', NULL,              'lina@university.edu.my', NULL, 'active'),
('lec_1002', MD5('raj12345'),     'Prof. Raj',        'lecturer',   'Computer Science', NULL,              'raj@university.edu.my', NULL, 'active'),
('lec_1003', MD5('amir1234'),    'Dr. Amir',         'lecturer',   'Computer Science', NULL,              'amir@university.edu.my', NULL, 'active'),
('lec_1004', MD5('amin1234'),    'Dr. Amin Hassan',  'lecturer',   'Arts and Design',  NULL,              'amin@university.edu.my', NULL, 'active'),
('lec_1005', MD5('justin123'),  'Dr. Justin Lee',   'lecturer',   'Arts and Design',  NULL,              'justin@university.edu.my', NULL, 'active'),
('lec_1006', MD5('farah123'),   'Dr. Farah',        'lecturer',   'Engineering',      NULL,              'farah@university.edu.my', NULL, 'active'),
('lec_1007', MD5('kumar123'),   'Dr. Kumar',        'lecturer',   'Engineering',      NULL,              'kumar@university.edu.my', NULL, 'active'),
('lec_1008', MD5('daniel123'),  'Prof. Daniel Ong', 'lecturer',   'Engineering',      NULL,              'daniel@university.edu.my', NULL, 'active'),
('lec_1009', MD5('enna1234'),    'Dr. Enna Tan',     'lecturer',   'Engineering',      NULL,              'enna@university.edu.my', NULL, 'active'),
('lec_1010', MD5('brenda123'),  'Prof. Brenda Lim', 'lecturer',   'Finance',          NULL,              'brenda@university.edu.my', NULL, 'active'),
('lec_1011', MD5('kelvin123'),  'Dr. Kelvin Goh',   'lecturer',   'Finance',          NULL,              'kelvin@university.edu.my', NULL, 'active'),
('lec_1012', MD5('aisyah123'),  'Dr. Aisyah Noor',  'lecturer',   'Finance',          NULL,              'aisyah@university.edu.my', NULL, 'active'),

('sup_2001', MD5('intel123'),   'Mr. John Tan',     'supervisor', NULL,               'Intel Penang',    'john.tan@intel.com', NULL, 'active'),
('sup_2002', MD5('maybank123'), 'Ms. Sarah Lim',    'supervisor', NULL,               'Maybank',         'sarah.lim@maybank.com', NULL, 'active'),

('S0021', MD5('stud0021'), 'Ahmad Zulkifli',   'student', 'Computer Science', NULL, 'ahmad@student.irms.com',          'S0021', 'active'),
('S0022', MD5('stud0022'), 'Nurul Aina',       'student', 'Finance',          NULL, 'nurul@student.irms.com',          'S0022', 'active'),
('S0023', MD5('stud0023'), 'Khairul Hisham',   'student', 'Engineering',      NULL, 'khairul@student.irms.com',        'S0023', 'active'),
('S0024', MD5('stud0024'), 'Siti Hajar',       'student', 'Computer Science', NULL, 'siti@student.irms.com',           'S0024', 'active'),
('S0025', MD5('stud0025'), 'Lee Wei Jian',     'student', 'Arts and Design',  NULL, 'lee@student.irms.com',            'S0025', 'active'),
('S0026', MD5('stud0026'), 'Priya Rajan',      'student', 'Finance',          NULL, 'priya@student.irms.com',          'S0026', 'active'),
('S0027', MD5('stud0027'), 'Hafizuddin Malik', 'student', 'Computer Science', NULL, 'hafiz@student.irms.com',          'S0027', 'active'),
('S0028', MD5('stud0028'), 'Amirah Zainudin',  'student', 'Arts and Design',  NULL, 'amirah@student.irms.com',         'S0028', 'active'),
('S0029', MD5('stud0029'), 'Tan Jia Hui',      'student', 'Computer Science', NULL, 'tan@student.irms.com',            'S0029', 'active'),
('S0030', MD5('stud0030'), 'Muhammad Faris',   'student', 'Engineering',      NULL, 'faris@student.irms.com',          'S0030', 'active'),
('S0031', MD5('stud0031'), 'Nur Syahirah',     'student', 'Finance',          NULL, 'nursyahirah@student.irms.com',    'S0031', 'active'),
('S0032', MD5('stud0032'), 'Azrul Nizam',      'student', 'Engineering',      NULL, 'azrul@student.irms.com',          'S0032', 'active'),
('S0033', MD5('stud0033'), 'Aiman Hakim',      'student', 'Computer Science', NULL, 'aiman@student.irms.com',          'S0033', 'active'),
('S0034', MD5('stud0034'), 'Siti Nur Aisyah',  'student', 'Finance',          NULL, 'aisyah2@student.irms.com',        'S0034', 'active'),
('S0035', MD5('stud0035'), 'Jason Lim',        'student', 'Computer Science', NULL, 'jason@student.irms.com',          'S0035', 'active'),
('S0036', MD5('stud0036'), 'Nurul Izzah',      'student', 'Arts and Design',  NULL, 'izzah@student.irms.com',          'S0036', 'active'),
('S0037', MD5('stud0037'), 'Daniel Tan',       'student', 'Arts and Design',  NULL, 'daniel2@student.irms.com',        'S0037', 'active'),
('S0038', MD5('stud0038'), 'Farah Nabila',     'student', 'Finance',          NULL, 'farahnabila@student.irms.com',    'S0038', 'active'),
('S0039', MD5('stud0039'), 'Lim Wei Jian',     'student', 'Engineering',      NULL, 'weijian@student.irms.com',        'S0039', 'active'),
('S0040', MD5('stud0040'), 'Muhammad Danish',  'student', 'Computer Science', NULL, 'danish@student.irms.com',         'S0040', 'active'),
('S0041', MD5('stud0041'), 'Chloe Ong',        'student', 'Arts and Design',  NULL, 'chloe@student.irms.com',          'S0041', 'active'),
('S0042', MD5('stud0042'), 'Ethan Wong',       'student', 'Arts and Design',  NULL, 'ethan@student.irms.com',          'S0042', 'active'),
('S0043', MD5('stud0043'), 'Nur Amira',        'student', 'Finance',          NULL, 'amira@student.irms.com',          'S0043', 'active'),
('S0044', MD5('stud0044'), 'Goh Jun Hao',      'student', 'Engineering',      NULL, 'junhao@student.irms.com',         'S0044', 'active'),
('S0045', MD5('stud0045'), 'Adam Lee',         'student', 'Computer Science', NULL, 'adam@student.irms.com',           'S0045', 'active');

-- Internships
-- lecturer_id keeps your old academic mapping
-- supervisor_id is only assigned when company matches one of the 2 fixed supervisors
INSERT INTO internships (student_id, lecturer_id, supervisor_id, company_name, industry, start_date, end_date, status, notes) VALUES
('S0021', 2,  14, 'Intel Penang', 'Technology / IT',            '2026-06-01', '2026-10-31', 'pending',    ''),
('S0022', 12, 15, 'Maybank',      'Finance / Banking',          '2026-06-01', '2026-10-31', 'pending',    ''),
('S0023', 9,  14, 'Intel Penang', 'Engineering',                '2026-06-01', '2026-10-31', 'pending',    ''),
('S0024', 3,  14, 'Intel Penang', 'Technology / IT',            '2026-06-01', '2026-10-31', 'completed',  ''),
('S0025', 5,  14, 'Intel Penang', 'Design / Media',             '2026-06-01', '2026-10-31', 'pending',    ''),
('S0026', 13, 15, 'Maybank',      'Finance / Banking',          '2026-06-01', '2026-10-31', 'pending',    ''),
('S0027', 4,  14, 'Intel Penang', 'Technology / IT',            '2026-06-01', '2026-10-31', 'pending',    ''),
('S0028', NULL, NULL, NULL,       NULL,                         NULL,         NULL,         'unassigned', ''),
('S0029', 2,  14, 'Intel Penang', 'Technology / IT',            '2026-06-01', '2026-10-31', 'pending',    ''),
('S0030', 10, 14, 'Intel Penang', 'Engineering / Energy',       '2026-06-01', '2026-10-31', 'pending',    ''),
('S0031', NULL, NULL, NULL,       NULL,                         NULL,         NULL,         'unassigned', ''),
('S0032', 8,  14, 'Intel Penang', 'Engineering',                '2026-06-01', '2026-10-31', 'pending',    ''),
('S0033', 4,  14, 'Intel Penang', 'E-commerce / IT',            '2026-06-01', '2026-10-31', 'pending',    ''),
('S0034', 13, 14, 'Intel Penang', 'Technology / Transport',     '2026-06-01', '2026-10-31', 'pending',    ''),
('S0035', 2,  14, 'Intel Penang', 'Technology / Aviation',      '2026-06-01', '2026-10-31', 'pending',    ''),
('S0036', NULL, NULL, NULL,       NULL,                         NULL,         NULL,         'unassigned', ''),
('S0037', 6,  14, 'Intel Penang', 'Media / Broadcasting',       '2026-06-01', '2026-10-31', 'completed',  ''),
('S0038', 12, 15, 'Maybank',      'Finance / Banking',          '2026-06-01', '2026-10-31', 'pending',    ''),
('S0039', 9,  14, 'Intel Penang', 'Engineering / Electronics',  '2026-06-01', '2026-10-31', 'pending',    ''),
('S0040', 2,  14, 'Intel Penang', 'Technology / Telecom',       '2026-06-01', '2026-10-31', 'pending',    ''),
('S0041', NULL, NULL, NULL,       NULL,                         NULL,         NULL,         'unassigned', ''),
('S0042', 5,  14, 'Intel Penang', 'Design / Advertising',       '2026-06-01', '2026-10-31', 'pending',    ''),
('S0043', 13, 15, 'Maybank',      'Finance / Banking',          '2026-06-01', '2026-10-31', 'pending',    ''),
('S0044', 10, 14, 'Intel Penang', 'Engineering / Construction', '2026-06-01', '2026-10-31', 'pending',    ''),
('S0045', 4,  14, 'Intel Penang', 'Technology / IT',            '2026-06-01', '2026-10-31', 'completed',  '');