-- Store captured handwritten signatures as file-backed assets linked to a case.
-- The application should save the canvas output to disk and persist only the
-- relative file path plus audit metadata in this table.

CREATE TABLE IF NOT EXISTS `casesignaturestbl` (
  `signature_id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` varchar(12) NOT NULL,
  `signature_role` enum('Complainant','Respondent','Witness','Officer','Other') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL DEFAULT 'image/png',
  `captured_by_user_id` varchar(12) DEFAULT NULL,
  `captured_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`signature_id`),
  UNIQUE KEY `uq_case_signature_role` (`case_id`, `signature_role`),
  KEY `idx_casesignature_case` (`case_id`),
  KEY `idx_casesignature_role` (`signature_role`),
  KEY `idx_casesignature_captured_by` (`captured_by_user_id`),
  CONSTRAINT `fk_casesignature_case`
    FOREIGN KEY (`case_id`) REFERENCES `casereportstbl` (`case_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_casesignature_captured_by`
    FOREIGN KEY (`captured_by_user_id`) REFERENCES `useraccountstbl` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Recommended file storage pattern:
--   UnifiedFileAttachment/CaseSignatures/<case_id>/complainant_<timestamp>.png
--   UnifiedFileAttachment/CaseSignatures/<case_id>/respondent_<timestamp>.png
--
-- Design notes:
-- 1. Signatures are stored separately from case header text fields to avoid
--    bloating casereportstbl with base64 canvas data.
-- 2. `signature_role` allows one active signature per role per case.
-- 3. This table is case-centric by design so it can support blotter first
--    without coupling tightly to any one child table.
