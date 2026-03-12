-- Fix any remaining case_id / blotter_id child columns that are still numeric
-- after the main ID-format migration.
--
-- This script dynamically finds child foreign keys pointing to:
-- - casereportstbl.case_id
-- - barangayblottertbl.blotter_id
--
-- Then it:
-- 1. drops those foreign keys
-- 2. converts the child columns to VARCHAR
-- 3. restores the same foreign keys

SET FOREIGN_KEY_CHECKS = 0;

DROP TEMPORARY TABLE IF EXISTS tmp_remaining_id_fks;
CREATE TEMPORARY TABLE tmp_remaining_id_fks AS
SELECT
    k.TABLE_NAME,
    k.COLUMN_NAME,
    k.CONSTRAINT_NAME,
    k.REFERENCED_TABLE_NAME,
    k.REFERENCED_COLUMN_NAME,
    rc.UPDATE_RULE,
    rc.DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE k
INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
    ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
   AND rc.TABLE_NAME = k.TABLE_NAME
   AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
WHERE k.CONSTRAINT_SCHEMA = DATABASE()
  AND (
      (k.REFERENCED_TABLE_NAME = 'casereportstbl' AND k.REFERENCED_COLUMN_NAME = 'case_id')
      OR
      (k.REFERENCED_TABLE_NAME = 'barangayblottertbl' AND k.REFERENCED_COLUMN_NAME = 'blotter_id')
  );

DROP PROCEDURE IF EXISTS fix_drop_remaining_id_fks;
DELIMITER $$
CREATE PROCEDURE fix_drop_remaining_id_fks()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table_name VARCHAR(64);
    DECLARE v_constraint_name VARCHAR(64);

    DECLARE cur CURSOR FOR
        SELECT TABLE_NAME, CONSTRAINT_NAME
        FROM tmp_remaining_id_fks;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    drop_loop: LOOP
        FETCH cur INTO v_table_name, v_constraint_name;
        IF done = 1 THEN
            LEAVE drop_loop;
        END IF;

        SET @sql = CONCAT(
            'ALTER TABLE `', v_table_name,
            '` DROP FOREIGN KEY `', v_constraint_name, '`'
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL fix_drop_remaining_id_fks();
DROP PROCEDURE IF EXISTS fix_drop_remaining_id_fks;

DROP PROCEDURE IF EXISTS fix_alter_remaining_id_columns;
DELIMITER $$
CREATE PROCEDURE fix_alter_remaining_id_columns()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table_name VARCHAR(64);
    DECLARE v_column_name VARCHAR(64);
    DECLARE v_ref_table_name VARCHAR(64);

    DECLARE cur CURSOR FOR
        SELECT DISTINCT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
        FROM tmp_remaining_id_fks;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    alter_loop: LOOP
        FETCH cur INTO v_table_name, v_column_name, v_ref_table_name;
        IF done = 1 THEN
            LEAVE alter_loop;
        END IF;

        IF v_ref_table_name = 'casereportstbl' THEN
            SET @sql = CONCAT(
                'ALTER TABLE `', v_table_name,
                '` MODIFY COLUMN `', v_column_name, '` VARCHAR(12) NOT NULL'
            );
        ELSE
            SET @nullable = IF(v_table_name = 'complaintstbl', 'NULL', 'NOT NULL');
            SET @sql = CONCAT(
                'ALTER TABLE `', v_table_name,
                '` MODIFY COLUMN `', v_column_name, '` VARCHAR(10) ', @nullable
            );
        END IF;

        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL fix_alter_remaining_id_columns();
DROP PROCEDURE IF EXISTS fix_alter_remaining_id_columns;

DROP PROCEDURE IF EXISTS fix_restore_remaining_id_fks;
DELIMITER $$
CREATE PROCEDURE fix_restore_remaining_id_fks()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table_name VARCHAR(64);
    DECLARE v_column_name VARCHAR(64);
    DECLARE v_constraint_name VARCHAR(64);
    DECLARE v_ref_table_name VARCHAR(64);
    DECLARE v_ref_column_name VARCHAR(64);
    DECLARE v_update_rule VARCHAR(30);
    DECLARE v_delete_rule VARCHAR(30);

    DECLARE cur CURSOR FOR
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            UPDATE_RULE,
            DELETE_RULE
        FROM tmp_remaining_id_fks;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    restore_loop: LOOP
        FETCH cur INTO
            v_table_name,
            v_column_name,
            v_constraint_name,
            v_ref_table_name,
            v_ref_column_name,
            v_update_rule,
            v_delete_rule;
        IF done = 1 THEN
            LEAVE restore_loop;
        END IF;

        SET @sql = CONCAT(
            'ALTER TABLE `', v_table_name,
            '` ADD CONSTRAINT `', v_constraint_name,
            '` FOREIGN KEY (`', v_column_name, '`) REFERENCES `',
            v_ref_table_name, '` (`', v_ref_column_name, '`) ON DELETE ',
            v_delete_rule, ' ON UPDATE ', v_update_rule
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL fix_restore_remaining_id_fks();
DROP PROCEDURE IF EXISTS fix_restore_remaining_id_fks;

DROP TEMPORARY TABLE IF EXISTS tmp_remaining_id_fks;

SET FOREIGN_KEY_CHECKS = 1;
