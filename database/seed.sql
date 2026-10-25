-- =====================================================================
-- Smart College Lost and Found System (SCLFS)
-- Seed Data Fixture for Development & Demonstration
-- =====================================================================

USE smart_lost_and_found;

DELETE FROM audit_logs;
DELETE FROM notifications;
DELETE FROM claims;
DELETE FROM smart_matches;
DELETE FROM items;
DELETE FROM locations;
DELETE FROM categories;
DELETE FROM users;

ALTER TABLE audit_logs AUTO_INCREMENT = 1;
ALTER TABLE notifications AUTO_INCREMENT = 1;
ALTER TABLE claims AUTO_INCREMENT = 1;
ALTER TABLE smart_matches AUTO_INCREMENT = 1;
ALTER TABLE items AUTO_INCREMENT = 1;
ALTER TABLE locations AUTO_INCREMENT = 1;
ALTER TABLE categories AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------------
-- 1. SEED CATEGORIES
-- ---------------------------------------------------------------------
INSERT INTO categories (id, name, icon, description) VALUES
(1, 'Electronics', 'laptop', 'Laptops, smartphones, tablets, headphones, chargers, power banks, and accessories'),
(2, 'ID Cards & Keys', 'id-card', 'College ID cards, access badges, driving licenses, room keys, and vehicle keys'),
(3, 'Wallets & Bags', 'wallet', 'Wallets, purses, backpacks, sling bags, gym bags, and card holders'),
(4, 'Books & Stationery', 'book', 'Textbooks, notebooks, calculators, lab manuals, and pencil cases'),
(5, 'Clothing & Accessories', 'tshirt', 'Jackets, hoodies, caps, umbrellas, watches, jewelry, and eyewear'),
(6, 'Other Items', 'box-open', 'Water bottles, sports equipment, musical instruments, and miscellaneous belongings');

-- ---------------------------------------------------------------------
-- 2. SEED CAMPUS LOCATIONS
-- ---------------------------------------------------------------------
INSERT INTO locations (id, campus_zone, building, specific_area) VALUES
(1, 'North Campus', 'Administrative Block', 'Ground Floor Reception & Accounts Desk'),
(2, 'North Campus', 'Engineering Block', '1st Floor Computer Labs & Room 102'),
(3, 'North Campus', 'Science Complex', 'Ground Floor Physics & Chemistry Laboratories'),
(4, 'Central Campus', 'Central Library', '2nd Floor Quiet Study Area & Reading Hall'),
(5, 'Central Campus', 'Student Activity Center', 'Main Lobby & Club Rooms'),
(6, 'South Campus', 'Main Cafeteria', 'Dining Hall & Food Court Seating'),
(7, 'South Campus', 'Sports Complex', 'Indoor Badminton Court & Gym Area'),
(8, 'Residential Zone', 'Hostel Block A', 'Common Room & Visitor Lounge'),
(9, 'Residential Zone', 'Hostel Block B', 'Dining Hall & Front Entrance');

-- ---------------------------------------------------------------------
-- 3. SEED USERS
-- Demo Administrator: admin@college.edu / Admin@123
-- Demo Students: alex@college.edu / Student@123, priya@college.edu / Student@123
-- NOTE: Never deploy demo credentials to production!
-- ---------------------------------------------------------------------
INSERT INTO users (id, role, student_id, full_name, email, password_hash, phone, department, status) VALUES
(1, 'admin', 'ADMIN001', 'System Administrator', 'admin@college.edu', 
 '$2y$10$3kkJOnxuV2e6BfVLO99vU.ORcGY2BcSA5ordu4.AWq9qEfAsa2jGu', '+1234567890', 'Campus Security Desk', 'active'),
(2, 'student', 'STU2026001', 'Alex Rivera', 'alex@college.edu', 
 '$2y$10$bBdZZwHI2RYeKgN14fQTJe.6PAVUMEyGT8cna.nixZXmveExShfsm', '+1234567891', 'Computer Science & Engineering', 'active'),
(3, 'student', 'STU2026002', 'Priya Sharma', 'priya@college.edu', 
 '$2y$10$bBdZZwHI2RYeKgN14fQTJe.6PAVUMEyGT8cna.nixZXmveExShfsm', '+1234567892', 'Electrical & Electronics Engineering', 'active');

-- ---------------------------------------------------------------------
-- 4. SEED SAMPLE ITEMS
-- Demonstrating lost and found items with both custody modes:
-- 'with_finder' and 'submitted_to_security'
-- ---------------------------------------------------------------------
INSERT INTO items (id, user_id, item_type, category_id, location_id, title, description, identifying_features, incident_date, incident_time, custody_status, status) VALUES
(1, 2, 'lost', 1, 4, 'Blue Dell Inspiron 15 Laptop', 
 'Lost my dark blue Dell laptop in a gray neoprene sleeve with charger. Left it on a study desk on the 2nd floor.', 
 'Intel Core i7 sticker, small scratch near the power port, desktop wallpaper is a blue nebula.', 
 '2026-10-15', '14:30:00', 'not_applicable', 'open'),

(2, 3, 'found', 1, 4, 'Dell Laptop in Gray Sleeve', 
 'Found a 15-inch Dell laptop in a gray sleeve on a desk near the 2nd-floor quiet study section. Handed over to library security desk.', 
 'Dark blue casing, small scratch near charging socket, charger included inside sleeve pocket.', 
 '2026-10-15', '16:00:00', 'submitted_to_security', 'open'),

(3, 3, 'found', 3, 6, 'Brown Leather Bi-fold Wallet', 
 'Found a brown leather wallet under table 14 during lunchtime. Finder is holding custody until verified.', 
 'Contains a city transit pass, two small brass locker keys, and a family photo in the card slot.', 
 '2026-10-16', '13:15:00', 'with_finder', 'open'),

(4, 2, 'lost', 2, 2, 'Silver Keychain with Bike Key', 
 'Lost a bundle of keys with a blue spiral wristband near Room 102 computer lab.', 
 'Contains 1 Yamaha motorcycle key and 2 small Yale locker keys.', 
 '2026-10-17', '10:00:00', 'not_applicable', 'open');

-- ---------------------------------------------------------------------
-- 5. SEED INITIAL SYSTEM NOTIFICATION
-- ---------------------------------------------------------------------
INSERT INTO notifications (user_id, title, message, type, is_read) VALUES
(1, 'System Initialized', 'Master database schema and demo fixtures loaded successfully.', 'system', 0),
(2, 'Welcome to SCLFS', 'Welcome Alex! Report any lost items or browse found campus belongings.', 'system', 0),
(3, 'Welcome to SCLFS', 'Welcome Priya! Thank you for helping keep our campus honest and helpful.', 'system', 0);
