-- ============================================================
--  AttendQR — Database Schema
--  Run this once to set up the database
-- ============================================================

CREATE DATABASE IF NOT EXISTS attendqr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendqr;

-- Admins
CREATE TABLE IF NOT EXISTS admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)        NOT NULL,
    email       VARCHAR(150)        NOT NULL UNIQUE,
    password    VARCHAR(255)        NOT NULL,
    role        ENUM('admin','staff') DEFAULT 'admin',
    created_at  DATETIME            DEFAULT CURRENT_TIMESTAMP,
    last_login  DATETIME            NULL
);

-- Events
CREATE TABLE IF NOT EXISTS events (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(200)        NOT NULL,
    description  TEXT,
    venue        VARCHAR(255),
    event_date   DATE                NOT NULL,
    event_time   TIME,
    status       ENUM('active','inactive','upcoming','completed') DEFAULT 'upcoming',
    max_capacity INT                 DEFAULT 0,
    created_by   INT,
    created_at   DATETIME            DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME            DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- Google Forms linked to events
CREATE TABLE IF NOT EXISTS event_forms (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    event_id    INT                 NOT NULL,
    form_title  VARCHAR(200)        NOT NULL,
    form_url    TEXT                NOT NULL,
    form_status ENUM('active','inactive') DEFAULT 'active',
    responses   INT                 DEFAULT 0,
    created_by  INT,
    created_at  DATETIME            DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id)   REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admins(id) ON DELETE SET NULL
);

-- Attendees / Registrants
CREATE TABLE IF NOT EXISTS attendees (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    event_id        INT                 NOT NULL,
    respondent_id   VARCHAR(50)         NOT NULL UNIQUE,
    full_name       VARCHAR(200)        NOT NULL,
    email           VARCHAR(150)        NOT NULL,
    phone           VARCHAR(30),
    qr_code_id      VARCHAR(100)        NOT NULL UNIQUE,
    qr_code_path    VARCHAR(500),
    registration_at DATETIME            DEFAULT CURRENT_TIMESTAMP,
    checkin_at      DATETIME            NULL,
    checkin_status  ENUM('pending','checked_in','no_show') DEFAULT 'pending',
    email_sent      TINYINT(1)          DEFAULT 0,
    email_sent_at   DATETIME            NULL,
    email_retries   INT                 DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- Scan Logs (Audit Trail)
CREATE TABLE IF NOT EXISTS scan_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    attendee_id INT                 NULL,
    qr_code_id  VARCHAR(100)        NOT NULL,
    event_id    INT                 NULL,
    scanned_by  INT                 NULL,
    scan_result ENUM('checked_in','already_checked_in','not_registered','invalid','error','wrong_event','manual_lookup_checked_in','manual_lookup_duplicate','manual_lookup_wrong_event','manual_lookup_not_found') NOT NULL,
    scanned_at  DATETIME            DEFAULT CURRENT_TIMESTAMP,
    ip_address  VARCHAR(45),
    user_agent  VARCHAR(500),
    FOREIGN KEY (attendee_id) REFERENCES attendees(id) ON DELETE SET NULL,
    FOREIGN KEY (event_id)    REFERENCES events(id)    ON DELETE SET NULL,
    FOREIGN KEY (scanned_by)  REFERENCES admins(id)    ON DELETE SET NULL
);

-- System Logs (general audit)
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    action      VARCHAR(100)        NOT NULL,
    actor_id    INT,
    actor_name  VARCHAR(150),
    target      VARCHAR(300),
    result      VARCHAR(100),
    event_name  VARCHAR(200),
    ip_address  VARCHAR(45),
    created_at  DATETIME            DEFAULT CURRENT_TIMESTAMP
);

-- Email Sending Logs
CREATE TABLE IF NOT EXISTS email_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    recipient     VARCHAR(255)        NOT NULL,
    subject       VARCHAR(255)        NOT NULL,
    body_html     TEXT,
    status        ENUM('sent','failed') NOT NULL,
    error_message TEXT,
    sent_at       DATETIME            DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
--  Seed Data
-- ============================================================

-- Default admin (password: Admin@1234)
INSERT IGNORE INTO admins (name, email, password, role) VALUES
('Administrator', 'admin@attendqr.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Staff User',    'staff@attendqr.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff');

-- Events
INSERT IGNORE INTO events (id, name, description, venue, event_date, event_time, status, max_capacity, created_by) VALUES
(1, 'Tech Summit 2025',   'Annual technology conference.',         'SMX Convention Center', '2025-07-15', '08:00:00', 'active',   200, 1),
(2, 'DevFest Manila',     'Google Developer Festival.',           'Marriott Hotel',         '2025-07-22', '09:00:00', 'active',   150, 1),
(3, 'AI Workshop',        'Hands-on artificial intelligence workshop.', 'UP Diliman',       '2025-08-05', '10:00:00', 'upcoming', 80,  1),
(4, 'Leadership Forum',   'Executive leadership development.',    'BGC, Taguig',            '2025-08-20', '08:30:00', 'upcoming', 100, 1);

-- Forms
INSERT IGNORE INTO event_forms (event_id, form_title, form_url, form_status, responses) VALUES
(1, 'Tech Summit 2025 Registration', 'https://docs.google.com/forms/d/e/example1/viewform', 'active', 89),
(2, 'DevFest Manila Registration',   'https://docs.google.com/forms/d/e/example2/viewform', 'active', 74),
(3, 'AI Workshop Registration',      'https://docs.google.com/forms/d/e/example3/viewform', 'active', 45),
(4, 'Leadership Forum Sign-up',      'https://docs.google.com/forms/d/e/example4/viewform', 'inactive', 39);

-- Attendees
INSERT IGNORE INTO attendees (id, event_id, respondent_id, full_name, email, phone, qr_code_id, registration_at, checkin_at, checkin_status, email_sent) VALUES
(1,  1, 'RID-00001', 'Maria Santos',    'maria.santos@gmail.com',    '09171234567', 'QR-E001-001', '2025-07-01 10:23:00', '2025-07-15 08:32:00', 'checked_in', 1),
(2,  1, 'RID-00002', 'Jose Reyes',      'jose.reyes@gmail.com',      '09181234567', 'QR-E001-002', '2025-07-01 11:05:00', '2025-07-15 08:33:00', 'checked_in', 1),
(3,  2, 'RID-00003', 'Ana Cruz',        'ana.cruz@gmail.com',        '09191234567', 'QR-E002-001', '2025-07-02 09:14:00', NULL,                  'pending',    1),
(4,  1, 'RID-00004', 'Carlos Lim',      'carlos.lim@gmail.com',      '09201234567', 'QR-E001-003', '2025-07-02 14:32:00', '2025-07-15 09:01:00', 'checked_in', 1),
(5,  2, 'RID-00005', 'Diana Torres',    'diana.torres@gmail.com',    '09211234567', 'QR-E002-002', '2025-07-03 08:50:00', '2025-07-22 08:55:00', 'checked_in', 1),
(6,  3, 'RID-00006', 'Edgar Bautista',  'edgar.bautista@gmail.com',  '09221234567', 'QR-E003-001', '2025-07-10 15:20:00', NULL,                  'pending',    1),
(7,  1, 'RID-00007', 'Fe Gonzales',     'fe.gonzales@gmail.com',     '09231234567', 'QR-E001-004', '2025-07-04 11:40:00', NULL,                  'pending',    1),
(8,  4, 'RID-00008', 'Gino Ramos',      'gino.ramos@gmail.com',      '09241234567', 'QR-E004-001', '2025-07-05 13:00:00', NULL,                  'pending',    1);

-- Scan Logs
INSERT IGNORE INTO scan_logs (attendee_id, qr_code_id, event_id, scanned_by, scan_result, scanned_at, ip_address) VALUES
(1, 'QR-E001-001', 1, 2, 'checked_in',         '2025-07-15 08:32:11', '192.168.1.10'),
(2, 'QR-E001-002', 1, 2, 'checked_in',         '2025-07-15 08:33:05', '192.168.1.10'),
(1, 'QR-E001-001', 1, 2, 'already_checked_in', '2025-07-15 08:35:20', '192.168.1.11'),
(4, 'QR-E001-003', 1, 2, 'checked_in',         '2025-07-15 09:01:44', '192.168.1.10'),
(NULL, 'QR-INVALID-999', 1, 2, 'invalid',       '2025-07-15 09:15:30', '192.168.1.12');

-- Audit Logs
INSERT IGNORE INTO audit_logs (action, actor_id, actor_name, target, result, event_name, ip_address, created_at) VALUES
('QR Scan',      2, 'Staff User',    'Maria Santos',       'checked_in',         'Tech Summit 2025', '192.168.1.10', '2025-07-15 08:32:11'),
('QR Scan',      2, 'Staff User',    'Jose Reyes',         'checked_in',         'Tech Summit 2025', '192.168.1.10', '2025-07-15 08:33:05'),
('QR Scan',      2, 'Staff User',    'Maria Santos',       'already_checked_in', 'Tech Summit 2025', '192.168.1.11', '2025-07-15 08:35:20'),
('Email Sent',   1, 'System',        'Ana Cruz',           'delivered',          'DevFest Manila',   '127.0.0.1',    '2025-07-15 08:40:01'),
('QR Generated', 1, 'System',        'Edgar Bautista',     'success',            'AI Workshop',      '127.0.0.1',    '2025-07-15 09:01:44'),
('Form Embedded',1, 'Administrator', 'AI Workshop Form',   'active',             'AI Workshop',      '192.168.1.1',  '2025-07-15 09:22:10');
