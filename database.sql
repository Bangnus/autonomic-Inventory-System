-- Inventory Request System Database Schema (Updated)
-- Run this in MySQL/MariaDB before using the app

CREATE DATABASE IF NOT EXISTS inventory_request CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inventory_request;

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  signature_base64 MEDIUMTEXT NOT NULL,
  role ENUM('admin','user') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Products table
CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(150) NOT NULL,
  serial VARCHAR(100) NULL,
  model VARCHAR(100) NULL,
  stock_quantity INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_products_search (code, name, serial, model)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Transactions: requests and returns
CREATE TABLE IF NOT EXISTS transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  type ENUM('request','return') NOT NULL,
  quantity INT NOT NULL,
  signature_base64 MEDIUMTEXT NOT NULL,
  pdf_filename VARCHAR(255) NULL,
  date date DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_transactions_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  INDEX idx_transactions_filters (user_id, product_id, type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Separate Request table
CREATE TABLE IF NOT EXISTS requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_requests_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  INDEX idx_requests_filters (user_id, product_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Separate Return table
-- CREATE TABLE IF NOT EXISTS returns (
--   id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
--   user_id INT UNSIGNED NOT NULL,
--   product_id INT UNSIGNED NOT NULL,
--   quantity INT NOT NULL,
--   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--   CONSTRAINT fk_returns_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
--   CONSTRAINT fk_returns_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
--   INDEX idx_returns_filters (user_id, product_id, created_at)
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

