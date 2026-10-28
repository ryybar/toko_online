USE toko_online;

-- Add new fields only when missing; existing rows and order history are retained.
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_shop_add_column$$
CREATE PROCEDURE sp_shop_add_column(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl_text TEXT)
BEGIN
 IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=tbl AND column_name=col) THEN
  SET @shop_ddl=ddl_text; PREPARE shop_stmt FROM @shop_ddl; EXECUTE shop_stmt; DEALLOCATE PREPARE shop_stmt;
 END IF;
END$$
DELIMITER ;

CALL sp_shop_add_column('users','owner_singleton',"ALTER TABLE users ADD COLUMN owner_singleton TINYINT GENERATED ALWAYS AS (CASE WHEN role='owner' THEN 1 ELSE NULL END) STORED");
CALL sp_shop_add_column('products','brand',"ALTER TABLE products ADD COLUMN brand VARCHAR(100) DEFAULT NULL");
CALL sp_shop_add_column('products','sku',"ALTER TABLE products ADD COLUMN sku VARCHAR(80) DEFAULT NULL");
CALL sp_shop_add_column('products','material',"ALTER TABLE products ADD COLUMN material VARCHAR(100) DEFAULT NULL");
CALL sp_shop_add_column('products','color_variant',"ALTER TABLE products ADD COLUMN color_variant VARCHAR(100) DEFAULT NULL");
CALL sp_shop_add_column('products','dimensions',"ALTER TABLE products ADD COLUMN dimensions VARCHAR(100) DEFAULT NULL");
CALL sp_shop_add_column('products','weight_grams',"ALTER TABLE products ADD COLUMN weight_grams INT UNSIGNED NOT NULL DEFAULT 0");
CALL sp_shop_add_column('products','product_condition',"ALTER TABLE products ADD COLUMN product_condition ENUM('baru','preloved') NOT NULL DEFAULT 'baru'");
CALL sp_shop_add_column('products','shipping_days',"ALTER TABLE products ADD COLUMN shipping_days TINYINT UNSIGNED NOT NULL DEFAULT 3");
CALL sp_shop_add_column('products','image_url',"ALTER TABLE products ADD COLUMN image_url VARCHAR(500) DEFAULT NULL");
CALL sp_shop_add_column('products','review_status',"ALTER TABLE products ADD COLUMN review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'approved'");
CALL sp_shop_add_column('products','review_note',"ALTER TABLE products ADD COLUMN review_note VARCHAR(500) DEFAULT NULL");
CALL sp_shop_add_column('products','reviewed_by',"ALTER TABLE products ADD COLUMN reviewed_by BIGINT UNSIGNED DEFAULT NULL");
CALL sp_shop_add_column('products','reviewed_at',"ALTER TABLE products ADD COLUMN reviewed_at DATETIME DEFAULT NULL");
CALL sp_shop_add_column('order_items','status',"ALTER TABLE order_items ADD COLUMN status ENUM('baru','diproses','dikirim','selesai','dibatalkan') NOT NULL DEFAULT 'baru'");
CALL sp_shop_add_column('order_items','status_updated_at',"ALTER TABLE order_items ADD COLUMN status_updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
DROP PROCEDURE sp_shop_add_column;

CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 type VARCHAR(40) NOT NULL,
 title VARCHAR(160) NOT NULL,
 message VARCHAR(500) NOT NULL,
 target_url VARCHAR(255) NOT NULL DEFAULT 'dashboard.php',
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_notifications_user_read (user_id,is_read,created_at)
) ENGINE=InnoDB;

-- This fails safely if the current database contains more than one owner.
SET @owner_unique_exists=(SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='users' AND index_name='uq_users_single_owner');
SET @owner_unique_sql=IF(@owner_unique_exists=0,'ALTER TABLE users ADD UNIQUE KEY uq_users_single_owner (owner_singleton)','SELECT 1');
PREPARE owner_unique_stmt FROM @owner_unique_sql; EXECUTE owner_unique_stmt; DEALLOCATE PREPARE owner_unique_stmt;
UPDATE order_items i JOIN orders o ON o.id=i.order_id SET i.status=o.status WHERE i.status='baru' AND o.status<>'baru';
UPDATE products SET is_active=0 WHERE seller_id IS NULL;


