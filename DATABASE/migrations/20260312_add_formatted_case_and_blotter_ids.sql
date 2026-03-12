-- Convert the real ID columns directly.
-- This version assumes the case-related records have been cleared first.
--
-- Case ID format:      CS[YY][MM][XXXXXX]  e.g. CS2603000001
-- Complaint ID format: C[YYYY][XXXXX]      e.g. C202600001
-- Blotter ID format:   B[YYYY][XXXXX]      e.g. B202600001
--
-- blotter_number remains the manual physical-record number.
-- This script discovers live foreign keys dynamically, so it also handles
-- tables not present in the repo, such as casestatushistorytbl.

SET FOREIGN_KEY_CHECKS = 0;

DROP TEMPORARY TABLE IF EXISTS tmp_id_fk_constraints;
CREATE TEMPORARY TABLE tmp_id_fk_constraints AS
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

DROP PROCEDURE IF EXISTS migrate_drop_id_fks;
DELIMITER $$
CREATE PROCEDURE migrate_drop_id_fks()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table_name VARCHAR(64);
    DECLARE v_constraint_name VARCHAR(64);

    DECLARE cur CURSOR FOR
        SELECT TABLE_NAME, CONSTRAINT_NAME
        FROM tmp_id_fk_constraints;

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

CALL migrate_drop_id_fks();
DROP PROCEDURE IF EXISTS migrate_drop_id_fks;

ALTER TABLE complaintstbl
    DROP COLUMN IF EXISTS complaint_ref_id;

ALTER TABLE barangayblottertbl
    DROP COLUMN IF EXISTS blotter_ref_id;

DROP TEMPORARY TABLE IF EXISTS tmp_case_id_tables;
CREATE TEMPORARY TABLE tmp_case_id_tables AS
SELECT 'casereportstbl' AS TABLE_NAME, 'case_id' AS COLUMN_NAME
UNION
SELECT DISTINCT TABLE_NAME, COLUMN_NAME
FROM tmp_id_fk_constraints
WHERE REFERENCED_TABLE_NAME = 'casereportstbl'
  AND REFERENCED_COLUMN_NAME = 'case_id';

DROP TEMPORARY TABLE IF EXISTS tmp_blotter_id_tables;
CREATE TEMPORARY TABLE tmp_blotter_id_tables AS
SELECT 'barangayblottertbl' AS TABLE_NAME, 'blotter_id' AS COLUMN_NAME
UNION
SELECT DISTINCT TABLE_NAME, COLUMN_NAME
FROM tmp_id_fk_constraints
WHERE REFERENCED_TABLE_NAME = 'barangayblottertbl'
  AND REFERENCED_COLUMN_NAME = 'blotter_id';

DROP PROCEDURE IF EXISTS migrate_alter_case_id_columns;
DELIMITER $$
CREATE PROCEDURE migrate_alter_case_id_columns()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table_name VARCHAR(64);
    DECLARE v_column_name VARCHAR(64);

    DECLARE cur CURSOR FOR
        SELECT TABLE_NAME, COLUMN_NAME
        FROM tmp_case_id_tables;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    alter_loop: LOOP
        FETCH cur INTO v_table_name, v_column_name;
        IF done = 1 THEN
            LEAVE alter_loop;
        END IF;

        SET @sql = CONCAT(
            'ALTER TABLE `', v_table_name,
            '` MODIFY COLUMN `', v_column_name, '` VARCHAR(12) NOT NULL'
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL migrate_alter_case_id_columns();
DROP PROCEDURE IF EXISTS migrate_alter_case_id_columns;

DROP PROCEDURE IF EXISTS migrate_alter_blotter_id_columns;
DELIMITER $$
CREATE PROCEDURE migrate_alter_blotter_id_columns()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table_name VARCHAR(64);
    DECLARE v_column_name VARCHAR(64);

    DECLARE cur CURSOR FOR
        SELECT TABLE_NAME, COLUMN_NAME
        FROM tmp_blotter_id_tables;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    alter_loop: LOOP
        FETCH cur INTO v_table_name, v_column_name;
        IF done = 1 THEN
            LEAVE alter_loop;
        END IF;

        SET @nullable = IF(v_table_name = 'complaintstbl', 'NULL', 'NOT NULL');
        SET @sql = CONCAT(
            'ALTER TABLE `', v_table_name,
            '` MODIFY COLUMN `', v_column_name, '` VARCHAR(10) ', @nullable
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL migrate_alter_blotter_id_columns();
DROP PROCEDURE IF EXISTS migrate_alter_blotter_id_columns;

ALTER TABLE complaintstbl
    MODIFY COLUMN complaint_id VARCHAR(10) NOT NULL;

DROP PROCEDURE IF EXISTS migrate_restore_id_fks;
DELIMITER $$
CREATE PROCEDURE migrate_restore_id_fks()
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
        FROM tmp_id_fk_constraints;

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

CALL migrate_restore_id_fks();
DROP PROCEDURE IF EXISTS migrate_restore_id_fks;

DROP TEMPORARY TABLE IF EXISTS tmp_case_id_tables;
DROP TEMPORARY TABLE IF EXISTS tmp_blotter_id_tables;
DROP TEMPORARY TABLE IF EXISTS tmp_id_fk_constraints;

SET FOREIGN_KEY_CHECKS = 1;
