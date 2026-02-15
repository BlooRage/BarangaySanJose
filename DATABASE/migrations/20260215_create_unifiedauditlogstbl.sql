-- Unified audit logs table
-- Stores a normalized audit trail for sensitive actions (admin/employee modules).

CREATE TABLE IF NOT EXISTS unifiedauditlogstbl (
  audit_id INT NOT NULL AUTO_INCREMENT,
  user_id VARCHAR(12),
  role_access VARCHAR(64) NOT NULL,
  module_affected VARCHAR(128) NOT NULL,
  target_type VARCHAR(64) NOT NULL,
  target_id VARCHAR(64) NOT NULL,
  action_type VARCHAR(64) NOT NULL,
  field_changed VARCHAR(128),
  old_value TEXT,
  new_value TEXT,
  remarks TEXT,
  action_timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status_id_audit INT,
  PRIMARY KEY (audit_id),
  KEY idx_audit_ts (action_timestamp),
  KEY idx_audit_user (user_id),
  KEY idx_audit_module (module_affected),
  KEY idx_audit_target (target_type, target_id),
  KEY idx_audit_action (action_type),
  KEY idx_audit_status (status_id_audit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If you want foreign keys, run these AFTER confirming:
-- - referenced tables are InnoDB
-- - referenced columns match type/length/collation exactly
--
-- ALTER TABLE unifiedauditlogstbl
--   ADD CONSTRAINT fk_audit_user
--     FOREIGN KEY (user_id) REFERENCES useraccountstbl(user_id)
--     ON UPDATE CASCADE
--     ON DELETE SET NULL;
--
-- ALTER TABLE unifiedauditlogstbl
--   ADD CONSTRAINT fk_audit_status
--     FOREIGN KEY (status_id_audit) REFERENCES statuslookuptbl(status_id)
--     ON UPDATE CASCADE
--     ON DELETE SET NULL;
