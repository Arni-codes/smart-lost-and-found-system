-- =====================================================================
-- Smart College Lost and Found System (SCLFS)
-- Master Database Schema
-- Compatible with MySQL 8.0+ and MariaDB 10.4+ on standard XAMPP
-- =====================================================================

CREATE DATABASE IF NOT EXISTS smart_lost_and_found
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE smart_lost_and_found;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS claims;
DROP TABLE IF EXISTS smart_matches;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. USERS TABLE
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('student', 'staff', 'admin') NOT NULL DEFAULT 'student',
    student_id VARCHAR(30) NULL UNIQUE COMMENT 'College Roll Number / Staff ID',
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL COMMENT 'Bcrypt / Argon2 hash - Plaintext strictly prohibited',
    phone VARCHAR(20) NOT NULL,
    department VARCHAR(100) NULL,
    status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_role_status (role, status),
    INDEX idx_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. CATEGORIES TABLE
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(50) NOT NULL DEFAULT 'fa-box',
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. CAMPUS LOCATIONS TABLE
-- ---------------------------------------------------------------------
CREATE TABLE locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    campus_zone VARCHAR(60) NOT NULL COMMENT 'e.g. North Campus, Engineering Block',
    building VARCHAR(80) NOT NULL COMMENT 'e.g. Central Library, Science Complex',
    specific_area VARCHAR(100) NULL COMMENT 'e.g. 2nd Floor Reading Room, Lab 102',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_building (building)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. ITEMS TABLE (Core Lost & Found Reports with Custody Tracking)
-- ---------------------------------------------------------------------
CREATE TABLE items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    item_type ENUM('lost', 'found') NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    location_id INT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    identifying_features TEXT NULL COMMENT 'Private verification features - STRICTLY HIDDEN FROM PUBLIC',
    incident_date DATE NOT NULL COMMENT 'Date lost or date found',
    incident_time TIME NULL,
    image_path VARCHAR(255) NULL,
    custody_status ENUM('with_finder', 'submitted_to_security', 'returned_to_owner', 'not_applicable') NOT NULL DEFAULT 'not_applicable',
    status ENUM('open', 'pending_claim', 'claimed', 'closed', 'rejected') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_items_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_items_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE RESTRICT,
    INDEX idx_items_type_status (item_type, status),
    INDEX idx_items_custody (custody_status),
    INDEX idx_items_date (incident_date),
    INDEX idx_items_cat_loc (category_id, location_id),
    FULLTEXT INDEX ft_item_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. SMART MATCHES TABLE (On-Demand Cached Heuristic Similarity Scores)
-- ---------------------------------------------------------------------
CREATE TABLE smart_matches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lost_item_id INT UNSIGNED NOT NULL,
    found_item_id INT UNSIGNED NOT NULL,
    match_score DECIMAL(5,2) NOT NULL COMMENT 'Heuristic similarity score 0.00 to 100.00 (not a probability)',
    score_breakdown JSON NULL COMMENT 'Sub-scores: category, location, date, keyword',
    status ENUM('suggested', 'viewed', 'confirmed', 'dismissed') NOT NULL DEFAULT 'suggested',
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_matches_lost FOREIGN KEY (lost_item_id) REFERENCES items (id) ON DELETE CASCADE,
    CONSTRAINT fk_matches_found FOREIGN KEY (found_item_id) REFERENCES items (id) ON DELETE CASCADE,
    UNIQUE KEY uk_match_pair (lost_item_id, found_item_id),
    INDEX idx_matches_score (match_score),
    INDEX idx_matches_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. CLAIMS TABLE (Ownership Claims Requiring Admin Review & Handover Verification)
-- ---------------------------------------------------------------------
CREATE TABLE claims (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL COMMENT 'The found item being claimed',
    claimant_id INT UNSIGNED NOT NULL,
    lost_item_id INT UNSIGNED NULL COMMENT 'Optional linked lost item report (auto-closed upon handover)',
    proof_description TEXT NOT NULL COMMENT 'Claimant detailed description of distinguishing features',
    proof_image_path VARCHAR(255) NULL COMMENT 'Protected invoice, receipt, or matching photo',
    status ENUM('pending', 'under_review', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL COMMENT 'Notes entered by reviewing administrator',
    reviewed_by_admin_id INT UNSIGNED NULL COMMENT 'Admin who approved or rejected the claim',
    reviewed_at DATETIME NULL,
    handover_date DATETIME NULL COMMENT 'Timestamp when physical item was handed over (prevents duplicate handover)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_claims_item FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE,
    CONSTRAINT fk_claims_claimant FOREIGN KEY (claimant_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_claims_lost_item FOREIGN KEY (lost_item_id) REFERENCES items (id) ON DELETE SET NULL,
    CONSTRAINT fk_claims_admin FOREIGN KEY (reviewed_by_admin_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_claims_status (status),
    INDEX idx_claims_item (item_id),
    INDEX idx_claims_handover (handover_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. NOTIFICATIONS TABLE (Retained Indefinitely for MVP with Pagination)
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('match_found', 'claim_received', 'claim_status', 'custody_update', 'admin_alert', 'system') NOT NULL,
    reference_id INT UNSIGNED NULL,
    reference_url VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_user_created (user_id, created_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. AUDIT LOGS TABLE
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL COMMENT 'e.g. CLAIM_APPROVED, HANDOVER_COMPLETED, CUSTODY_UPDATED',
    target_type VARCHAR(30) NOT NULL COMMENT 'item, claim, user',
    target_id INT UNSIGNED NOT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    INDEX idx_audit_action (action),
    INDEX idx_audit_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
