-- Unified Document Request + Transaction schema (non-_v2 names).
-- Safe to run multiple times (uses IF NOT EXISTS where possible).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS documentrequesttbl (
  request_id VARCHAR(16) NOT NULL,
  resident_user_id VARCHAR(12) DEFAULT NULL,
  resident_name VARCHAR(191) DEFAULT NULL,
  attachment_id BIGINT(20) UNSIGNED DEFAULT NULL,
  request_details LONGTEXT DEFAULT NULL,
  status_id INT(11) DEFAULT NULL,
  user_id_official_reviewed_by VARCHAR(12) DEFAULT NULL,
  user_id_official_released_by VARCHAR(12) DEFAULT NULL,
  request_timestamp DATETIME DEFAULT NULL,
  review_timestamp DATETIME DEFAULT NULL,
  release_timestamp DATETIME DEFAULT NULL,
  document_validity DATETIME DEFAULT NULL,
  qr_code_path VARCHAR(255) DEFAULT NULL,
  resident_id VARCHAR(12) DEFAULT NULL,
  document_type VARCHAR(120) DEFAULT NULL,
  document_type_id INT(11) DEFAULT NULL,
  purpose VARCHAR(255) DEFAULT NULL,
  status_remarks TEXT DEFAULT NULL,
  payment_method VARCHAR(40) DEFAULT NULL,
  payment_proof_path VARCHAR(255) DEFAULT NULL,
  payment_submitted_at DATETIME DEFAULT NULL,
  amount DECIMAL(12,2) DEFAULT NULL,
  or_number VARCHAR(80) DEFAULT NULL,
  certificate_number VARCHAR(80) DEFAULT NULL,
  verification_code VARCHAR(80) DEFAULT NULL,
  issued_file_path VARCHAR(255) DEFAULT NULL,
  submitted_at DATETIME DEFAULT NULL,
  personnel_user_id VARCHAR(12) DEFAULT NULL,
  personnel_decision_at DATETIME DEFAULT NULL,
  finance_user_id VARCHAR(12) DEFAULT NULL,
  finance_decision_at DATETIME DEFAULT NULL,
  ready_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (request_id),
  KEY idx_docreq_resident_user (resident_user_id),
  KEY idx_docreq_document_type_id (document_type_id),
  KEY idx_docreq_status_id (status_id),
  KEY idx_docreq_timestamp (request_timestamp),
  KEY idx_docreq_status (status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS barangayidrequesttbl (
  barangay_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id VARCHAR(16) NOT NULL,
  id_details LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (barangay_id),
  UNIQUE KEY uq_barangayid_request (request_id),
  CONSTRAINT fk_barangayid_request FOREIGN KEY (request_id)
    REFERENCES documentrequesttbl(request_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS issuancerequesttbl (
  certificate_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id VARCHAR(16) NOT NULL,
  certificate_type VARCHAR(120) NOT NULL,
  certificate_details LONGTEXT DEFAULT NULL,
  certificate_number VARCHAR(64) DEFAULT NULL,
  verification_code VARCHAR(80) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (certificate_id),
  UNIQUE KEY uq_issuancereq_request (request_id),
  KEY idx_issuancereq_type (certificate_type),
  CONSTRAINT fk_issuancereq_request FOREIGN KEY (request_id)
    REFERENCES documentrequesttbl(request_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS certificatesrequesttbl (
  certificate_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id VARCHAR(16) NOT NULL,
  certificate_type VARCHAR(120) NOT NULL,
  certificate_details LONGTEXT DEFAULT NULL,
  certificate_number VARCHAR(64) DEFAULT NULL,
  verification_code VARCHAR(80) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (certificate_id),
  UNIQUE KEY uq_certificatesreq_request (request_id),
  KEY idx_certificatesreq_type (certificate_type),
  CONSTRAINT fk_certificatesreq_request FOREIGN KEY (request_id)
    REFERENCES documentrequesttbl(request_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS clearancerequesttbl (
  clearance_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id VARCHAR(16) NOT NULL,
  clearance_type VARCHAR(120) DEFAULT NULL,
  application_type VARCHAR(120) DEFAULT NULL,
  clearance_details LONGTEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clearance_id),
  UNIQUE KEY uq_clearancereq_request (request_id),
  CONSTRAINT fk_clearancereq_request FOREIGN KEY (request_id)
    REFERENCES documentrequesttbl(request_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS clearanceinspectiontbl (
  inspection_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  clearance_id BIGINT(20) UNSIGNED NOT NULL,
  inspector_name VARCHAR(191) DEFAULT NULL,
  date_inspected DATETIME DEFAULT NULL,
  remarks TEXT DEFAULT NULL,
  inspector_signature_path VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (inspection_id),
  KEY idx_inspection_clearance (clearance_id),
  CONSTRAINT fk_inspection_clearance FOREIGN KEY (clearance_id)
    REFERENCES clearancerequesttbl(clearance_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS clearancefeestbl (
  clearance_fee_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  clearance_id BIGINT(20) UNSIGNED NOT NULL,
  fee_type VARCHAR(120) NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (clearance_fee_id),
  KEY idx_clearancefee_clearance (clearance_id),
  CONSTRAINT fk_clearancefee_clearance FOREIGN KEY (clearance_id)
    REFERENCES clearancerequesttbl(clearance_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS financetransactiontbl (
  transaction_id VARCHAR(10) NOT NULL,
  request_id VARCHAR(16) NOT NULL,
  transaction_amount DECIMAL(12,2) DEFAULT NULL,
  applicant_lastname VARCHAR(120) DEFAULT NULL,
  applicant_firstname VARCHAR(120) DEFAULT NULL,
  applicant_middleInitial VARCHAR(10) DEFAULT NULL,
  payment_method VARCHAR(40) DEFAULT NULL,
  transaction_details LONGTEXT DEFAULT NULL,
  or_number VARCHAR(80) DEFAULT NULL,
  transaction_status_id INT(11) DEFAULT NULL,
  payment_deadline DATETIME DEFAULT NULL,
  payment_timestamp DATETIME DEFAULT NULL,
  user_id_employee_process VARCHAR(12) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (transaction_id),
  UNIQUE KEY uq_transaction_request (request_id),
  UNIQUE KEY uq_transaction_or_number (or_number),
  KEY idx_transaction_status (transaction_status_id),
  KEY idx_transaction_employee (user_id_employee_process),
  CONSTRAINT fk_transaction_request FOREIGN KEY (request_id)
    REFERENCES documentrequesttbl(request_id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
