-- ============================================================
--  CodeQuest Database Setup
--  Run this in phpMyAdmin or MySQL CLI ONCE
-- ============================================================

CREATE DATABASE IF NOT EXISTS codequest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE codequest;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(100) NOT NULL,
    email        VARCHAR(150) NOT NULL UNIQUE,
    password     VARCHAR(255) NOT NULL,        -- bcrypt hash
    username     VARCHAR(50)  DEFAULT NULL,
    display_name VARCHAR(100) DEFAULT NULL,
    xp           INT          DEFAULT 0,
    streak       INT          DEFAULT 0,
    gems         INT          DEFAULT 0,
    lessons_done INT          DEFAULT 0,
    progress     INT          DEFAULT 0,        -- highest lesson node reached
    selected_lang VARCHAR(30) DEFAULT 'Python',
    last_login   DATETIME     DEFAULT NULL,
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Chapter completions per user
CREATE TABLE IF NOT EXISTS chapter_completions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT  NOT NULL,
    lesson_key  VARCHAR(30) NOT NULL,           -- e.g. 'python1', 'java1'
    chapter_num INT  NOT NULL,
    xp_earned   INT  DEFAULT 0,
    completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_chapter (user_id, lesson_key, chapter_num),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Quiz scores
CREATE TABLE IF NOT EXISTS quiz_scores (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    score      INT DEFAULT 0,
    total      INT DEFAULT 5,
    xp_earned  INT DEFAULT 0,
    taken_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Activity log (for Dashboard)
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    action_type VARCHAR(50)  NOT NULL,          -- 'lesson', 'quiz', 'login', 'streak'
    description VARCHAR(255) DEFAULT '',
    xp_change   INT DEFAULT 0,
    logged_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
