START TRANSACTION;

CREATE TABLE IF NOT EXISTS `areamastertbl` (
  `area_id` INT(11) NOT NULL AUTO_INCREMENT,
  `area_code` VARCHAR(20) NOT NULL,
  `area_name` VARCHAR(100) NOT NULL,
  `sort_order` INT(11) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`area_id`),
  UNIQUE KEY `uq_areamaster_code` (`area_code`),
  UNIQUE KEY `uq_areamaster_name` (`area_name`),
  KEY `idx_areamaster_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `areamastertbl` (`area_code`, `area_name`, `sort_order`, `is_active`)
VALUES
  ('AREA01', 'Area 01', 1, 1),
  ('AREA1A', 'Area 1A', 2, 1),
  ('AREA02', 'Area 02', 3, 1),
  ('AREA03', 'Area 03', 4, 1),
  ('AREA04', 'Area 04', 5, 1),
  ('AREA05', 'Area 05', 6, 1),
  ('AREA06', 'Area 06', 7, 1)
ON DUPLICATE KEY UPDATE
  `area_name` = VALUES(`area_name`),
  `sort_order` = VALUES(`sort_order`),
  `is_active` = VALUES(`is_active`);

ALTER TABLE `caseparticipantstbl`
ADD COLUMN IF NOT EXISTS `area_number` VARCHAR(50) DEFAULT NULL AFTER `address`;

CREATE INDEX `idx_caseparticipant_area`
ON `caseparticipantstbl` (`area_number`);

CREATE INDEX `idx_caseparticipant_case_role_area`
ON `caseparticipantstbl` (`case_id`, `participant_role`, `area_number`);

UPDATE `caseparticipantstbl`
SET `area_number` = TRIM(
  SUBSTRING_INDEX(
    SUBSTRING_INDEX(`address`, 'Area:', -1),
    ',',
    1
  )
)
WHERE (`area_number` IS NULL OR TRIM(`area_number`) = '')
  AND `address` IS NOT NULL
  AND `address` LIKE '%Area:%';

CREATE OR REPLACE VIEW `vw_resident_area_current` AS
SELECT
  r.`resident_id`,
  r.`user_id`,
  r.`lastname`,
  r.`firstname`,
  r.`middlename`,
  r.`suffix`,
  r.`sex`,
  r.`birthdate`,
  r.`civil_status`,
  r.`head_of_family`,
  r.`sector_membership`,
  r.`status_id_resident`,
  a.`address_id`,
  a.`area_number`
FROM `residentinformationtbl` r
LEFT JOIN `residentaddresstbl` a
  ON a.`address_id` = (
    SELECT a2.`address_id`
    FROM `residentaddresstbl` a2
    WHERE a2.`resident_id` = r.`resident_id`
    ORDER BY a2.`address_id` DESC
    LIMIT 1
  );

CREATE OR REPLACE VIEW `vw_area_resident_summary` AS
SELECT
  COALESCE(NULLIF(TRIM(v.`area_number`), ''), 'Unspecified Area') AS `area_name`,
  COUNT(*) AS `total_residents`,
  SUM(CASE WHEN s.`status_name` = 'VerifiedResident' THEN 1 ELSE 0 END) AS `verified_residents`,
  SUM(CASE WHEN s.`status_name` = 'PendingVerification' THEN 1 ELSE 0 END) AS `pending_residents`,
  SUM(CASE WHEN s.`status_name` = 'NotVerified' THEN 1 ELSE 0 END) AS `not_verified_residents`,
  SUM(CASE WHEN r.`sex` = 'Male' THEN 1 ELSE 0 END) AS `male_residents`,
  SUM(CASE WHEN r.`sex` = 'Female' THEN 1 ELSE 0 END) AS `female_residents`,
  SUM(CASE WHEN r.`head_of_family` = 1 THEN 1 ELSE 0 END) AS `household_heads`
FROM `vw_resident_area_current` v
INNER JOIN `residentinformationtbl` r
  ON r.`resident_id` = v.`resident_id`
LEFT JOIN `statuslookuptbl` s
  ON s.`status_id` = r.`status_id_resident`
GROUP BY COALESCE(NULLIF(TRIM(v.`area_number`), ''), 'Unspecified Area');

CREATE OR REPLACE VIEW `vw_area_case_summary` AS
SELECT
  COALESCE(NULLIF(TRIM(cp.`area_number`), ''), 'Unspecified Area') AS `area_name`,
  c.`report_type`,
  COUNT(DISTINCT c.`case_id`) AS `total_cases`
FROM `casereportstbl` c
INNER JOIN `caseparticipantstbl` cp
  ON cp.`case_id` = c.`case_id`
  AND cp.`participant_role` = 'Complainant'
GROUP BY
  COALESCE(NULLIF(TRIM(cp.`area_number`), ''), 'Unspecified Area'),
  c.`report_type`;

COMMIT;
