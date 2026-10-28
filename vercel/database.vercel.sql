CREATE DATABASE IF NOT EXISTS toko_online CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE toko_online;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','seller','customer') NOT NULL DEFAULT 'customer',
  owner_singleton TINYINT GENERATED ALWAYS AS (CASE WHEN role='owner' THEN 1 ELSE NULL END) STORED,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
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
  price INT UNSIGNED NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  emoji VARCHAR(12) NOT NULL DEFAULT '🛍️',
  color VARCHAR(30) NOT NULL DEFAULT 'peach',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_products_active (is_active)
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
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_items_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_items_seller (seller_id)
) ENGINE=InnoDB;

INSERT INTO products (seller_id,name,category,description,price,stock,emoji,color) VALUES
(NULL,'Tote Bag Kanvas','Aksesori','Ringan, kuat, dan siap menemani aktivitasmu.',45000,25,'👜','peach'),
(NULL,'Tumbler Daily','Lifestyle','Teman minum praktis untuk setiap perjalanan.',68000,30,'🥤','mint'),
(NULL,'Notebook A5','Alat tulis','Catat ide besar di halaman yang nyaman.',32000,40,'📒','lavender'),
(NULL,'Lampu Meja Mini','Rumah','Pencahayaan hangat untuk meja favoritmu.',89000,15,'💡','yellow'),
(NULL,'Headphone Cozy','Elektronik','Nikmati musik dan fokus dengan lebih nyaman.',125000,12,'🎧','blue'),
(NULL,'Pouch Serbaguna','Aksesori','Barang kecil tertata rapi, mudah dibawa.',39000,28,'👝','pink');

-- Additive tables used by the multi-seller marketplace.
CREATE TABLE IF NOT EXISTS terms_documents (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,audience ENUM('customer','seller') NOT NULL,version VARCHAR(32) NOT NULL,title VARCHAR(160) NOT NULL,body LONGTEXT NOT NULL,is_active TINYINT(1) NOT NULL DEFAULT 0,requires_reacceptance TINYINT(1) NOT NULL DEFAULT 0,published_by BIGINT UNSIGNED DEFAULT NULL,published_at DATETIME DEFAULT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_terms_audience_version(audience,version),CONSTRAINT fk_terms_publisher FOREIGN KEY(published_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS terms_acceptances (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,terms_document_id BIGINT UNSIGNED NOT NULL,accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,accepted_ip VARCHAR(45) DEFAULT NULL,UNIQUE KEY uq_user_terms_acceptance(user_id,terms_document_id),CONSTRAINT fk_terms_acceptance_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_terms_acceptance_doc FOREIGN KEY(terms_document_id) REFERENCES terms_documents(id)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS seller_business_profiles (user_id BIGINT UNSIGNED PRIMARY KEY,store_name VARCHAR(120) NOT NULL,store_description VARCHAR(1000) NOT NULL,store_address TEXT NOT NULL,store_phone VARCHAR(30) NOT NULL,application_status ENUM('pending','approved','rejected','suspended','blocked') NOT NULL DEFAULT 'pending',seller_status ENUM('active','planning') NOT NULL DEFAULT 'active',expected_monthly_profit DECIMAL(14,2) UNSIGNED NOT NULL DEFAULT 0,item_weight_grams INT UNSIGNED NOT NULL DEFAULT 0,monthly_plan ENUM('trial','monthly') NOT NULL DEFAULT 'trial',monthly_budget DECIMAL(12,2) UNSIGNED DEFAULT NULL,reviewed_by BIGINT UNSIGNED DEFAULT NULL,reviewed_at DATETIME DEFAULT NULL,review_note VARCHAR(500) DEFAULT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_business_profile_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_business_profile_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,INDEX idx_seller_profile_status(application_status,created_at)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_catalog_details (product_id INT UNSIGNED PRIMARY KEY,sku VARCHAR(64) DEFAULT NULL,discount_price DECIMAL(12,2) UNSIGNED DEFAULT NULL,weight_grams INT UNSIGNED DEFAULT NULL,product_status ENUM('draft','active','inactive','suspended') NOT NULL DEFAULT 'active',updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_product_sku(sku),CONSTRAINT fk_catalog_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS product_images (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,product_id INT UNSIGNED NOT NULL,image_path VARCHAR(255) NOT NULL,alt_text VARCHAR(160) DEFAULT NULL,sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_product_image_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,INDEX idx_product_images_order(product_id,sort_order)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS seller_orders (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,order_id BIGINT UNSIGNED NOT NULL,seller_id BIGINT UNSIGNED NOT NULL,status ENUM('new','confirmed','awaiting_cod','packing','ready_to_ship','shipping','delivered','completed','cancelled','issue') NOT NULL DEFAULT 'new',payment_method ENUM('cod') NOT NULL DEFAULT 'cod',payment_status ENUM('unpaid','received','issue') NOT NULL DEFAULT 'unpaid',carrier VARCHAR(80) DEFAULT NULL,tracking_number VARCHAR(100) DEFAULT NULL,seller_note VARCHAR(500) DEFAULT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_order_seller(order_id,seller_id),CONSTRAINT fk_seller_order_parent FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,CONSTRAINT fk_seller_order_seller FOREIGN KEY(seller_id) REFERENCES users(id),INDEX idx_seller_order_queue(seller_id,status,created_at)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS order_status_history (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,seller_order_id BIGINT UNSIGNED NOT NULL,changed_by BIGINT UNSIGNED DEFAULT NULL,from_status VARCHAR(32) DEFAULT NULL,to_status VARCHAR(32) NOT NULL,reason VARCHAR(500) DEFAULT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_order_history_suborder FOREIGN KEY(seller_order_id) REFERENCES seller_orders(id) ON DELETE CASCADE,CONSTRAINT fk_order_history_user FOREIGN KEY(changed_by) REFERENCES users(id) ON DELETE SET NULL,INDEX idx_order_history_time(seller_order_id,created_at)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS notifications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,recipient_id BIGINT UNSIGNED NOT NULL,kind VARCHAR(48) NOT NULL,title VARCHAR(160) NOT NULL,body VARCHAR(1000) NOT NULL,target_url VARCHAR(255) DEFAULT NULL,reference_type VARCHAR(48) DEFAULT NULL,reference_id BIGINT UNSIGNED DEFAULT NULL,dedupe_key VARCHAR(190) DEFAULT NULL,is_read TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_notification_dedupe(recipient_id,dedupe_key),CONSTRAINT fk_notification_recipient FOREIGN KEY(recipient_id) REFERENCES users(id) ON DELETE CASCADE,INDEX idx_notification_inbox(recipient_id,is_read,created_at)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS announcements (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,author_id BIGINT UNSIGNED NOT NULL,title VARCHAR(160) NOT NULL,body TEXT NOT NULL,audience ENUM('all','seller','customer','user') NOT NULL DEFAULT 'all',recipient_id BIGINT UNSIGNED DEFAULT NULL,is_published TINYINT(1) NOT NULL DEFAULT 0,published_at DATETIME DEFAULT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_announcement_author FOREIGN KEY(author_id) REFERENCES users(id),CONSTRAINT fk_announcement_recipient FOREIGN KEY(recipient_id) REFERENCES users(id) ON DELETE SET NULL,INDEX idx_announcements_live(is_published,published_at)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS account_actions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL,action ENUM('suspended','blocked','deactivated','reactivated','appeal') NOT NULL,reason VARCHAR(500) NOT NULL,actor_id BIGINT UNSIGNED DEFAULT NULL,starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,ends_at DATETIME DEFAULT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_account_action_user FOREIGN KEY(user_id) REFERENCES users(id),CONSTRAINT fk_account_action_actor FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL,INDEX idx_account_action_user(user_id,created_at)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,actor_id BIGINT UNSIGNED DEFAULT NULL,action VARCHAR(80) NOT NULL,entity_type VARCHAR(48) NOT NULL,entity_id BIGINT UNSIGNED DEFAULT NULL,reason VARCHAR(500) DEFAULT NULL,before_data JSON DEFAULT NULL,after_data JSON DEFAULT NULL,ip_address VARCHAR(45) DEFAULT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,CONSTRAINT fk_audit_actor FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL,INDEX idx_audit_entity(entity_type,entity_id,created_at),INDEX idx_audit_actor(actor_id,created_at)) ENGINE=InnoDB;
INSERT INTO terms_documents(audience,version,title,body,is_active,requires_reacceptance,published_at) SELECT 'customer','1.0','Syarat dan Ketentuan Pembeli','Pembeli wajib memberikan data penerima yang benar, memeriksa detail pesanan, dan membayar pesanan COD saat barang diterima. Pembatalan mengikuti kebijakan yang ditampilkan saat pembelian.',1,0,NOW() WHERE NOT EXISTS(SELECT 1 FROM terms_documents WHERE audience='customer' AND version='1.0');
INSERT INTO terms_documents(audience,version,title,body,is_active,requires_reacceptance,published_at) SELECT 'seller','1.0','Syarat dan Ketentuan Penjual','Penjual bertanggung jawab atas keakuratan produk, harga, stok, pengemasan, dan pemrosesan pengiriman. Penjual hanya dapat mengakses data miliknya. Biaya paket harus dikonfirmasi tertulis oleh owner sebelum berlaku.',1,0,NOW() WHERE NOT EXISTS(SELECT 1 FROM terms_documents WHERE audience='seller' AND version='1.0');

CREATE TABLE IF NOT EXISTS carts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED NOT NULL UNIQUE,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,CONSTRAINT fk_cart_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cart_items (cart_id BIGINT UNSIGNED NOT NULL,product_id INT UNSIGNED NOT NULL,quantity SMALLINT UNSIGNED NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(cart_id,product_id),CONSTRAINT fk_cart_item_cart FOREIGN KEY(cart_id) REFERENCES carts(id) ON DELETE CASCADE,CONSTRAINT fk_cart_item_product FOREIGN KEY(product_id) REFERENCES products(id)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS auth_attempts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ip_hash CHAR(64) NOT NULL,email_hash CHAR(64) NOT NULL,attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX idx_auth_attempt_lookup(ip_hash,email_hash,attempted_at)) ENGINE=InnoDB;

-- Persistent session storage for serverless deployments (if PHP is configured to use it).
CREATE TABLE IF NOT EXISTS app_sessions (
  session_id VARCHAR(128) NOT NULL PRIMARY KEY,
  payload LONGBLOB NOT NULL,
  expires_at BIGINT UNSIGNED NOT NULL,
  INDEX idx_app_sessions_expiry (expires_at)
) ENGINE=InnoDB;
