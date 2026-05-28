-- Maze Engine Test Database Schema
-- Only includes tables used by the maze engine.

CREATE DATABASE IF NOT EXISTS maze_test_db;
USE maze_test_db;

-- Users
CREATE TABLE IF NOT EXISTS Users (
    id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    profile_pic_path VARCHAR(255) DEFAULT NULL,
    profile_pic_mime_type VARCHAR(50) DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

-- Messages
CREATE TABLE IF NOT EXISTS Messages (
    id INT(11) NOT NULL AUTO_INCREMENT,
    sender_id INT(11) NOT NULL,
    receiver_id INT(11) NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (sender_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES Users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- MazeChallenges (CRITICAL: ON DELETE SET NULL for msg FKs)
CREATE TABLE IF NOT EXISTS MazeChallenges (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    public_key TEXT DEFAULT NULL,
    encrypted_username_msg_id INT(11) DEFAULT NULL,
    public_key_msg_id INT(11) DEFAULT NULL,
    status ENUM('pending', 'processed', 'invalid', 'replied', 'pending_message', 'pending_key') DEFAULT 'pending',
    decrypted_username VARCHAR(255) DEFAULT NULL,
    error_message VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (encrypted_username_msg_id) REFERENCES Messages(id) ON DELETE SET NULL,
    FOREIGN KEY (public_key_msg_id) REFERENCES Messages(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- MazeProgress
CREATE TABLE IF NOT EXISTS MazeProgress (
    user_id INT(11) NOT NULL,
    last_processed_msg_id INT(11) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
