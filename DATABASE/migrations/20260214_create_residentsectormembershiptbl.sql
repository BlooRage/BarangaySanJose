-- Creates a normalized table for per-resident sector membership status.
-- This replaces relying on CSV strings for queries and status tracking.

CREATE TABLE IF NOT EXISTS residentsectormembershiptbl (
  id INT NOT NULL AUTO_INCREMENT,
  resident_id VARCHAR(10) NOT NULL,
  sector_key VARCHAR(64) NOT NULL,
  sector_status_id INT NOT NULL,
  latest_attachment_id INT NULL,
  remarks TEXT NULL,
  upload_timestamp DATETIME NULL,
  last_update_user_id VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_resident_sector (resident_id, sector_key),
  KEY idx_resident (resident_id),
  KEY idx_sector (sector_key),
  KEY idx_sector_status (sector_status_id),
  KEY idx_latest_attachment (latest_attachment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
