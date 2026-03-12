CREATE TABLE IF NOT EXISTS `complaintstbl` (
  `complaint_id` varchar(10) NOT NULL,
  `case_id` bigint(20) unsigned NOT NULL,
  `complaint_origin` enum('ResidentPortal','AdminEncoded','WalkIn','Referral') NOT NULL DEFAULT 'ResidentPortal',
  `subject_kind` enum('Resident','NonResident','Business','Organization','Unknown','GeneralConcern') NOT NULL DEFAULT 'Unknown',
  `subject_display_name` varchar(255) NOT NULL,
  `subject_contact_number` varchar(20) DEFAULT NULL,
  `subject_address` varchar(255) DEFAULT NULL,
  `witness_summary` text DEFAULT NULL,
  `intake_notes` text DEFAULT NULL,
  `screening_notes` text DEFAULT NULL,
  `escalated_to_blotter` tinyint(1) NOT NULL DEFAULT 0,
  `blotter_id` varchar(10) DEFAULT NULL,
  `escalated_to_blotter_at` datetime DEFAULT NULL,
  `escalated_by_user_id` varchar(12) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`complaint_id`),
  UNIQUE KEY `uq_complaint_case` (`case_id`),
  UNIQUE KEY `uq_complaint_blotter` (`blotter_id`),
  KEY `idx_complaint_origin` (`complaint_origin`),
  KEY `idx_complaint_subject_kind` (`subject_kind`),
  KEY `idx_complaint_escalated` (`escalated_to_blotter`),
  KEY `idx_complaint_blotter` (`blotter_id`),
  KEY `idx_complaint_escalated_by` (`escalated_by_user_id`),
  CONSTRAINT `fk_complaint_case`
    FOREIGN KEY (`case_id`) REFERENCES `casereportstbl` (`case_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_complaint_blotter`
    FOREIGN KEY (`blotter_id`) REFERENCES `barangayblottertbl` (`blotter_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_complaint_escalated_by`
    FOREIGN KEY (`escalated_by_user_id`) REFERENCES `useraccountstbl` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Design notes:
-- 1. casereportstbl remains the shared case header for both complaints and blotter records.
-- 2. complaintstbl stores complaint-only intake metadata and keeps a 1:1 relationship with casereportstbl.
-- 3. caseparticipantstbl should continue to store complainant/respondent/witness rows.
-- 4. barangayblottertbl remains the blotter-specific child record when a complaint is escalated.
-- 5. Lifecycle is one-way only: Complaint -> Blotter. A complaint may escalate into a blotter, but a blotter must not revert into a complaint.
-- 6. report_type in casereportstbl should preserve the case origin as 'Complaint'; escalation is represented by complaintstbl.escalated_to_blotter plus the linked blotter_id.
-- 7. Application logic should only create barangayblottertbl rows from complaint-origin cases and must not provide any downgrade path once blotter_id is assigned.
