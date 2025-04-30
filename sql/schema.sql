-- Database schema for CME Website

CREATE DATABASE IF NOT EXISTS cme_website CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cme_website;

-- Members table
CREATE TABLE IF NOT EXISTS members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    birthday DATE DEFAULT NULL,
    gender ENUM('male', 'female', 'other') DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    member_level ENUM('silver', 'gold', 'platinum') DEFAULT 'silver',
    points INT DEFAULT 0,
    referral_code VARCHAR(50) UNIQUE,
    referred_by VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    shipping_address TEXT,
    points_redeemed INT DEFAULT 0,
    FOREIGN KEY (member_id) REFERENCES members(id)
);

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Subscriptions table
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    email VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    verified BOOLEAN DEFAULT FALSE,
    verification_code VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id)
);

-- Rewards table
CREATE TABLE IF NOT EXISTS rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('birthday', 'registration', 'subscription') NOT NULL,
    points INT NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    active BOOLEAN DEFAULT TRUE
);

-- Referral commissions table
CREATE TABLE IF NOT EXISTS referral_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    referred_member_id INT NOT NULL,
    points_earned INT NOT NULL,
    commission_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (referred_member_id) REFERENCES members(id)
);

-- Points redemption mall table
CREATE TABLE IF NOT EXISTS mall_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    points_required INT NOT NULL,
    stock INT DEFAULT 0,
    active BOOLEAN DEFAULT TRUE
);

-- Points redemption orders table
CREATE TABLE IF NOT EXISTS mall_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    mall_item_id INT NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (mall_item_id) REFERENCES mall_items(id)
);
