-- ============================================================
--  COMP1044 — Internship Result Management System
--  Database: comp1044_irms
-- ============================================================

DROP DATABASE IF EXISTS comp1044_irms;
CREATE DATABASE comp1044_irms;
USE comp1044_irms;

-- ------------------------------------------------------------
-- 1. Students
-- ------------------------------------------------------------
CREATE TABLE students (
    student_id  VARCHAR(10)  PRIMARY KEY,       -- e.g. S0025
    full_name   VARCHAR(100) NOT NULL,
    programme   ENUM('Engineering','Arts and Design','Computer Science','Finance') NOT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    status      ENUM('active','inactive') DEFAULT 'active',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 2. Users 
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,          -- store hashed password
    full_name   VARCHAR(100) NOT NULL,
    role        ENUM('admin','assessor','student') NOT NULL,
    programme   ENUM('Engineering','Arts and Design','Computer Science','Finance') DEFAULT NULL,
    email       VARCHAR(100) NOT NULL UNIQUE,
    student_id  VARCHAR(10) DEFAULT NULL,
    status      ENUM('active','inactive') DEFAULT 'active',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id) REFERENCES students(student_id)  ON DELETE SET NULL
);

-- ------------------------------------------------------------
-- 3. Internships
-- ------------------------------------------------------------
CREATE TABLE internships (
    internship_id   INT AUTO_INCREMENT PRIMARY KEY,
    student_id      VARCHAR(10) NOT NULL UNIQUE,
    assessor_id     INT DEFAULT NULL,
    company_name    VARCHAR(150) DEFAULT NULL,
    industry        VARCHAR(100) DEFAULT NULL,
    start_date      DATE DEFAULT NULL,
    end_date        DATE DEFAULT NULL,
    status          ENUM('unassigned','pending','completed') DEFAULT 'unassigned',
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

-- Users (passwords are MD5 hashed — use password_hash() in real PHP)
INSERT INTO users (username, password, full_name, role, programme, email, student_id, status) VALUES
('admin',   MD5('admin123'),   'Admin User',       'admin',    NULL,                 'admin@university.edu.my', NULL, 'active'),

('as_1001', MD5('lina123'),    'Dr. Lina',         'assessor', 'Computer Science',   'lina@university.edu.my', NULL, 'active'),
('as_1002', MD5('raj123'),     'Prof. Raj',        'assessor', 'Computer Science',   'raj@university.edu.my', NULL, 'active'),
('as_1003', MD5('amir123'),    'Dr. Amir',         'assessor', 'Computer Science',   'amir@university.edu.my', NULL, 'active'),
('as_1004', MD5('amin123'),    'Dr. Amin Hassan',  'assessor', 'Arts and Design',    'amin@university.edu.my', NULL, 'active'),
('as_1005', MD5('justin123'),  'Dr. Justin Lee',   'assessor', 'Arts and Design',    'justin@university.edu.my', NULL, 'active'),
('as_1006', MD5('farah123'),   'Dr. Farah',        'assessor', 'Engineering',        'farah@university.edu.my', NULL, 'active'),
('as_1007', MD5('kumar123'),   'Dr. Kumar',        'assessor', 'Engineering',        'kumar@university.edu.my', NULL, 'active'),
('as_1008', MD5('daniel123'),  'Prof. Daniel Ong', 'assessor', 'Engineering',        'daniel@university.edu.my', NULL, 'active'),
('as_1009', MD5('enna123'),    'Dr. Enna Tan',     'assessor', 'Engineering',        'enna@university.edu.my', NULL, 'active'),
('as_1010', MD5('brenda123'),  'Prof. Brenda Lim', 'assessor', 'Finance',            'brenda@university.edu.my', NULL, 'active'),
('as_1011', MD5('kelvin123'),  'Dr. Kelvin Goh',   'assessor', 'Finance',            'kelvin@university.edu.my', NULL, 'active'),
('as_1012', MD5('aisyah123'),  'Dr. Aisyah Noor',  'assessor', 'Finance',            'aisyah@university.edu.my', NULL, 'active'),

('S0021', MD5('stud0021'), 'Ahmad Zulkifli',   'student', 'Computer Science', 'ahmad@student.irms.com',   'S0021', 'active'),
('S0022', MD5('stud0022'), 'Nurul Aina',       'student', 'Finance',          'nurul@student.irms.com',   'S0022', 'active'),
('S0023', MD5('stud0023'), 'Khairul Hisham',   'student', 'Engineering',      'khairul@student.irms.com', 'S0023', 'active'),
('S0024', MD5('stud0024'), 'Siti Hajar',       'student', 'Computer Science', 'siti@student.irms.com',    'S0024', 'active'),
('S0025', MD5('stud0025'), 'Lee Wei Jian',     'student', 'Arts and Design',  'lee@student.irms.com',     'S0025', 'active'),
('S0026', MD5('stud0026'), 'Priya Rajan',      'student', 'Finance',          'priya@student.irms.com',   'S0026', 'active'),
('S0027', MD5('stud0027'), 'Hafizuddin Malik', 'student', 'Computer Science', 'hafiz@student.irms.com',   'S0027', 'active'),
('S0028', MD5('stud0028'), 'Amirah Zainudin',  'student', 'Arts and Design',  'amirah@student.irms.com',  'S0028', 'active'),
('S0029', MD5('stud0029'), 'Tan Jia Hui',      'student', 'Computer Science', 'tan@student.irms.com',     'S0029', 'active'),
('S0030', MD5('stud0030'), 'Muhammad Faris',   'student', 'Engineering',      'faris@student.irms.com',   'S0030', 'active'),
('S0031', MD5('stud0031'), 'Nur Syahirah',     'student', 'Finance',          'nursyahirah@student.irms.com', 'S0031', 'active'),
('S0032', MD5('stud0032'), 'Azrul Nizam',      'student', 'Engineering',      'azrul@student.irms.com',   'S0032', 'active'),
('S0033', MD5('stud0033'), 'Aiman Hakim',      'student', 'Computer Science', 'aiman@student.irms.com',   'S0033', 'active'),
('S0034', MD5('stud0034'), 'Siti Nur Aisyah',  'student', 'Finance',          'aisyah2@student.irms.com', 'S0034', 'active'),
('S0035', MD5('stud0035'), 'Jason Lim',        'student', 'Computer Science', 'jason@student.irms.com',   'S0035', 'active'),
('S0036', MD5('stud0036'), 'Nurul Izzah',      'student', 'Arts and Design',  'izzah@student.irms.com',   'S0036', 'active'),
('S0037', MD5('stud0037'), 'Daniel Tan',       'student', 'Arts and Design',  'daniel2@student.irms.com', 'S0037', 'active'),
('S0038', MD5('stud0038'), 'Farah Nabila',     'student', 'Finance',          'farahnabila@student.irms.com', 'S0038', 'active'),
('S0039', MD5('stud0039'), 'Lim Wei Jian',     'student', 'Engineering',      'weijian@student.irms.com', 'S0039', 'active'),
('S0040', MD5('stud0040'), 'Muhammad Danish',  'student', 'Computer Science', 'danish@student.irms.com',  'S0040', 'active'),
('S0041', MD5('stud0041'), 'Chloe Ong',        'student', 'Arts and Design',  'chloe@student.irms.com',   'S0041', 'active'),
('S0042', MD5('stud0042'), 'Ethan Wong',       'student', 'Arts and Design',  'ethan@student.irms.com',   'S0042', 'active'),
('S0043', MD5('stud0043'), 'Nur Amira',        'student', 'Finance',          'amira@student.irms.com',   'S0043', 'active'),
('S0044', MD5('stud0044'), 'Goh Jun Hao',      'student', 'Engineering',      'junhao@student.irms.com',  'S0044', 'active'),
('S0045', MD5('stud0045'), 'Adam Lee',         'student', 'Computer Science', 'adam@student.irms.com',    'S0045', 'active');

-- Internships
INSERT INTO internships (student_id, assessor_id, company_name, industry, start_date, end_date, status, notes) VALUES
('S0021', 2,  'Petronas Digital',       'Technology / IT',            '2026-06-01', '2026-10-31', 'pending',    ''),
('S0022', 12, 'CIMB Group',             'Finance / Banking',          '2026-06-01', '2026-10-31', 'pending',    ''),
('S0023', 9,  'Siemens Malaysia',       'Engineering',                '2026-06-01', '2026-10-31', 'pending',    ''),
('S0024', 3,  'Intel Penang',           'Technology / IT',            '2026-06-01', '2026-10-31', 'completed',  ''),
('S0025', 5,  'Mediaprima Creative',    'Design / Media',             '2026-06-01', '2026-10-31', 'pending',    ''),
('S0026', 13, 'Maybank',                'Finance / Banking',          '2026-06-01', '2026-10-31', 'pending',    ''),
('S0027', 4,  'Dell Technologies',      'Technology / IT',            '2026-06-01', '2026-10-31', 'pending',    ''),
('S0028', NULL, NULL,                   NULL,                         NULL,         NULL,         'unassigned', ''),
('S0029', 2,  'TM One',                 'Technology / IT',            '2026-06-01', '2026-10-31', 'pending',    ''),
('S0030', 10, 'Tenaga Nasional Berhad', 'Engineering / Energy',       '2026-06-01', '2026-10-31', 'pending',    ''),
('S0031', NULL, NULL,                   NULL,                         NULL,         NULL,         'unassigned', ''),
('S0032', 11, 'Bosch Malaysia',         'Engineering',                '2026-06-01', '2026-10-31', 'pending',    ''),
('S0033', 4,  'Shopee Malaysia',        'E-commerce / IT',            '2026-06-01', '2026-10-31', 'pending',    ''),
('S0034', 13, 'Grab Malaysia',          'Technology / Transport',     '2026-06-01', '2026-10-31', 'pending',    ''),
('S0035', 2,  'AirAsia Digital',        'Technology / Aviation',      '2026-06-01', '2026-10-31', 'pending',    ''),
('S0036', NULL, NULL,                   NULL,                         NULL,         NULL,         'unassigned', ''),
('S0037', 6,  'Astro Malaysia',         'Media / Broadcasting',       '2026-06-01', '2026-10-31', 'completed',  ''),
('S0038', 12, 'RHB Bank',               'Finance / Banking',          '2026-06-01', '2026-10-31', 'pending',    ''),
('S0039', 9,  'Panasonic Malaysia',     'Engineering / Electronics',  '2026-06-01', '2026-10-31', 'pending',    ''),
('S0040', 2,  'Huawei Malaysia',        'Technology / Telecom',       '2026-06-01', '2026-10-31', 'pending',    ''),
('S0041', NULL, NULL,                   NULL,                         NULL,         NULL,         'unassigned', ''),
('S0042', 5,  'Leo Burnett Malaysia',   'Design / Advertising',       '2026-06-01', '2026-10-31', 'pending',    ''),
('S0043', 13, 'Public Bank',            'Finance / Banking',          '2026-06-01', '2026-10-31', 'pending',    ''),
('S0044', 10, 'Gamuda Berhad',          'Engineering / Construction', '2026-06-01', '2026-10-31', 'pending',    ''),
('S0045', 4,  'HP Malaysia',            'Technology / IT',            '2026-06-01', '2026-10-31', 'completed',  '');