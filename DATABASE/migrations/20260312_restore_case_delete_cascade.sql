-- Restore ON DELETE CASCADE for every live foreign key that references
-- casereportstbl.case_id.
--
-- Use this after the ID-format migration if deleting a case report no longer
-- cascades to child tables such as complaintstbl, barangayblottertbl,
-- caseparticipantstbl, caseupdateslogtbl, or casestatushistorytbl.
--
-- This script only touches foreign keys that reference casereportstbl.case_id.
-- It does not modify complaintstbl.blotter_id -> barangayblottertbl.blotter_id,
-- so any intended SET NULL behavior on blotter linkage remains unchanged.

SET FOREIGN_KEY_CHECKS = 0;

DROP TEMPORARY TABLE IF EXISTS tmp_case_delete_fks;
CREATE TEMPORARY TABLE tmp_case_delete_fks AS
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
  AND k.REFERENCED_TABLE_NAME = 'casereportstbl'
  AND k.REFERENCED_COLUMN_NAME = 'case_id';

DROP PROCEDURE IF EXISTS restore_drop_case_delete_fks;
DELIMITER $$
CREATE PROCEDURE restore_drop_case_delete_fks()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table_name VARCHAR(64);
    DECLARE v_constraint_name VARCHAR(64);

    DECLARE cur CURSOR FOR
        SELECT TABLE_NAME, CONSTRAINT_NAME
        FROM tmp_case_delete_fks;

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

CALL restore_drop_case_delete_fks();
DROP PROCEDURE IF EXISTS restore_drop_case_delete_fks;

DROP PROCEDURE IF EXISTS restore_add_case_delete_fks;
DELIMITER $$
CREATE PROCEDURE restore_add_case_delete_fks()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table_name VARCHAR(64);
    DECLARE v_column_name VARCHAR(64);
    DECLARE v_constraint_name VARCHAR(64);
    DECLARE v_ref_table_name VARCHAR(64);
    DECLARE v_ref_column_name VARCHAR(64);
    DECLARE v_update_rule VARCHAR(30);

    DECLARE cur CURSOR FOR
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            UPDATE_RULE
        FROM tmp_case_delete_fks;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;
    add_loop: LOOP
        FETCH cur INTO
            v_table_name,
            v_column_name,
            v_constraint_name,
            v_ref_table_name,
            v_ref_column_name,
            v_update_rule;
        IF done = 1 THEN
            LEAVE add_loop;
        END IF;

        SET @sql = CONCAT(
            'ALTER TABLE `', v_table_name,
            '` ADD CONSTRAINT `', v_constraint_name,
            '` FOREIGN KEY (`', v_column_name, '`) REFERENCES `',
            v_ref_table_name, '` (`', v_ref_column_name,
            '`) ON DELETE CASCADE ON UPDATE ', v_update_rule
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL restore_add_case_delete_fks();
DROP PROCEDURE IF EXISTS restore_add_case_delete_fks;

DROP TEMPORARY TABLE IF EXISTS tmp_case_delete_fks;

SET FOREIGN_KEY_CHECKS = 1;
