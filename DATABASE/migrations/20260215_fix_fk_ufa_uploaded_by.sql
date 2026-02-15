-- Fix for MySQL error 1451 when deleting from useraccountstbl:
-- unifiedfileattachmenttbl.user_id_uploaded_by references useraccountstbl.user_id.
--
-- Option A (recommended): keep FK, but allow deleting users by setting uploaded_by to NULL.
-- Requirements: user_id_uploaded_by must be nullable.

-- 1) Drop the existing FK.
ALTER TABLE unifiedfileattachmenttbl
  DROP FOREIGN KEY fk_ufa_uploaded_by;

-- 2) Allow NULLs.
ALTER TABLE unifiedfileattachmenttbl
  MODIFY user_id_uploaded_by VARCHAR(12) NULL;

-- 3) Re-add FK with ON DELETE SET NULL.
ALTER TABLE unifiedfileattachmenttbl
  ADD CONSTRAINT fk_ufa_uploaded_by
    FOREIGN KEY (user_id_uploaded_by)
    REFERENCES useraccountstbl (user_id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

-- Option B (quick, not recommended): remove the FK entirely (allows orphan values).
-- ALTER TABLE unifiedfileattachmenttbl DROP FOREIGN KEY fk_ufa_uploaded_by;

