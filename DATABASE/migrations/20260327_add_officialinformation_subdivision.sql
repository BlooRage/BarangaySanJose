ALTER TABLE `officialinformationtbl`
  ADD COLUMN IF NOT EXISTS `subdivision` VARCHAR(150) NULL AFTER `street_name`;
