-- Make documentrequesttbl.resident_user_id use useraccountstbl.user_id as parent key.
-- Fixes FK 1451 when deleting/updating resident rows while document requests exist.

START TRANSACTION;

-- Normalize column type to match useraccountstbl.user_id.
ALTER TABLE documentrequesttbl
  MODIFY resident_user_id VARCHAR(12) NULL;

-- Backfill legacy rows where resident_user_id still stores resident_id.
UPDATE documentrequesttbl dr
INNER JOIN residentinformationtbl r
  ON r.resident_id = dr.resident_user_id
SET dr.resident_user_id = r.user_id;

-- Drop old FK (if present).
SET @drop_fk := (
  SELECT IF(
    EXISTS (
      SELECT 1
      FROM information_schema.TABLE_CONSTRAINTS
      WHERE CONSTRAINT_SCHEMA = DATABASE()
        AND TABLE_NAME = 'documentrequesttbl'
        AND CONSTRAINT_NAME = 'fk_docreq_resident_user'
        AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ),
    'ALTER TABLE documentrequesttbl DROP FOREIGN KEY fk_docreq_resident_user',
    'SELECT 1'
  )
);
PREPARE stmt FROM @drop_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional cleanup for orphan values so FK add will succeed.
UPDATE documentrequesttbl dr
LEFT JOIN useraccountstbl u
  ON u.user_id = dr.resident_user_id
SET dr.resident_user_id = NULL
WHERE dr.resident_user_id IS NOT NULL
  AND u.user_id IS NULL;

-- Recreate FK to useraccountstbl.user_id.
ALTER TABLE documentrequesttbl
  ADD CONSTRAINT fk_docreq_resident_user
  FOREIGN KEY (resident_user_id)
  REFERENCES useraccountstbl(user_id)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

COMMIT;
