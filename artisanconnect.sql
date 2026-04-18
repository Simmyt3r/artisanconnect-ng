-- ArtisanConnect NG Database Schema
-- Import this file via phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS artisanconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE artisanconnect;

-- Users table (central identity for all roles)
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('artisan','customer','admin') NOT NULL DEFAULT 'customer',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    profile_picture VARCHAR(500) DEFAULT 'default.png',
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Artisan profiles
CREATE TABLE artisan_profiles (
    profile_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    trade_category VARCHAR(100) NOT NULL,
    trade_subcategory VARCHAR(100),
    years_experience TINYINT UNSIGNED DEFAULT 0,
    bio TEXT,
    state_of_operation VARCHAR(100),
    lga_of_operation VARCHAR(100),
    is_available TINYINT(1) DEFAULT 1,
    average_rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT UNSIGNED DEFAULT 0,
    verification_status ENUM('unverified','pending','verified') DEFAULT 'unverified',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_trade (trade_category),
    INDEX idx_state (state_of_operation),
    INDEX idx_rating (average_rating)
) ENGINE=InnoDB;

-- Portfolio items
CREATE TABLE portfolio_items (
    item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artisan_id INT UNSIGNED NOT NULL,
    item_title VARCHAR(200) NOT NULL,
    item_description TEXT,
    image_path VARCHAR(500) NOT NULL,
    display_order TINYINT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (artisan_id) REFERENCES artisan_profiles(profile_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Services
CREATE TABLE services (
    service_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    artisan_id INT UNSIGNED NOT NULL,
    service_title VARCHAR(200) NOT NULL,
    service_description TEXT,
    pricing_type ENUM('fixed','hourly','negotiable') DEFAULT 'negotiable',
    base_price DECIMAL(10,2),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (artisan_id) REFERENCES artisan_profiles(profile_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Bookings
CREATE TABLE bookings (
    booking_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_user_id INT UNSIGNED NOT NULL,
    artisan_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED,
    booking_description TEXT NOT NULL,
    status ENUM('pending','accepted','declined','in_progress','completed','cancelled') DEFAULT 'pending',
    agreed_price DECIMAL(10,2),
    customer_confirmed TINYINT(1) DEFAULT 0,
    artisan_confirmed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (artisan_id) REFERENCES artisan_profiles(profile_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Reviews
CREATE TABLE reviews (
    review_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL UNIQUE,
    customer_user_id INT UNSIGNED NOT NULL,
    artisan_id INT UNSIGNED NOT NULL,
    overall_rating TINYINT UNSIGNED NOT NULL,
    quality_rating TINYINT UNSIGNED,
    timeliness_rating TINYINT UNSIGNED,
    communication_rating TINYINT UNSIGNED,
    review_title VARCHAR(200),
    review_body TEXT,
    artisan_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE CASCADE,
    FOREIGN KEY (artisan_id) REFERENCES artisan_profiles(profile_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Conversations
CREATE TABLE conversations (
    conversation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user1_id INT UNSIGNED NOT NULL,
    user2_id INT UNSIGNED NOT NULL,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user1_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (user2_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_conv (user1_id, user2_id)
) ENGINE=InnoDB;

-- Messages
CREATE TABLE messages (
    message_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message_body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed admin user (password: Admin@1234)
INSERT INTO users (email, password_hash, role, first_name, last_name, is_verified) VALUES
('admin@artisanconnect.ng', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Platform', 'Admin', 1);

-- Seed sample artisan (password: Test@1234)
INSERT INTO users (email, password_hash, role, first_name, last_name, phone, is_verified) VALUES
('chukwu@demo.ng', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'artisan', 'Chukwu', 'Emeka', '08012345678', 1),
('adaeze@demo.ng', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'artisan', 'Adaeze', 'Obi', '08023456789', 1),
('musa@demo.ng',  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'artisan', 'Musa', 'Abubakar', '08034567890', 1),
('customer@demo.ng', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer', 'Ngozi', 'Eze', '08045678901', 1);

INSERT INTO artisan_profiles (user_id, trade_category, trade_subcategory, years_experience, bio, state_of_operation, lga_of_operation, average_rating, total_reviews, verification_status) VALUES
(2, 'Carpentry', 'Furniture Making', 8, 'Experienced furniture maker specialising in custom wardrobes, beds, and office furniture. I deliver on time and my work speaks for itself.', 'FCT', 'Abuja Municipal', 4.70, 14, 'verified'),
(3, 'Tailoring', 'Fashion Design', 5, 'Creative fashion designer with expertise in native attire, corporate wear, and casual outfits. Bringing your style vision to life since 2019.', 'Nasarawa', 'Keffi', 4.50, 9, 'verified'),
(4, 'Electrical', 'Installation & Wiring', 12, 'Certified electrician providing safe and reliable electrical installation, maintenance, and repairs for homes and offices in Abuja.', 'FCT', 'Gwagwalada', 4.80, 21, 'verified');

INSERT INTO services (artisan_id, service_title, service_description, pricing_type, base_price) VALUES
(1, 'Custom Wardrobe', 'Made-to-measure wooden wardrobes for any room size', 'fixed', 45000.00),
(1, 'Office Table', 'Professional office desks in various finishes', 'fixed', 25000.00),
(2, 'Agbada & Senator Set', 'Complete native outfit sewn to your measurements', 'fixed', 15000.00),
(2, 'Corporate Suit', 'Formal corporate wear for men and women', 'fixed', 20000.00),
(3, 'Full House Wiring', 'Complete electrical wiring for new construction', 'negotiable', NULL),
(3, 'Fault Detection & Repair', 'Diagnose and fix electrical faults quickly', 'fixed', 5000.00);
