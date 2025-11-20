-- Notes Application Database Setup
-- Run this script to create the required database and tables

CREATE DATABASE IF NOT EXISTS notes_app;
USE notes_app;

-- Users table with enhanced profile information
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    profile_picture VARCHAR(255),
    bio TEXT,
    timezone VARCHAR(50) DEFAULT 'UTC',
    language VARCHAR(10) DEFAULT 'en',
    is_active BOOLEAN DEFAULT TRUE,
    is_premium BOOLEAN DEFAULT FALSE,
    subscription_type ENUM('free', 'basic', 'premium', 'enterprise') DEFAULT 'free',
    subscription_expires_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    login_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User sessions for better security
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Categories for organizing notes
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#007bff',
    icon VARCHAR(50),
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_category (user_id, name)
);

-- Enhanced notes table with more features
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    summary TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_archived BOOLEAN DEFAULT FALSE,
    is_favorite BOOLEAN DEFAULT FALSE,
    read_count INT DEFAULT 0,
    word_count INT DEFAULT 0,
    character_count INT DEFAULT 0,
    estimated_read_time INT DEFAULT 0,
    last_read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Tags for better note organization
CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#6c757d',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_tag (user_id, name)
);

-- Note-tag relationships
CREATE TABLE IF NOT EXISTS note_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE,
    UNIQUE KEY unique_note_tag (note_id, tag_id)
);

-- Attachments for notes (files, images, etc.)
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_extension VARCHAR(20),
    is_image BOOLEAN DEFAULT FALSE,
    image_width INT,
    image_height INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Note sharing functionality
CREATE TABLE IF NOT EXISTS note_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    shared_by_user_id INT NOT NULL,
    shared_with_user_id INT,
    share_token VARCHAR(255) UNIQUE NOT NULL,
    permission_level ENUM('read', 'write', 'admin') DEFAULT 'read',
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Note comments for collaboration
CREATE TABLE IF NOT EXISTS note_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_comment_id INT,
    content TEXT NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES note_comments(id) ON DELETE CASCADE
);

-- Note history for version control
CREATE TABLE IF NOT EXISTS note_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT,
    version_number INT NOT NULL,
    change_summary VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User activity logging
CREATE TABLE IF NOT EXISTS user_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('login', 'logout', 'create_note', 'edit_note', 'delete_note', 'share_note', 'download_attachment', 'search') NOT NULL,
    activity_details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User preferences and settings
CREATE TABLE IF NOT EXISTS user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    theme ENUM('light', 'dark', 'auto') DEFAULT 'light',
    font_size ENUM('small', 'medium', 'large') DEFAULT 'medium',
    editor_type ENUM('rich', 'markdown', 'plain') DEFAULT 'rich',
    auto_save_interval INT DEFAULT 30,
    email_notifications BOOLEAN DEFAULT TRUE,
    push_notifications BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Subscription plans
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    billing_cycle ENUM('monthly', 'yearly') DEFAULT 'monthly',
    max_notes INT,
    max_storage_mb INT,
    max_attachments_per_note INT,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User subscriptions
CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    status ENUM('active', 'cancelled', 'expired', 'pending') DEFAULT 'pending',
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    auto_renew BOOLEAN DEFAULT TRUE,
    payment_method VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES subscription_plans(id) ON DELETE CASCADE
);

-- Insert subscription plans
INSERT INTO subscription_plans (name, description, price, billing_cycle, max_notes, max_storage_mb, max_attachments_per_note, features) VALUES 
('Free', 'Basic features for casual users', 0.00, 'monthly', 50, 100, 5, '["basic_notes", "search", "categories"]'),
('Basic', 'Enhanced features for regular users', 4.99, 'monthly', 500, 1000, 20, '["basic_notes", "search", "categories", "tags", "attachments", "sharing"]'),
('Premium', 'Advanced features for power users', 9.99, 'monthly', 5000, 10000, 50, '["basic_notes", "search", "categories", "tags", "attachments", "sharing", "collaboration", "version_history", "advanced_search"]'),
('Enterprise', 'Full features for teams and organizations', 19.99, 'monthly', -1, 100000, 100, '["basic_notes", "search", "categories", "tags", "attachments", "sharing", "collaboration", "version_history", "advanced_search", "admin_panel", "analytics", "api_access"]');

-- Insert sample user (password: password123)
INSERT INTO users (username, email, password_hash, first_name, last_name, is_premium, subscription_type) VALUES 
('demo_user', 'demo@example.com', '$2y$10$MOE4vZ8kUMxeKsOEZw/MU.7KHZ0NKIbANtnFAMDQ0tGDYHnq8y8wO', 'Demo', 'User', TRUE, 'premium');

-- Insert default categories
INSERT INTO categories (user_id, name, description, color, icon, is_default) VALUES 
(1, 'General', 'Default category for general notes', '#007bff', 'bi-journal-text', TRUE),
(1, 'Work', 'Work-related notes and tasks', '#28a745', 'bi-briefcase', FALSE),
(1, 'Personal', 'Personal notes and thoughts', '#ffc107', 'bi-person', FALSE),
(1, 'Ideas', 'Creative ideas and brainstorming', '#dc3545', 'bi-lightbulb', FALSE),
(1, 'To-Do', 'Tasks and reminders', '#6f42c1', 'bi-check2-square', FALSE);

-- Insert user preferences
INSERT INTO user_preferences (user_id, theme, font_size, editor_type, auto_save_interval) VALUES 
(1, 'light', 'medium', 'rich', 30);

-- Insert sample notes
INSERT INTO notes (user_id, category_id, title, content, summary, is_pinned, word_count, character_count, estimated_read_time) VALUES 
(1, 1, 'Welcome to Your Notes App', 'Welcome to your enhanced notes application! This is your first note in the commercial version with advanced features.

Features available:
- Categories and tags for organization
- File attachments
- Note sharing and collaboration
- Version history
- Advanced search capabilities
- Premium subscription options

Get started by creating your first note!', 'Welcome message and feature overview', TRUE, 45, 280, 1),
(1, 2, 'Project Meeting Notes', 'Meeting scheduled for tomorrow at 2 PM.

Agenda:
1. Review current progress
2. Discuss new features
3. Timeline planning
4. Resource allocation

Action items:
- Prepare presentation slides
- Update project documentation
- Schedule follow-up meeting', 'Project meeting agenda and action items', FALSE, 35, 220, 1),
(1, 3, 'Personal Goals 2024', 'My personal goals for this year:

Health & Fitness:
- Exercise 3 times per week
- Maintain healthy diet
- Get 8 hours of sleep

Career:
- Learn new programming languages
- Complete online courses
- Network more actively

Personal:
- Read 24 books this year
- Travel to 3 new countries
- Learn to play guitar', 'Personal goals and resolutions for 2024', FALSE, 42, 280, 1);

-- Insert sample tags
INSERT INTO tags (user_id, name, color) VALUES 
(1, 'important', '#dc3545'),
(1, 'work', '#007bff'),
(1, 'personal', '#28a745'),
(1, 'ideas', '#ffc107'),
(1, 'meeting', '#6f42c1');

-- Link tags to notes
INSERT INTO note_tags (note_id, tag_id) VALUES 
(1, 1), -- Welcome note - important
(2, 2), -- Meeting notes - work
(2, 5), -- Meeting notes - meeting
(3, 3); -- Personal goals - personal 