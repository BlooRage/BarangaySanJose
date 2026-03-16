-- Create a review-layer table for complaint-to-blotter endorsements.
-- A complaint endorsement should create a blotter request first; only an
-- approved request should create the actual blotter case and child record.

-- Seed status lookup values for blotter review requests.
INSERT INTO statuslookuptbl (status_name, status_type)
SELECT 'Pending', 'BlotterRequest'
WHERE NOT EXISTS (
    SELECT 1
    FROM statuslookuptbl
    WHERE status_name = 'Pending' AND status_type = 'BlotterRequest'
);

INSERT INTO statuslookuptbl (status_name, status_type)
SELECT 'Approved', 'BlotterRequest'
WHERE NOT EXISTS (
    SELECT 1
    FROM statuslookuptbl
    WHERE status_name = 'Approved' AND status_type = 'BlotterRequest'
);

INSERT INTO statuslookuptbl (status_name, status_type)
SELECT 'Rejected', 'BlotterRequest'
WHERE NOT EXISTS (
    SELECT 1
    FROM statuslookuptbl
    WHERE status_name = 'Rejected' AND status_type = 'BlotterRequest'
);

CREATE TABLE IF NOT EXISTS `blotterrequeststbl` (
  `request_id` varchar(12) NOT NULL,
  `complaint_case_id` varchar(12) NOT NULL,
  `complaint_id` varchar(10) NOT NULL,
  `request_status_id` int(11) NOT NULL,
  `review_notes` text DEFAULT NULL,
  `recommended_by_user_id` varchar(12) DEFAULT NULL,
  `reviewed_by_user_id` varchar(12) DEFAULT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `approved_blotter_case_id` varchar(12) DEFAULT NULL,
  `approved_blotter_id` varchar(10) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `uq_blotterrequest_complaint_case` (`complaint_case_id`),
  UNIQUE KEY `uq_blotterrequest_complaint_id` (`complaint_id`),
  UNIQUE KEY `uq_blotterrequest_approved_case` (`approved_blotter_case_id`),
  UNIQUE KEY `uq_blotterrequest_approved_blotter` (`approved_blotter_id`),
  KEY `idx_blotterrequest_status` (`request_status_id`),
  KEY `idx_blotterrequest_recommended_by` (`recommended_by_user_id`),
  KEY `idx_blotterrequest_reviewed_by` (`reviewed_by_user_id`),
  CONSTRAINT `fk_blotterrequest_complaint_case`
    FOREIGN KEY (`complaint_case_id`) REFERENCES `casereportstbl` (`case_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_blotterrequest_complaint`
    FOREIGN KEY (`complaint_id`) REFERENCES `complaintstbl` (`complaint_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_blotterrequest_status`
    FOREIGN KEY (`request_status_id`) REFERENCES `statuslookuptbl` (`status_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_blotterrequest_recommended_by`
    FOREIGN KEY (`recommended_by_user_id`) REFERENCES `useraccountstbl` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_blotterrequest_reviewed_by`
    FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `useraccountstbl` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_blotterrequest_approved_case`
    FOREIGN KEY (`approved_blotter_case_id`) REFERENCES `casereportstbl` (`case_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_blotterrequest_approved_blotter`
    FOREIGN KEY (`approved_blotter_id`) REFERENCES `barangayblottertbl` (`blotter_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Design notes:
-- 1. `request_status_id` must point to statuslookuptbl entries with
--    status_type = 'BlotterRequest'.
-- 2. `review_notes` serves as the single remarks field for screening,
--    approval context, and rejection reasons.
-- 3. Applications should require non-empty `review_notes` when a request is
--    marked as Rejected.
-- 4. One complaint should only have one active blotter request record unless
--    business rules later expand to support resubmission/versioning.
