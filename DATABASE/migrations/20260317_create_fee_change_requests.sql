-- Fee change request catalog (Admin → Finance request workflow).
-- Safe to run multiple times.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS clearancefeechangerequeststbl (
  fee_change_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_type         ENUM('add_type','edit_price') NOT NULL,
  fee_type_id          BIGINT UNSIGNED DEFAULT NULL,
  proposed_fee_name    VARCHAR(120) NOT NULL,
  proposed_amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  current_amount       DECIMAL(12,2) DEFAULT NULL,
  notes                TEXT DEFAULT NULL,
  status               ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  requested_by_user_id VARCHAR(64) NOT NULL DEFAULT '',
  reviewed_by_user_id  VARCHAR(64) DEFAULT NULL,
  reviewed_at          DATETIME DEFAULT NULL,
  review_notes         TEXT DEFAULT NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (fee_change_id),
  KEY idx_fcr_status    (status),
  KEY idx_fcr_requester (requested_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
