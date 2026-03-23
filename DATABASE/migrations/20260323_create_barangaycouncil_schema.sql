SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- barangaycounciltbl
-- Canonical list of barangay council seats.
-- One row = one position/seat (fixed structure of the council).
-- current_official_id tracks who holds that seat right now.
-- The Official Transition Module reads from this table —
-- only council seats are subject to the transition workflow.
-- ============================================================

CREATE TABLE IF NOT EXISTS `barangaycounciltbl` (
  `council_id`            INT           NOT NULL AUTO_INCREMENT,
  `seat_name`             VARCHAR(150)  NOT NULL,
  `selection_method`      VARCHAR(20)   NOT NULL DEFAULT 'Elected',
  `seat_group`            VARCHAR(50)   NOT NULL DEFAULT 'Sangguniang Barangay',
  `sort_order`            INT           NOT NULL DEFAULT 0,
  `current_official_id`   VARCHAR(20)   DEFAULT NULL,
  `term_start`            DATE          DEFAULT NULL,
  `term_end`              DATE          DEFAULT NULL,
  `is_active`             TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`council_id`),
  KEY `idx_council_official`  (`current_official_id`),
  KEY `idx_council_active`    (`is_active`),
  KEY `idx_council_group`     (`seat_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── Pre-seed standard barangay council seats ──────────────────────────────────
INSERT IGNORE INTO `barangaycounciltbl`
  (`council_id`, `seat_name`, `selection_method`, `seat_group`, `sort_order`) VALUES
  (1,  'Punong Barangay (Barangay Captain)', 'Elected',   'Sangguniang Barangay', 1),
  (2,  'Kagawad Seat 1',                     'Elected',   'Sangguniang Barangay', 2),
  (3,  'Kagawad Seat 2',                     'Elected',   'Sangguniang Barangay', 3),
  (4,  'Kagawad Seat 3',                     'Elected',   'Sangguniang Barangay', 4),
  (5,  'Kagawad Seat 4',                     'Elected',   'Sangguniang Barangay', 5),
  (6,  'Kagawad Seat 5',                     'Elected',   'Sangguniang Barangay', 6),
  (7,  'Kagawad Seat 6',                     'Elected',   'Sangguniang Barangay', 7),
  (8,  'Kagawad Seat 7',                     'Elected',   'Sangguniang Barangay', 8),
  (9,  'SK Chairperson',                     'Elected',   'Sangguniang Kabataan', 9),
  (10, 'Barangay Secretary',                 'Appointed', 'Appointed Positions',  10),
  (11, 'Barangay Treasurer',                 'Appointed', 'Appointed Positions',  11),
  (12, 'Lupong Tagapamayapa Member',         'Appointed', 'Lupon',               12),
  (13, 'Barangay Tanod',                     'Appointed', 'Support Roles',        13),
  (14, 'Barangay Health Worker (BHW)',        'Appointed', 'Support Roles',        14),
  (15, 'Day Care Worker',                    'Appointed', 'Support Roles',        15);

-- ── Add council_id to officialtransitionstbl ──────────────────────────────────
-- Links each transition to the specific council seat it concerns.
ALTER TABLE `officialtransitionstbl`
  ADD COLUMN IF NOT EXISTS `council_id` INT DEFAULT NULL AFTER `transition_id`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Design Notes
-- ============================================================
--
-- seat_group groups seats in the UI dropdown:
--   'Sangguniang Barangay'  — elected SB members
--   'Sangguniang Kabataan'  — SK officials (separate election cycle)
--   'Appointed Positions'   — Secretary, Treasurer
--   'Lupon'                 — Lupong Tagapamayapa
--   'Support Roles'         — Tanod, BHW, DCW
--
-- selection_method drives the transition_type options shown in the modal:
--   Elected   → BarangayElection / SKElection / Resignation / Removal / Retirement
--   Appointed → Appointment / Reappointment / Resignation / Removal / Retirement
--
-- current_official_id is updated by the API when a transition completes:
--   - ReElected      → keep same current_official_id, refresh term dates
--   - NewPerson      → set to incoming_official_id once onboarding completes
--   - Reactivated    → set to linked former official's official_id
--   - PositionChange → set to the incoming official's official_id
--   - NoSuccessor    → set to NULL (seat becomes vacant)
--
-- is_active = 0 means the seat has been abolished (no transitions allowed).
-- Admin can add new seats (custom positions) directly in the module.
