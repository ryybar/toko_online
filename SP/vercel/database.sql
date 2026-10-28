CREATE DATABASE IF NOT EXISTS toko_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE toko_online;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','seller','customer') NOT NULL DEFAULT 'customer',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  owner_singleton TINYINT GENERATED ALWAYS AS (CASE WHEN role='owner' THEN 1 ELSE NULL END) STORED,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_single_owner (owner_singleton)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_activity (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  last_seen_at DATETIME NOT NULL,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seller_applications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE,
  seller_status ENUM('active','planning') NOT NULL,
  product_name VARCHAR(120) NOT NULL,
  product_category VARCHAR(60) NOT NULL,
  product_description VARCHAR(255) NOT NULL,
  expected_monthly_profit INT UNSIGNED NOT NULL DEFAULT 0,
  item_weight_grams INT UNSIGNED NOT NULL DEFAULT 0,
  monthly_plan ENUM('trial','monthly') NOT NULL DEFAULT 'monthly',
  monthly_budget INT UNSIGNED DEFAULT NULL,
  application_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_seller_application_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_seller_application_status (application_status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id BIGINT UNSIGNED DEFAULT NULL,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(60) NOT NULL,
  description VARCHAR(255) NOT NULL,
  brand VARCHAR(100) DEFAULT NULL,
  sku VARCHAR(80) DEFAULT NULL,
  material VARCHAR(100) DEFAULT NULL,
  color_variant VARCHAR(100) DEFAULT NULL,
  dimensions VARCHAR(100) DEFAULT NULL,
  weight_grams INT UNSIGNED NOT NULL DEFAULT 0,
  product_condition ENUM('baru','preloved') NOT NULL DEFAULT 'baru',
  shipping_days TINYINT UNSIGNED NOT NULL DEFAULT 3,
  image_url VARCHAR(500) DEFAULT NULL,
  price INT UNSIGNED NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  emoji VARCHAR(12) NOT NULL DEFAULT '🛍️',
  color VARCHAR(30) NOT NULL DEFAULT 'peach',
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  review_note VARCHAR(500) DEFAULT NULL,
  reviewed_by BIGINT UNSIGNED DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_products_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_products_active (is_active,review_status),
  INDEX idx_products_seller_review (seller_id,review_status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id BIGINT UNSIGNED NOT NULL,
  customer_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address TEXT NOT NULL,
  total INT UNSIGNED NOT NULL,
  status ENUM('baru','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'baru',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES users(id),
  INDEX idx_orders_created (created_at),
  INDEX idx_orders_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  seller_id BIGINT UNSIGNED DEFAULT NULL,
  product_name VARCHAR(120) NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL,
  unit_price INT UNSIGNED NOT NULL,
  line_total INT UNSIGNED NOT NULL,
  status ENUM('baru','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'baru',
  status_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_items_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_items_seller (seller_id)
) ENGINE=InnoDB;

-- Produk ditambahkan penjual dan perlu persetujuan owner sebelum tayang.

CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
 type VARCHAR(40) NOT NULL, title VARCHAR(160) NOT NULL, message VARCHAR(500) NOT NULL,
 target_url VARCHAR(255) NOT NULL DEFAULT 'dashboard.php', is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_notifications_user_read (user_id,is_read,created_at)
) ENGINE=InnoDB;


