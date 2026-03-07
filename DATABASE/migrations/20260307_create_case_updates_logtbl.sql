CREATE TABLE IF NOT EXISTS `caseupdateslogtbl` (
  `case_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `case_id` bigint(20) unsigned NOT NULL,
  `log_entry` text NOT NULL,
  `logged_by_user_id` varchar(12) DEFAULT NULL,
  `logged_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`case_log_id`),
  KEY `idx_caseupdateslog_case` (`case_id`),
  KEY `idx_caseupdateslog_logged_by` (`logged_by_user_id`),
  CONSTRAINT `fk_caseupdateslog_case`
    FOREIGN KEY (`case_id`) REFERENCES `casereportstbl` (`case_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_caseupdateslog_logged_by`
    FOREIGN KEY (`logged_by_user_id`) REFERENCES `useraccountstbl` (`user_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
