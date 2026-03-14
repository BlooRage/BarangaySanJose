SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `appointmentstbl` (
  `appointment_id` VARCHAR(10) NOT NULL,
  `user_id_resident` VARCHAR(12) DEFAULT NULL,
  `name` VARCHAR(191) NOT NULL,
  `contact_number` VARCHAR(20) NOT NULL,
  `subject` VARCHAR(120) NOT NULL,
  `subject_other` VARCHAR(191) DEFAULT NULL,
  `purpose` TEXT NOT NULL,
  `preferred_schedule_timestamp` DATETIME NOT NULL,
  `confirmed_schedule_timestamp` DATETIME DEFAULT NULL,
  `user_id_employee_staff` VARCHAR(12) DEFAULT NULL,
  `user_id_official_assigned` VARCHAR(12) DEFAULT NULL,
  `appointment_status_id` INT(11) DEFAULT NULL,
  `resident_notes` TEXT DEFAULT NULL,
  `appointment_remarks` TEXT DEFAULT NULL,
  `review_timestamp` DATETIME DEFAULT NULL,
  `request_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_update_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`appointment_id`),
  KEY `idx_appointments_resident_user` (`user_id_resident`),
  KEY `idx_appointments_staff_user` (`user_id_employee_staff`),
  KEY `idx_appointments_assigned_official` (`user_id_official_assigned`),
  KEY `idx_appointments_status` (`appointment_status_id`),
  KEY `idx_appointments_preferred_schedule` (`preferred_schedule_timestamp`),
  KEY `idx_appointments_confirmed_schedule` (`confirmed_schedule_timestamp`),
  KEY `idx_appointments_review_timestamp` (`review_timestamp`),
  KEY `idx_appointments_request_timestamp` (`request_timestamp`),
  KEY `idx_appointments_subject` (`subject`),
  CONSTRAINT `fk_appointments_resident_user`
    FOREIGN KEY (`user_id_resident`) REFERENCES `useraccountstbl` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_staff_user`
    FOREIGN KEY (`user_id_employee_staff`) REFERENCES `useraccountstbl` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_assigned_official`
    FOREIGN KEY (`user_id_official_assigned`) REFERENCES `useraccountstbl` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_status`
    FOREIGN KEY (`appointment_status_id`) REFERENCES `statuslookuptbl` (`status_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `appointmentqueuetbl` (
  `queue_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` VARCHAR(10) NOT NULL,
  `queue_number` VARCHAR(32) NOT NULL,
  `queue_status_id` INT(11) DEFAULT NULL,
  `queue_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`queue_id`),
  UNIQUE KEY `uq_appointmentqueue_appointment` (`appointment_id`),
  UNIQUE KEY `uq_appointmentqueue_number` (`queue_number`),
  KEY `idx_appointmentqueue_status` (`queue_status_id`),
  KEY `idx_appointmentqueue_timestamp` (`queue_timestamp`),
  CONSTRAINT `fk_appointmentqueue_appointment`
    FOREIGN KEY (`appointment_id`) REFERENCES `appointmentstbl` (`appointment_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appointmentqueue_status`
    FOREIGN KEY (`queue_status_id`) REFERENCES `statuslookuptbl` (`status_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Design notes:
-- 0. `appointment_id` is a formatted string key intended for values like `AP26000000`.
-- 1. `subject` stores the resident's selected appointment type (e.g. Follow-up Concern, Consultation, Event Coordination, Other).
-- 2. `subject_other` stores the resident-supplied detail only when `subject = 'Other'`.
-- 3. `preferred_schedule_timestamp` is the resident's requested slot; `confirmed_schedule_timestamp` is the final approved schedule after staff review.
-- 4. `user_id_employee_staff` is the employee/personnel who reviewed or processed the request; `user_id_official_assigned` is the official the appointment is ultimately set with.
-- 5. `purpose` remains the longer explanation of the appointment request.
-- 6. `name` and `contact_number` are kept as submission-time snapshots for audit/history, even when `user_id_resident` is present.
-- 7. `resident_notes` is reserved for resident-provided extra context; `appointment_remarks` is intended for staff/internal remarks.
-- 8. `review_timestamp` records when staff approved, denied, or rescheduled the request.
-- 9. `appointmentqueuetbl` is optional workflow data for queue assignment after the appointment request is approved/confirmed.
