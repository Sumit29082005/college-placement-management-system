-- College Placement Management System - MySQL schema
CREATE DATABASE IF NOT EXISTS placement_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE placement_db;

CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('admin','student') NOT NULL DEFAULT 'student',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL UNIQUE,
    roll_no         VARCHAR(30) NOT NULL UNIQUE,
    department      VARCHAR(80) NOT NULL,
    cgpa            DECIMAL(3,2) NOT NULL DEFAULT 0,
    backlogs        INT NOT NULL DEFAULT 0,
    graduation_year YEAR NOT NULL,
    phone           VARCHAR(20),
    skills          TEXT,
    resume_link     VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS companies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    industry    VARCHAR(80),
    website     VARCHAR(200),
    location    VARCHAR(100),
    description TEXT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS drives (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    company_id          INT NOT NULL,
    job_title           VARCHAR(120) NOT NULL,
    package_lpa         DECIMAL(5,2) NOT NULL DEFAULT 0,
    location            VARCHAR(100),
    description         TEXT,
    min_cgpa            DECIMAL(3,2) NOT NULL DEFAULT 0,
    max_backlogs        INT NOT NULL DEFAULT 0,
    allowed_departments VARCHAR(255) NOT NULL DEFAULT 'ALL',  -- comma separated or ALL
    deadline            DATE NOT NULL,
    drive_date          DATE NOT NULL,
    status              ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS applications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    drive_id   INT NOT NULL,
    student_id INT NOT NULL,
    status     ENUM('applied','shortlisted','interviewed','selected','rejected') NOT NULL DEFAULT 'applied',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_drive_student (drive_id, student_id),
    FOREIGN KEY (drive_id)   REFERENCES drives(id)   ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_app_status ON applications(status);
CREATE INDEX idx_drive_status ON drives(status, deadline);
