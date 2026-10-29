-- =====================================================================
-- R2026102901__add_indexes_to_pushes.sql
-- ---------------------------------------------------------------------
-- 用途：回滚 V2026102901__add_indexes_to_pushes.sql
-- 应用：CmsPro 记事本（cmspro.notepad）
-- 数据库：MySQL 5.7+ / 8.0
-- 执行：mysql -u<user> -p <database> < R2026102901__add_indexes_to_pushes.sql
-- 幂等：可重复执行
-- 注意：回滚仅恢复索引结构与迁移记录；第 3 步的重复数据还原为可选操作，
--       需人工确认后再执行（还原后表内会重新存在重复推送记录）
-- =====================================================================

-- ========== 1. 删除新增的唯一索引 ==========
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_cmspro_notepad_pushes'
      AND INDEX_NAME = 'app_cmspro_notepad_pushes_note_id_to_admin_user_id_unique'
);
SET @ddl := IF(@idx_exists > 0,
    'ALTER TABLE `app_cmspro_notepad_pushes` DROP INDEX `app_cmspro_notepad_pushes_note_id_to_admin_user_id_unique`',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========== 2. 删除新增的复合索引 ==========
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_cmspro_notepad_pushes'
      AND INDEX_NAME = 'app_cmspro_notepad_pushes_to_admin_user_id_is_read_index'
);
SET @ddl := IF(@idx_exists > 0,
    'ALTER TABLE `app_cmspro_notepad_pushes` DROP INDEX `app_cmspro_notepad_pushes_to_admin_user_id_is_read_index`',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========== 3. 恢复原三个单列索引 ==========
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_cmspro_notepad_pushes'
      AND INDEX_NAME = 'app_cmspro_notepad_pushes_note_id_index'
);
SET @ddl := IF(@idx_exists = 0,
    'ALTER TABLE `app_cmspro_notepad_pushes` ADD KEY `app_cmspro_notepad_pushes_note_id_index` (`note_id`)',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_cmspro_notepad_pushes'
      AND INDEX_NAME = 'app_cmspro_notepad_pushes_to_admin_user_id_index'
);
SET @ddl := IF(@idx_exists = 0,
    'ALTER TABLE `app_cmspro_notepad_pushes` ADD KEY `app_cmspro_notepad_pushes_to_admin_user_id_index` (`to_admin_user_id`)',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_cmspro_notepad_pushes'
      AND INDEX_NAME = 'app_cmspro_notepad_pushes_is_read_index'
);
SET @ddl := IF(@idx_exists = 0,
    'ALTER TABLE `app_cmspro_notepad_pushes` ADD KEY `app_cmspro_notepad_pushes_is_read_index` (`is_read`)',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========== 4. 移除迁移登记记录 ==========
DELETE FROM `migrations`
WHERE `migration` = '2026_10_29_000004_add_indexes_to_app_cmspro_notepad_pushes_table';

-- ========== 5.（可选）还原升级时被清理的重复推送记录 ==========
-- 确认业务需要保留这些重复记录后再取消注释执行：
-- INSERT INTO `app_cmspro_notepad_pushes`
-- SELECT bak.* FROM `app_cmspro_notepad_pushes_bak_20261029` bak
-- WHERE NOT EXISTS (
--     SELECT 1 FROM `app_cmspro_notepad_pushes` p WHERE p.`id` = bak.`id`
-- );

-- ========== 6.（可选）确认无误后清理快照表 ==========
-- DROP TABLE IF EXISTS `app_cmspro_notepad_pushes_bak_20261029`;

-- ========== 7. 验证 ==========
-- 期望结果：存在 PRIMARY 与三个单列索引，不存在 unique / 复合索引
SHOW INDEX FROM `app_cmspro_notepad_pushes`;
