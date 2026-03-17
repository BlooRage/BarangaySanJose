-- Clearance fee type catalog (Finance-managed).
-- Safe to run multiple times.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS clearancefeetypetbl (
  fee_type_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  fee_name VARCHAR(120) NOT NULL,
  default_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (fee_type_id),
  UNIQUE KEY uq_clearancefeetypes_name (fee_name),
  KEY idx_clearancefeetypes_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed common fee types if table is empty
INSERT IGNORE INTO clearancefeetypetbl (fee_name, default_amount) VALUES
  ('Application Fee', 100.00),
  ('Inspection Fee', 200.00),
  ('Processing Fee', 50.00),
  ('Clearance Fee', 150.00);
