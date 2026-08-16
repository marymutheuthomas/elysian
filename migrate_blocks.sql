-- ============================================================
-- Elysian Success — 4-Tier Content Hierarchy Migration
-- Non-destructive: preserves all existing data
-- Compatible with MySQL 8.0
-- ============================================================
USE elysian_success;

-- ─── 1. blocks table ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `blocks` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `pillar_id` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL DEFAULT 'Untitled Block',
  `sort_order` INT NOT NULL DEFAULT 0,
  CONSTRAINT `fk_blocks_pillars` FOREIGN KEY (`pillar_id`) REFERENCES `pillars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. pillars: add sort_order if missing ───────────────────
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'elysian_success'
    AND TABLE_NAME = 'pillars'
    AND COLUMN_NAME = 'sort_order'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `pillars` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0',
  'SELECT "pillars.sort_order already exists" AS note'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ─── 3. pillars: add congratulatory_note if missing ──────────
SET @col_exists2 = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'elysian_success'
    AND TABLE_NAME = 'pillars'
    AND COLUMN_NAME = 'congratulatory_note'
);
SET @sql2 = IF(@col_exists2 = 0,
  'ALTER TABLE `pillars` ADD COLUMN `congratulatory_note` TEXT NULL',
  'SELECT "pillars.congratulatory_note already exists" AS note'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- ─── 4. components: add block_id if missing ──────────────────
SET @col_exists3 = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'elysian_success'
    AND TABLE_NAME = 'components'
    AND COLUMN_NAME = 'block_id'
);
SET @sql3 = IF(@col_exists3 = 0,
  'ALTER TABLE `components` ADD COLUMN `block_id` VARCHAR(50) NULL AFTER `pillar_id`',
  'SELECT "components.block_id already exists" AS note'
);
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- ─── 5. components: add sort_order if missing ────────────────
SET @col_exists4 = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'elysian_success'
    AND TABLE_NAME = 'components'
    AND COLUMN_NAME = 'sort_order'
);
SET @sql4 = IF(@col_exists4 = 0,
  'ALTER TABLE `components` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0',
  'SELECT "components.sort_order already exists" AS note'
);
PREPARE stmt4 FROM @sql4; EXECUTE stmt4; DEALLOCATE PREPARE stmt4;

-- ─── 6. Seed pillar sort_order ────────────────────────────────
UPDATE `pillars` SET `sort_order` = 0 WHERE `sort_order` = 0;
SET @rn := 0;
UPDATE `pillars` SET `sort_order` = (@rn := @rn + 1) ORDER BY `id` ASC;

-- ─── 7. Create default block for each existing pillar ────────
INSERT IGNORE INTO `blocks` (`id`, `pillar_id`, `title`, `sort_order`)
SELECT
  CONCAT('DEFBLK-', p.id),
  p.id,
  'Main Assessment Block',
  1
FROM `pillars` p;

-- ─── 8. Map existing components to their default block ───────
UPDATE `components`
SET `block_id` = CONCAT('DEFBLK-', `pillar_id`)
WHERE `block_id` IS NULL OR `block_id` = '';

-- ─── 9. Seed component sort_order ────────────────────────────
SET @m := 0;
SET @last_p := '';
UPDATE `components` c
JOIN (
  SELECT id,
    @m := IF(@last_p = pillar_id, @m + 1, 1) AS rn,
    @last_p := pillar_id AS _lp
  FROM `components`
  ORDER BY pillar_id ASC, id ASC
) ranked ON c.id = ranked.id
SET c.sort_order = ranked.rn;

-- ─── 10. FK for block_id (ignore if already exists) ──────────
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = 'elysian_success'
    AND TABLE_NAME = 'components'
    AND CONSTRAINT_NAME = 'fk_components_blocks'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql5 = IF(@fk_exists = 0,
  'ALTER TABLE `components` ADD CONSTRAINT `fk_components_blocks` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`) ON DELETE SET NULL',
  'SELECT "FK fk_components_blocks already exists" AS note'
);
PREPARE stmt5 FROM @sql5; EXECUTE stmt5; DEALLOCATE PREPARE stmt5;

-- ─── 11. Seed default congratulatory notes ───────────────────
UPDATE `pillars`
SET `congratulatory_note` = CONCAT(
  '🌟 Congratulations! You have successfully completed the "',
  `title`,
  '" pillar. Your insights and responses have been recorded. Keep this momentum going as you advance to your next chapter!'
)
WHERE `congratulatory_note` IS NULL OR `congratulatory_note` = '';

-- ─── Done ─────────────────────────────────────────────────────
SELECT 'Migration complete.' AS status,
  (SELECT COUNT(*) FROM `blocks`) AS blocks_created,
  (SELECT COUNT(*) FROM `components` WHERE `block_id` IS NOT NULL) AS components_mapped;
