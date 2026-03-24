-- ============================================================
-- COMP1044 Internship Result Management System
-- Database Schema + Sample Data
-- ============================================================

CREATE DATABASE IF NOT EXISTS internship_db;
USE internship_db;

-- ------------------------------------------------------------
-- Table: users (Admin + Assessor login accounts)
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id     INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,      -- store hashed passwords
    role        ENUM('admin', 'assessor') NOT NULL,
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Table: students
-- ------------------------------------------------------------
CREATE TABLE students (
    student_id  VARCHAR(20) PRIMARY KEY,    -- e.g. "20304050"
    full_name   VARCHAR(100) NOT NULL,
    programme   VARCHAR(100) NOT NULL,
    email       VARCHAR(100),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Table: internships (links student to assessor + company)
-- ------------------------------------------------------------
CREATE TABLE internships (
    internship_id   INT AUTO_INCREMENT PRIMARY KEY,
    student_id      VARCHAR(20) NOT NULL,
    assessor_id     INT NOT NULL,
    company_name    VARCHAR(150) NOT NULL,
    start_date      DATE,
    end_date        DATE,
    status          ENUM('ongoing', 'completed') DEFAULT 'ongoing',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (assessor_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Table: assessments (Student C owns this table)
-- ------------------------------------------------------------
CREATE TABLE assessments (
    assessment_id   INT AUTO_INCREMENT PRIMARY KEY,
    student_id      VARCHAR(20) NOT NULL,
    assessor_id     INT NOT NULL,
    -- Raw scores (0-10 each)
    task_score      DECIMAL(4,2) NOT NULL CHECK (task_score BETWEEN 0 AND 10),
    safety_score    DECIMAL(4,2) NOT NULL CHECK (safety_score BETWEEN 0 AND 10),
    knowledge_score DECIMAL(4,2) NOT NULL CHECK (knowledge_score BETWEEN 0 AND 10),
    report_score    DECIMAL(4,2) NOT NULL CHECK (report_score BETWEEN 0 AND 10),
    language_score  DECIMAL(4,2) NOT NULL CHECK (language_score BETWEEN 0 AND 10),
    lifelong_score  DECIMAL(4,2) NOT NULL CHECK (lifelong_score BETWEEN 0 AND 10),
    project_score   DECIMAL(4,2) NOT NULL CHECK (project_score BETWEEN 0 AND 10),
    time_score      DECIMAL(4,2) NOT NULL CHECK (time_score BETWEEN 0 AND 10),
    -- Auto-calculated total (stored for performance)
    total_score     DECIMAL(5,2),
    comment         TEXT,
    submitted_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (assessor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_assessment (student_id, assessor_id)  -- one assessment per student per assessor
);

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Users (password is MD5 of "password123" for demo)
INSERT INTO users (username, password, role, full_name, email) VALUES
('admin01',    MD5('password123'), 'admin',    'Dr. Ahmad Razali',    'ahmad@uni.edu.my'),
('assessor01', MD5('password123'), 'assessor', 'Prof. Siti Nuraini',  'siti@uni.edu.my'),
('assessor02', MD5('password123'), 'assessor', 'Dr. Lee Wei Ming',    'leewm@uni.edu.my');

-- Students
INSERT INTO students (student_id, full_name, programme, email) VALUES
('20301001', 'Muhammad Haziq bin Rosli',     'Bachelor of Computer Science',     'haziq@student.edu.my'),
('20301002', 'Nurul Ain binti Zulkifli',     'Bachelor of Computer Science',     'ain@student.edu.my'),
('20301003', 'Rajesh Kumar Pillai',          'Bachelor of Software Engineering', 'rajesh@student.edu.my'),
('20301004', 'Tan Mei Ling',                 'Bachelor of Information Technology','tanml@student.edu.my'),
('20301005', 'Siti Hajar binti Mohamad',     'Bachelor of Computer Science',     'hajar@student.edu.my');

-- Internships
INSERT INTO internships (student_id, assessor_id, company_name, start_date, end_date, status) VALUES
('20301001', 2, 'Petronas Digital Sdn Bhd',    '2025-07-01', '2025-12-31', 'completed'),
('20301002', 2, 'Maybank IT Department',        '2025-07-01', '2025-12-31', 'completed'),
('20301003', 3, 'TM Research & Development',   '2025-07-01', '2025-12-31', 'completed'),
('20301004', 3, 'CIMB Technology Solutions',   '2025-07-01', '2025-12-31', 'ongoing'),
('20301005', 2, 'Grab Technology Malaysia',    '2025-07-01', '2025-12-31', 'ongoing');

-- Assessments (with calculated total_score)
-- Formula: task*0.10 + safety*0.10 + knowledge*0.10 + report*0.15 + language*0.10 + lifelong*0.15 + project*0.15 + time*0.15
INSERT INTO assessments (student_id, assessor_id, task_score, safety_score, knowledge_score, report_score, language_score, lifelong_score, project_score, time_score, total_score, comment) VALUES
('20301001', 2, 8, 9, 8, 7, 8, 9, 8, 9,
    ROUND(8*0.10 + 9*0.10 + 8*0.10 + 7*0.15 + 8*0.10 + 9*0.15 + 8*0.15 + 9*0.15, 2),
    'Haziq performed exceptionally well. Strong project management and time discipline.'),
('20301002', 2, 7, 8, 7, 8, 7, 8, 7, 8,
    ROUND(7*0.10 + 8*0.10 + 7*0.10 + 8*0.15 + 7*0.10 + 8*0.15 + 7*0.15 + 8*0.15, 2),
    'Ain showed good written communication skills and consistent performance throughout.'),
('20301003', 3, 9, 8, 9, 8, 7, 9, 9, 8,
    ROUND(9*0.10 + 8*0.10 + 9*0.10 + 8*0.15 + 7*0.10 + 9*0.15 + 9*0.15 + 8*0.15, 2),
    'Rajesh demonstrated excellent technical knowledge and project management skills.');