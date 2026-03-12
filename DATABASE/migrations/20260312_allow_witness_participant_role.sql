-- Ensure caseparticipantstbl.participant_role accepts Witness.
--
-- Some live databases appear to still restrict participant_role to only
-- Complainant/Respondent, which causes Witness inserts to fail.
-- Converting this column to VARCHAR keeps existing values and avoids future
-- enum mismatches for case participant roles.

ALTER TABLE caseparticipantstbl
    MODIFY COLUMN participant_role VARCHAR(50) NOT NULL;
