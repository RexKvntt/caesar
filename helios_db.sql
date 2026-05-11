-- ============================================================
--  Helios University Academic Hub — MySQL Database Schema
--  Version 2 — Updated:
--    - username column: UNIQUE + COMMENT marking it immutable
--    - username length set to 10 to fit YY-XXXX format (e.g. 26-4821)
--    - DB-level trigger blocks username changes after activation
--    - activated_at column added to users
--    - Admin seed uses new YY-XXXX format (26-0001)
--    - All foreign keys referencing username updated to VARCHAR(10)
-- ============================================================

CREATE DATABASE IF NOT EXISTS helios_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE helios_db;

-- ============================================================
-- 1. AUTHORIZED_PEOPLE
--    Admin inserts a row here before a person can register.
--    The register form checks this table for a match.
-- ============================================================
CREATE TABLE authorized_people (
    id              VARCHAR(36)   NOT NULL PRIMARY KEY,
    firstname       VARCHAR(100)  NOT NULL,
    lastname        VARCHAR(100)  NOT NULL,
    email           VARCHAR(255)  NOT NULL UNIQUE,
    phonenumber     VARCHAR(20)   NOT NULL            COMMENT 'Normalized digits e.g. 9171234567',
    role            ENUM('student','faculty','admin') NOT NULL,
    status          ENUM('pending','activated')       NOT NULL DEFAULT 'pending',
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 2. USERS
--    Core accounts table.
--
--    username = permanent unique ID assigned on admin approval.
--    Format  : YY-XXXX  (e.g. 26-4821)
--    YY      : last 2 digits of the approval year
--    XXXX    : random 4-digit number (1000-9999)
--
--    Immutability rules:
--      - Set ONCE inside process_approve.php
--      - UNIQUE constraint prevents duplicates
--      - Trigger below blocks any UPDATE once set to YY-XXXX
--      - password column CAN be changed (process_change_password.php)
-- ============================================================
CREATE TABLE users (
    id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id           VARCHAR(20)   NOT NULL UNIQUE
                             COMMENT 'Temp registration ID (REQ-XXXXXXXX), kept for audit trail',
    authorized_person_id VARCHAR(36)   NULL,
    firstname            VARCHAR(100)  NOT NULL,
    lastname             VARCHAR(100)  NOT NULL,
    fullname             VARCHAR(201)  NOT NULL,
    phonenumber          VARCHAR(255)  NOT NULL   COMMENT 'Encrypted at application layer',
    username             VARCHAR(10)   NOT NULL UNIQUE
                             COMMENT 'Permanent ID — YY-XXXX format — set once on approval, NEVER updated',
    email                VARCHAR(255)  NOT NULL   COMMENT 'Encrypted at application layer',
    password             VARCHAR(255)  NULL       COMMENT 'bcrypt hash — NULL until approved',
    role                 ENUM('student','faculty','admin') NOT NULL,
    status               ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
    activation_request   TINYINT(1)   NOT NULL DEFAULT 1,
    registered_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activated_at         DATETIME     NULL        COMMENT 'Set when admin approves the account',

    FOREIGN KEY (authorized_person_id)
        REFERENCES authorized_people(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
);

-- ============================================================
-- TRIGGER: Enforce username immutability at the database level.
--
-- The only allowed username change is the one-time transition
-- from a REQ-* pending value to the final YY-XXXX format,
-- which happens exactly once inside process_approve.php.
--
-- After that, any attempt to UPDATE username will raise an error,
-- even if someone bypasses the PHP layer directly in SQL.
-- ============================================================
DELIMITER $$

CREATE TRIGGER trg_block_username_change
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    IF OLD.username REGEXP '^[0-9]{2}-[0-9]{4}$'
       AND NEW.username <> OLD.username
    THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Username (ID) is permanent and cannot be changed after activation.';
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- 3. ADMIN_ACCOUNTS
--    Any user with role='admin' gets a row here.
--    Supports multiple admins.
-- ============================================================
CREATE TABLE admin_accounts (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL UNIQUE,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- 4. CLASSES
-- ============================================================
CREATE TABLE classes (
    id          VARCHAR(20)   NOT NULL PRIMARY KEY,
    name        VARCHAR(200)  NOT NULL,
    subject     VARCHAR(200)  NOT NULL,
    description TEXT          NULL,
    code        CHAR(6)       NOT NULL UNIQUE     COMMENT '6-char alphanumeric join code',
    owner       VARCHAR(10)   NOT NULL            COMMENT 'Faculty YY-XXXX username',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (owner) REFERENCES users(username)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- 5. CLASS_MEMBERS
-- ============================================================
CREATE TABLE class_members (
    class_id    VARCHAR(20)  NOT NULL,
    username    VARCHAR(10)  NOT NULL,
    joined_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (class_id, username),
    FOREIGN KEY (class_id) REFERENCES classes(id)      ON DELETE CASCADE,
    FOREIGN KEY (username) REFERENCES users(username)  ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- 6. POSTS
-- ============================================================
CREATE TABLE posts (
    id          VARCHAR(20)    NOT NULL PRIMARY KEY,
    class_id    VARCHAR(20)    NOT NULL,
    type        ENUM('announcement','assignment','material') NOT NULL DEFAULT 'announcement',
    title       VARCHAR(255)   NULL,
    body        TEXT           NULL,
    link_url    VARCHAR(2048)  NULL,
    posted_by   VARCHAR(10)    NOT NULL,
    posted_at   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deadline    DATETIME       NULL,
    points      INT UNSIGNED   NULL,

    FOREIGN KEY (class_id)  REFERENCES classes(id)       ON DELETE CASCADE,
    FOREIGN KEY (posted_by) REFERENCES users(username)   ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- 7. POST_FILES
-- ============================================================
CREATE TABLE post_files (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id     VARCHAR(20)   NOT NULL UNIQUE,
    orig_name   VARCHAR(255)  NOT NULL,
    stored_path VARCHAR(512)  NOT NULL,
    ext         VARCHAR(10)   NOT NULL,
    size        INT UNSIGNED  NOT NULL,

    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

-- ============================================================
-- 8. COMMENTS
-- ============================================================
CREATE TABLE comments (
    id          VARCHAR(20)  NOT NULL PRIMARY KEY,
    post_id     VARCHAR(20)  NOT NULL,
    author      VARCHAR(10)  NOT NULL,
    role        ENUM('student','faculty','admin') NOT NULL,
    body        TEXT         NOT NULL,
    posted_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (post_id) REFERENCES posts(id)         ON DELETE CASCADE,
    FOREIGN KEY (author)  REFERENCES users(username)   ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- 9. SUBMISSIONS
-- ============================================================
CREATE TABLE submissions (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    post_id          VARCHAR(20)   NOT NULL,
    student_username VARCHAR(10)   NOT NULL,
    note             TEXT          NULL,
    submitted_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    score            DECIMAL(6,2)  NULL,
    score_note       TEXT          NULL,
    scored_at        DATETIME      NULL,
    scored_by        VARCHAR(10)   NULL,

    UNIQUE KEY uq_submission (post_id, student_username),
    FOREIGN KEY (post_id)           REFERENCES posts(id)         ON DELETE CASCADE,
    FOREIGN KEY (student_username)  REFERENCES users(username)   ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (scored_by)         REFERENCES users(username)   ON DELETE SET NULL ON UPDATE CASCADE
);

-- ============================================================
-- 10. SUBMISSION_FILES
-- ============================================================
CREATE TABLE submission_files (
    id            INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED  NOT NULL UNIQUE,
    orig_name     VARCHAR(255)  NOT NULL,
    stored_path   VARCHAR(512)  NOT NULL,
    ext           VARCHAR(10)   NOT NULL,
    size          INT UNSIGNED  NOT NULL,

    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
);

-- ============================================================
-- 11. NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id       VARCHAR(20)  NOT NULL PRIMARY KEY,
    type     VARCHAR(50)  NOT NULL,
    message  TEXT         NOT NULL,
    time     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_read  TINYINT(1)   NOT NULL DEFAULT 0
);

-- ============================================================
-- 12. CALENDAR_EVENTS
-- ============================================================
CREATE TABLE calendar_events (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255)  NOT NULL,
    description TEXT          NULL,
    event_date  DATE          NOT NULL,
    start_time  TIME          NULL,
    end_time    TIME          NULL,
    created_by  VARCHAR(10)   NOT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (created_by) REFERENCES users(username)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- SEED: Default Admin User
--
-- Username: 26-0001  (reserved admin ID for 2026)
-- Generate your bcrypt hash first:
--   php -r "echo password_hash('your_password_here', PASSWORD_BCRYPT);"
-- Then replace REPLACEME below.
-- ============================================================
INSERT INTO users (
    request_id, firstname, lastname, fullname,
    phonenumber, username, email, password,
    role, status, activation_request,
    registered_at, activated_at
) VALUES (
    'REQ-26-0001',
    'Admin', 'User', 'Admin User',
    '',
    '26-0001',
    '',
    '$2y$12$REPLACEME_WITH_YOUR_BCRYPT_HASH',
    'admin',
    'active',
    0,
    NOW(),
    NOW()
);

INSERT INTO admin_accounts (user_id)
SELECT id FROM users WHERE username = '26-0001' LIMIT 1;

-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX idx_users_role        ON users(role);
CREATE INDEX idx_users_status      ON users(status);
CREATE INDEX idx_users_activated   ON users(activated_at);
CREATE INDEX idx_posts_class       ON posts(class_id);
CREATE INDEX idx_posts_type        ON posts(type);
CREATE INDEX idx_submissions_post  ON submissions(post_id);
CREATE INDEX idx_comments_post     ON comments(post_id);
CREATE INDEX idx_notif_read        ON notifications(is_read);
CREATE INDEX idx_ap_status         ON authorized_people(status);