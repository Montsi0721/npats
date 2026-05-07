-- ============================================================
-- NATIONAL PASSPORT APPLICATION TRACKING SYSTEM (NPATS)
-- Database Schema & Seed Data
-- ============================================================
-- All demo accounts use password: password
-- Hashes generated with bcrypt (cost 10), verified with Python bcrypt.
-- PHP password_verify() accepts $2b$ and $2y$ identically.
-- ============================================================

CREATE DATABASE IF NOT EXISTS npats CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE npats;

CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    full_name   VARCHAR(150) NOT NULL,
    username    VARCHAR(80)  NOT NULL UNIQUE,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','officer','applicant') NOT NULL DEFAULT 'applicant',
    phone       VARCHAR(20)  DEFAULT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS passport_applications (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    application_number VARCHAR(20)  NOT NULL UNIQUE,
    applicant_user_id  INT          DEFAULT NULL,
    officer_id         INT          NOT NULL,
    full_name          VARCHAR(150) NOT NULL,
    national_id        VARCHAR(30)  NOT NULL,
    date_of_birth      DATE         NOT NULL,
    gender             ENUM('Male','Female') NOT NULL,
    address            TEXT         NOT NULL,
    phone              VARCHAR(20)  NOT NULL,
    email              VARCHAR(150) NOT NULL,
    passport_type      ENUM('Normal','Express') NOT NULL DEFAULT 'Normal',
    application_date   DATE         NOT NULL,
    photo_path         VARCHAR(255) DEFAULT NULL,
    current_stage      ENUM(
                           'Application Submitted',
                           'Document Verification',
                           'Biometric Capture',
                           'Background Check',
                           'Passport Printing',
                           'Ready for Collection',
                           'Passport Released'
                       ) NOT NULL DEFAULT 'Application Submitted',
    status             ENUM('Pending','In-Progress','Completed','Rejected') NOT NULL DEFAULT 'Pending',
    created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (officer_id)        REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (applicant_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS processing_stages (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    stage_name     ENUM(
                       'Application Submitted',
                       'Document Verification',
                       'Biometric Capture',
                       'Background Check',
                       'Passport Printing',
                       'Ready for Collection',
                       'Passport Released'
                   ) NOT NULL,
    status         ENUM('Pending','In-Progress','Completed','Rejected') NOT NULL DEFAULT 'Pending',
    officer_id     INT  DEFAULT NULL,
    comments       TEXT DEFAULT NULL,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES passport_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (officer_id)     REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_app_stage (application_id, stage_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS passport_releases (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    application_id   INT          NOT NULL UNIQUE,
    collection_date  DATE         NOT NULL,
    applicant_name   VARCHAR(150) NOT NULL,
    officer_id       INT          NOT NULL,
    notes            TEXT         DEFAULT NULL,
    created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (application_id) REFERENCES passport_applications(id) ON DELETE CASCADE,
    FOREIGN KEY (officer_id)     REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          DEFAULT NULL,
    action     VARCHAR(255) NOT NULL,
    details    TEXT         DEFAULT NULL,
    ip_address VARCHAR(45)  DEFAULT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT          DEFAULT NULL,
    application_id INT          DEFAULT NULL,
    message        TEXT         NOT NULL,
    is_read        TINYINT(1)   NOT NULL DEFAULT 0,
    created_at     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)        REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES passport_applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- SEED DATA
-- All three accounts use password: password

-- Admin account
INSERT INTO users (full_name, username, email, password, role, phone) VALUES
(
    'System Administrator',
    'admin',
    'admin@npats.gov.ls',
    '$2b$10$IEYpU6TIwm41qHE/bwufROAL2xfxsN33hAtuMKjNPLEvf3f2BZs7O',
    'admin',
    '+26622100001'
);

-- Officer account
INSERT INTO users (full_name, username, email, password, role, phone) VALUES
(
    'Mora Mokoena',
    'officer1',
    'officer1@npats.gov.ls',
    '$2b$10$TZ9bb/eyNyyYr8UIDc5N0uNObt9lcyDg1diY9aKF8jHdRByBqL3He',
    'officer',
    '+26622100002'
);

-- Applicant account
INSERT INTO users (full_name, username, email, password, role, phone) VALUES
(
    'Will Smith',
    'applicant1',
    'will@example.com',
    '$2b$10$8GjAHJ.qIVxXpT.Ww/oOhOp058PLVOyIvwBASpfe6ToGNPQWYqP0K',
    'applicant',
    '+26657100003'
);

-- Insert a special "Unassigned" user
INSERT INTO users (full_name, username, email, password, role, phone) VALUES 
(
    'Unassigned Officer', 
    'unassigned', 
    'unassigned@system.local', 
    'disabled', 
    'officer',
    '0000000000'
);