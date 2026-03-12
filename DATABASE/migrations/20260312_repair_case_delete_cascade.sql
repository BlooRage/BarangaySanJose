-- Repair the complaint-to-case foreign key without querying information_schema.
-- complaintstbl currently has no case_id foreign key, so deleting a row from
-- casereportstbl will not cascade into complaintstbl until this is restored.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE complaintstbl
    MODIFY COLUMN case_id VARCHAR(12) NOT NULL;

ALTER TABLE complaintstbl
    ADD CONSTRAINT fk_20260312_complaint_case
        FOREIGN KEY (case_id) REFERENCES casereportstbl(case_id)
        ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE barangayblottertbl
    MODIFY COLUMN case_id VARCHAR(12) NOT NULL;

ALTER TABLE barangayblottertbl
    ADD CONSTRAINT fk_20260312_blotter_case
        FOREIGN KEY (case_id) REFERENCES casereportstbl(case_id)
        ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE caseparticipantstbl
    MODIFY COLUMN case_id VARCHAR(12) NOT NULL;

ALTER TABLE caseparticipantstbl
    ADD CONSTRAINT fk_20260312_participant_case
        FOREIGN KEY (case_id) REFERENCES casereportstbl(case_id)
        ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE caseupdateslogtbl
    MODIFY COLUMN case_id VARCHAR(12) NOT NULL;

ALTER TABLE caseupdateslogtbl
    ADD CONSTRAINT fk_20260312_caseupdateslog_case
        FOREIGN KEY (case_id) REFERENCES casereportstbl(case_id)
        ON DELETE CASCADE ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;
