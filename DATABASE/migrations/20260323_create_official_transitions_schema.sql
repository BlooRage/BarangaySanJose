SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Step 1: Extend officialinformationtbl with transition columns
-- ============================================================

ALTER TABLE `officialinformationtbl`
  ADD COLUMN IF NOT EXISTS `selection_method`       VARCHAR(20)   DEFAULT NULL AFTER `department`,
  ADD COLUMN IF NOT EXISTS `term_start`             DATE          DEFAULT NULL AFTER `selection_method`,
  ADD COLUMN IF NOT EXISTS `term_end`               DATE          DEFAULT NULL AFTER `term_start`,
  ADD COLUMN IF NOT EXISTS `acting_for_id`          VARCHAR(20)   DEFAULT NULL AFTER `term_end`,
  ADD COLUMN IF NOT EXISTS `acting_until_date`      DATE          DEFAULT NULL AFTER `acting_for_id`,
  ADD COLUMN IF NOT EXISTS `transition_out_type`    VARCHAR(50)   DEFAULT NULL AFTER `acting_until_date`,
  ADD COLUMN IF NOT EXISTS `transition_out_date`    DATE          DEFAULT NULL AFTER `transition_out_type`,
  ADD COLUMN IF NOT EXISTS `transition_out_reason`  TEXT          DEFAULT NULL AFTER `transition_out_date`,
  ADD COLUMN IF NOT EXISTS `batch_label`            VARCHAR(200)  DEFAULT NULL AFTER `transition_out_reason`,
  ADD COLUMN IF NOT EXISTS `can_return`             TINYINT(1)    NOT NULL DEFAULT 1 AFTER `batch_label`;

-- ============================================================
-- Step 2: officialtransitionstbl
-- One row per position transition event.
-- Absorbs: batch grouping, election schedule, acting info,
-- and all notification/action flags in one place.
-- ============================================================

CREATE TABLE IF NOT EXISTS `officialtransitionstbl` (
  `transition_id`           VARCHAR(25)   NOT NULL,
  `batch_label`             VARCHAR(200)  DEFAULT NULL,
  `proclamation_date`       DATE          DEFAULT NULL,
  `next_election_date`      DATE          DEFAULT NULL,
  `election_date`           DATE          DEFAULT NULL,

  -- Notification / action flags
  `notify_3mo_sent`         TINYINT(1)    NOT NULL DEFAULT 0,
  `notify_3mo_sent_at`      TIMESTAMP     NULL DEFAULT NULL,
  `notify_1mo_sent`         TINYINT(1)    NOT NULL DEFAULT 0,
  `notify_1mo_sent_at`      TIMESTAMP     NULL DEFAULT NULL,
  `deactivated_7d_before`   TINYINT(1)    NOT NULL DEFAULT 0,
  `deactivated_7d_before_at` TIMESTAMP    NULL DEFAULT NULL,
  `notify_post_sent`        TINYINT(1)    NOT NULL DEFAULT 0,
  `notify_post_sent_at`     TIMESTAMP     NULL DEFAULT NULL,

  -- What kind of transition this is
  `transition_type`         VARCHAR(50)   NOT NULL,
  `position`                VARCHAR(150)  DEFAULT NULL,
  `department`              VARCHAR(100)  DEFAULT NULL,
  `area_number`             VARCHAR(50)   DEFAULT NULL,

  -- Who is involved
  `outgoing_official_id`    VARCHAR(20)   DEFAULT NULL,
  `incoming_official_id`    VARCHAR(20)   DEFAULT NULL,
  `selected_candidate_id`   INT           DEFAULT NULL,

  -- What was decided / outcome
  `outcome`                 VARCHAR(50)   DEFAULT NULL,
  `status`                  VARCHAR(30)   NOT NULL DEFAULT 'Open',

  -- Acting / temporary replacement
  `is_acting`               TINYINT(1)    NOT NULL DEFAULT 0,
  `acting_until_date`       DATE          DEFAULT NULL,

  -- Timing and reason
  `effective_date`          DATE          DEFAULT NULL,
  `reason`                  TEXT          DEFAULT NULL,

  -- Metadata
  `created_by`              VARCHAR(20)   NOT NULL,
  `created_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at`              TIMESTAMP     NULL DEFAULT NULL,
  `completed_at`            TIMESTAMP     NULL DEFAULT NULL,
  `updated_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`transition_id`),
  KEY `idx_transitions_batch`       (`batch_label`(100)),
  KEY `idx_transitions_election`    (`election_date`),
  KEY `idx_transitions_status`      (`status`),
  KEY `idx_transitions_type`        (`transition_type`),
  KEY `idx_transitions_outgoing`    (`outgoing_official_id`),
  KEY `idx_transitions_incoming`    (`incoming_official_id`),
  KEY `idx_transitions_created`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Step 3: upcomingofficialstbl
-- One row per candidate per transition.
-- This is the "Upcoming Officials" pool — candidates being
-- evaluated and winners waiting to be onboarded.
-- ============================================================

CREATE TABLE IF NOT EXISTS `upcomingofficialstbl` (
  `upcoming_id`             INT           NOT NULL AUTO_INCREMENT,
  `transition_id`           VARCHAR(25)   NOT NULL,

  -- Candidate classification
  `candidate_type`          VARCHAR(30)   NOT NULL DEFAULT 'New',

  -- Candidate identity
  `candidate_name`          VARCHAR(200)  NOT NULL,
  `candidate_contact`       VARCHAR(100)  DEFAULT NULL,

  -- Optional links to existing records
  `linked_official_id`      VARCHAR(20)   DEFAULT NULL,
  `linked_resident_id`      VARCHAR(20)   DEFAULT NULL,
  `linked_invite_id`        INT           DEFAULT NULL,

  -- Selection
  `is_selected`             TINYINT(1)    NOT NULL DEFAULT 0,
  `notes`                   TEXT          DEFAULT NULL,

  -- Metadata
  `encoded_by`              VARCHAR(20)   NOT NULL,
  `encoded_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`upcoming_id`),
  KEY `idx_upcoming_transition`     (`transition_id`),
  KEY `idx_upcoming_official`       (`linked_official_id`),
  KEY `idx_upcoming_resident`       (`linked_resident_id`),
  KEY `idx_upcoming_selected`       (`is_selected`),
  KEY `idx_upcoming_type`           (`candidate_type`),

  CONSTRAINT `fk_upcoming_transition`
    FOREIGN KEY (`transition_id`) REFERENCES `officialtransitionstbl` (`transition_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Step 4: seat-based module access templates
-- One template per council seat so future office holders
-- inherit the correct module access immediately.
-- ============================================================

CREATE TABLE IF NOT EXISTS `officialseatmodulepermissionstbl` (
  `seat_permission_id`      INT           NOT NULL AUTO_INCREMENT,
  `council_id`              INT           NOT NULL,
  `permission_key`          VARCHAR(120)  NOT NULL,
  `is_allowed`              TINYINT(1)    NOT NULL DEFAULT 1,
  `granted_by_user_id`      VARCHAR(20)   DEFAULT NULL,
  `created_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`seat_permission_id`),
  UNIQUE KEY `uniq_seat_permission`      (`council_id`, `permission_key`),
  KEY `idx_seat_permission_key`          (`permission_key`),
  KEY `idx_seat_permission_allowed`      (`is_allowed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `officialseataccessprofiletbl` (
  `seat_access_profile_id`  INT           NOT NULL AUTO_INCREMENT,
  `council_id`              INT           NOT NULL,
  `permissions_initialized` TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`seat_access_profile_id`),
  UNIQUE KEY `uniq_seat_access_profile_council` (`council_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Design Notes
-- ============================================================
--
-- officialinformationtbl extensions:
--   selection_method  — 'Elected' or 'Appointed'; drives which transition flow the UI uses.
--   term_start/end    — date range of the current term.
--   acting_for_id     — self-FK; set when this official is a temporary acting replacement.
--   acting_until_date — expected end of acting period.
--   transition_out_*  — snapshot of the last transition-out event (type, date, reason).
--   batch_label       — which election batch last affected this official.
--   can_return        — 0 when the official was Removed (disciplinary); prevents them from
--                       appearing in the candidate pool for future elections.
--
-- officialtransitionstbl:
--   One row = one position-level transition event.
--   batch_label + next_election_date group related transitions into a batch; no separate batch
--   table is needed — the cron script deduplicates by (batch_label, election_date).
--   transition_type values: BarangayElection | SKElection | Appointment | Reappointment |
--                           Resignation | Removal | Retirement.
--   status values:          Open | CandidateEncoding | PendingDecision | Decided |
--                           Completed | Cancelled.
--   outcome values:         ReElected | NewPerson | Reactivated | PositionChange |
--                           ActingReplacement | NoSuccessor.
--
-- upcomingofficialstbl:
--   candidate_type values: New | ReturningOfficial | ActiveOfficial | Resident.
--   linked_invite_id is set when a winner is onboarded via the existing invite flow;
--   the invite completion callback should set officialtransitionstbl.incoming_official_id
--   and mark the transition Completed automatically.
--   linked_official_id references officialinformationtbl for ReturningOfficial /
--   ActiveOfficial candidates, enabling account reactivation without duplicate creation.
--
-- officialseatmodulepermissionstbl + officialseataccessprofiletbl:
--   Stores one reusable access template per council seat. When a transition is completed,
--   the saved template is copied to the new or returning official so dashboard access
--   matches the seat immediately.
