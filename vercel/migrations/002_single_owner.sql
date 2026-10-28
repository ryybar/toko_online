-- Tambahkan batasan database agar hanya ada satu akun dengan role owner.
-- Salin database terlebih dahulu. Jika sudah ada owner ganda, pembuatan unique key
-- akan gagal tanpa menghapus akun; rapikan duplikat secara manual sebelum mencoba lagi.

SET @owner_guard_column_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'owner_singleton'
);
SET @owner_guard_column_sql = IF(
  @owner_guard_column_exists = 0,
  'ALTER TABLE users ADD COLUMN owner_singleton TINYINT GENERATED ALWAYS AS (CASE WHEN role = ''owner'' THEN 1 ELSE NULL END) STORED',
  'SELECT ''owner_singleton column already exists'' AS migration_status'
);
PREPARE owner_guard_column_stmt FROM @owner_guard_column_sql;
EXECUTE owner_guard_column_stmt;
DEALLOCATE PREPARE owner_guard_column_stmt;

SET @owner_guard_index_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_single_owner'
);
SET @owner_guard_index_sql = IF(
  @owner_guard_index_exists = 0,
  'ALTER TABLE users ADD UNIQUE KEY uq_users_single_owner (owner_singleton)',
  'SELECT ''owner singleton index already exists'' AS migration_status'
);
PREPARE owner_guard_index_stmt FROM @owner_guard_index_sql;
EXECUTE owner_guard_index_stmt;
DEALLOCATE PREPARE owner_guard_index_stmt;