-- Merge fee change request workflow into clearancefeetypetbl.
-- Drops the separate clearancefeechangerequeststbl.
-- Safe to run multiple times.

SET NAMES utf8mb4;

-- 1. Add status column (replaces is_active)
ALTER TABLE clearancefeetypetbl
  ADD COLUMN IF NOT EXISTS `status` ENUM('pending','approved','rejected')
    NOT NULL DEFAULT 'approved' AFTER `is_active`;

-- 2. Migrate is_active → status
UPDATE clearancefeetypetbl
  SET `status` = IF(`is_active` = 1, 'approved', 'rejected')
  WHERE `status` = 'approved' OR `status` = 'rejected';

-- 3. Add request-tracking columns
ALTER TABLE clearancefeetypetbl
  ADD COLUMN IF NOT EXISTS `proposed_amount`      DECIMAL(12,2)           DEFAULT NULL AFTER `default_amount`,
  ADD COLUMN IF NOT EXISTS `change_type`          ENUM('new_type','price_edit') DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `notes`                TEXT                    DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `requested_by_user_id` VARCHAR(64)             DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `reviewed_by_user_id`  VARCHAR(64)             DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `reviewed_at`          DATETIME                DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `review_notes`         TEXT                    DEFAULT NULL;

-- 4. Add index on status
ALTER TABLE clearancefeetypetbl
  ADD KEY IF NOT EXISTS idx_clearancefeetypes_status (`status`);

-- 5. Drop the now-redundant separate table
DROP TABLE IF EXISTS clearancefeechangerequeststbl;
