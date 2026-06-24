-- ============================================================
-- Lost and Found Inventory Management System
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS lostfound_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lostfound_db;

-- ============================================================
-- ROLES
-- ============================================================
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO roles (name, description) VALUES
('admin', 'Full system access'),
('staff', 'Can manage items and claims'),
('user', 'Can submit reports and claims');

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL DEFAULT 3,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(200) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30),
    department VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    reset_token VARCHAR(100),
    reset_expires DATETIME,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Default admin account (password: Admin@1234)
INSERT INTO users (role_id, full_name, email, password_hash, is_active) VALUES
(1, 'System Administrator', 'admin@lostfound.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- ============================================================
-- CATEGORIES
-- ============================================================
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, slug) VALUES
('Electronics', 'electronics'),
('Mobile Phones', 'mobile-phones'),
('Wallets', 'wallets'),
('Bags', 'bags'),
('Identification Cards', 'identification-cards'),
('Documents', 'documents'),
('Keys', 'keys'),
('Jewelry', 'jewelry'),
('Clothing', 'clothing'),
('Others', 'others');

-- ============================================================
-- LOCATIONS
-- ============================================================
CREATE TABLE locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    building VARCHAR(100),
    floor VARCHAR(50),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO locations (name) VALUES
('Classroom'),
('Library'),
('Cafeteria'),
('Gymnasium'),
('Parking Area'),
('Administrative Office'),
('Dormitory'),
('Hallway / Corridor'),
('Restroom'),
('Other');

-- ============================================================
-- LOST ITEMS
-- ============================================================
CREATE TABLE lost_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(20) NOT NULL UNIQUE,
    category_id INT UNSIGNED,
    location_id INT UNSIGNED,
    reported_by INT UNSIGNED,
    item_name VARCHAR(200) NOT NULL,
    description TEXT,
    date_lost DATE NOT NULL,
    time_lost TIME,
    owner_name VARCHAR(150) NOT NULL,
    owner_contact VARCHAR(100) NOT NULL,
    owner_email VARCHAR(200),
    photo VARCHAR(300),
    status ENUM('missing','matched','claimed','returned','archived') DEFAULT 'missing',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- FOUND ITEMS
-- ============================================================
CREATE TABLE found_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(20) NOT NULL UNIQUE,
    category_id INT UNSIGNED,
    location_id INT UNSIGNED,
    recorded_by INT UNSIGNED,
    item_name VARCHAR(200) NOT NULL,
    description TEXT,
    date_found DATE NOT NULL,
    time_found TIME,
    found_by_name VARCHAR(150),
    storage_location VARCHAR(200),
    photo VARCHAR(300),
    status ENUM('available','claimed','returned','archived') DEFAULT 'available',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- CLAIMS
-- ============================================================
CREATE TABLE claims (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_code VARCHAR(20) NOT NULL UNIQUE,
    found_item_id INT UNSIGNED,
    lost_item_id INT UNSIGNED,
    claimant_id INT UNSIGNED,
    claimant_name VARCHAR(150) NOT NULL,
    claimant_contact VARCHAR(100) NOT NULL,
    claimant_email VARCHAR(200),
    proof_of_ownership TEXT,
    evidence_file VARCHAR(300),
    notes TEXT,
    status ENUM('pending','under_review','approved','rejected','completed') DEFAULT 'pending',
    reviewed_by INT UNSIGNED,
    reviewed_at DATETIME,
    review_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE SET NULL,
    FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE SET NULL,
    FOREIGN KEY (claimant_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- ITEM MATCHES
-- ============================================================
CREATE TABLE item_matches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lost_item_id INT UNSIGNED NOT NULL,
    found_item_id INT UNSIGNED NOT NULL,
    match_score TINYINT UNSIGNED DEFAULT 0 COMMENT 'Score 0-100',
    matched_by INT UNSIGNED COMMENT 'NULL = auto, user ID = manual',
    status ENUM('suggested','confirmed','rejected') DEFAULT 'suggested',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lost_item_id) REFERENCES lost_items(id) ON DELETE CASCADE,
    FOREIGN KEY (found_item_id) REFERENCES found_items(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_match (lost_item_id, found_item_id)
);

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('new_claim','claim_approved','claim_rejected','item_matched','item_returned','system') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    reference_id INT UNSIGNED COMMENT 'ID of related item/claim',
    reference_type VARCHAR(50) COMMENT 'lost_item|found_item|claim',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- ACTIVITY LOGS
-- ============================================================
CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    reference_id INT UNSIGNED,
    reference_type VARCHAR(50),
    description TEXT,
    ip_address VARCHAR(50),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- INDEXES
-- ============================================================
CREATE INDEX idx_lost_status ON lost_items(status);
CREATE INDEX idx_lost_date ON lost_items(date_lost);
CREATE INDEX idx_lost_category ON lost_items(category_id);
CREATE INDEX idx_found_status ON found_items(status);
CREATE INDEX idx_found_date ON found_items(date_found);
CREATE INDEX idx_found_category ON found_items(category_id);
CREATE INDEX idx_claims_status ON claims(status);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX idx_activity_user ON activity_logs(user_id);
CREATE INDEX idx_activity_module ON activity_logs(module);
