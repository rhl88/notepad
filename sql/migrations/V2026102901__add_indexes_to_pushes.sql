-- =====================================================================
-- V2026102901__add_indexes_to_pushes.sql
-- ---------------------------------------------------------------------
-- 应用：CmsPro 记事本（cmspro.notepad）
-- 变更：为 app_cmspro_notepad_pushes 补唯一索引与复合索引，清理被覆盖的冗余单列索引
-- 数据库：MySQL 5.7+ / 8.0（其它驱动不适用，SQLite 测试环境由 Tests 自建表结构）
-- 执行：mysql -u<user> -p <database> < V2026102901__add_indexes_to_pushes.sql
-- 幂等：可重复执行，全部 DDL 前均做 information_schema 存在性判断
-- 说明：若通过应用市场「升级」按钮升级，PHP 迁移 000004 会自动完成以下全部动作，
--       本脚本仅用于不便走应用升级流程时的手工执行（此时需执行第 5 步登记迁移记录）
-- 回滚：sql/rollback/R2026102901__add_indexes_to_pushes.sql
-- =====================================================================

-- ========== 0. 执行前备份 ==========
-- 第 1 步会物理删除重复推送记录，务必先备份。二选一：
--   A) 库外备份（推荐）：
--      mysqldump -u<user> -p <database> app_cmspro_notepad_pushes > backup_pushes_20261029.sql
--   B) 库内快照表（下面自动执行，回滚脚本可据此还原）：
CREATE TABLE IF NOT EXISTS `app_cmspro_notepad_pushes_bak_20261029`
    LIKE `app_cmspro_notepad_pushes`;

INSERT INTO `app_cmspro_notepad_pushes_bak_20261029`
SELECT b.* FROM `app_cmspro_notepad_pushes` b
WHERE NOT EXISTS (
    SELECT 1 FROM `app_cmspro_notepad_pushes_bak_20261029` a WHERE a.`id` = b.`id`
);

-- ========== 1. 清理重复推送 ==========
-- 规则：同一便签推送给同一管理员时只保留 id 最小的一条（首次推送）
-- 1.1 预检查：先执行本查询，确认将被删除的记录符合预期
SELECT p.`id`, p.`note_id`, p.`to_admin_user_id`, p.`message`, p.`create_time`
FROM `app_cmspro_notepad_pushes` p
INNER JOIN (
    SELECT `note_id`, `to_admin_user_id`, MIN(`id`) AS keep_id
    FROM `app_cmspro_notepad_pushes`
    GROUP BY `note_id`, `to_admin_user_id`
    HAVING COUNT(*) > 1
) d ON p.`note_id` = d.`note_id`
   AND p.`to_admin_user_id` = d.`to_admin_user_id`
   AND p.`id` > d.keep_id;

-- 1.2 执行删除（唯一索引建立前必须先消除重复，否则 ALTER 直接失败）
DELETE p FROM `app_cmspro_notepad_pushes` p
INNER JOIN (
    SELECT `note_id`, `to_admin_user_id`, MIN(`id`) AS keep_id
    FROM `app_cmspro_notepad_pushes`
    GROUP BY `note_id`, `to_admin_user_id`
    HAVING COUNT(*) > 1
) d ON p.`note_id` = d.`note_id`
   AND p.`to_admin_user_id` = d.`to_admin_user_id`
   AND p.`id` > d.keep_id;

-- ========== 2. 添加唯一索引（note_id + to_admin_user_id） ==========
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_cmspro_notepad_pushes'
      AND INDEX_NAME = 'app_cmspro_notepad_pushes_note_id_to_admin_user_id_unique'
);
SET @ddl := IF(@idx_exists = 0,
    'ALTER TABLE `app_cmspro_notepad_pushes` ADD UNIQUE KEY `app_cmspro_notepad_pushes_note_id_to_admin_user_id_unique` (`note_id`, `to_admin_user_id`)',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========== 3. 添加复合索引（to_admin_user_id + is_read） ==========
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_cmspro_notepad_pushes'
      AND INDEX_NAME = 'app_cmspro_notepad_pushes_to_admin_user_id_is_read_index'
);
SET @ddl := IF(@idx_exists = 0,
    'ALTER TABLE `app_cmspro_notepad_pushes` ADD KEY `app_cmspro_notepad_pushes_to_admin_user_id_is_read_index` (`to_admin_user_id`, `is_read`)',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========== 4. 删除被最左前缀覆盖的冗余单列索引 ==========
-- 4.1 note_id（被第 2 步唯一索引的最左前缀覆盖）
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_cmspro_notepad_pushes'
      AND INDEX_NAME = 'app_cmspro_notepad_pushes_note_id_index'
);
SET @ddl := IF(@idx_exists > 0,
    'ALTER TABLE `app_cmspro_notepad_pushes` DROP INDEX `app_cmspro_notepad_pushes_note_id_index`',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4.2 to_admin_user_id（被第 3 步复合索引的最左前缀覆盖）
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_cmspro_notepad_pushes'
      AND INDEX_NAME = 'app_cmspro_notepad_pushes_to_admin_user_id_index'
);
SET @ddl := IF(@idx_exists > 0,
    'ALTER TABLE `app_cmspro_notepad_pushes` DROP INDEX `app_cmspro_notepad_pushes_to_admin_user_id_index`',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4.3 is_read（选择性极低，单独建索引无收益，已由复合索引替代）
SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_cmspro_notepad_pushes'
      AND INDEX_NAME = 'app_cmspro_notepad_pushes_is_read_index'
);
SET @ddl := IF(@idx_exists > 0,
    'ALTER TABLE `app_cmspro_notepad_pushes` DROP INDEX `app_cmspro_notepad_pushes_is_read_index`',
    'DO 0');
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ========== 5. 登记迁移记录（仅手工执行本脚本时需要） ==========
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_29_000004_add_indexes_to_app_cmspro_notepad_pushes_table',
       (SELECT COALESCE(MAX(t.`batch`), 0) + 1 FROM (SELECT `batch` FROM `migrations`) t)
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations` m
    WHERE m.`migration` = '2026_10_29_000004_add_indexes_to_app_cmspro_notepad_pushes_table'
);

-- ========== 6. 验证 ==========
-- 期望结果：仅存在 PRIMARY、..._note_id_to_admin_user_id_unique、..._to_admin_user_id_is_read_index
SHOW INDEX FROM `app_cmspro_notepad_pushes`;

-- 期望结果：0 行
SELECT `note_id`, `to_admin_user_id`, COUNT(*) AS cnt
FROM `app_cmspro_notepad_pushes`
GROUP BY `note_id`, `to_admin_user_id`
HAVING COUNT(*) > 1;
