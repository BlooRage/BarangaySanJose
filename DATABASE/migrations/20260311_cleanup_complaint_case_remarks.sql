-- Remove screening-notes text that was previously appended into
-- casereportstbl.case_remarks for complaint records.
--
-- Safe scope:
-- - complaints only
-- - only when case_remarks ends with screening_notes
-- - only when the screening_notes suffix is preceded by a newline
--
-- This preserves the original non-screening-note case remarks such as:
-- - "Complaint submitted via resident portal."
-- - "Complaint encoded by admin."

UPDATE casereportstbl c
INNER JOIN complaintstbl ct
    ON ct.case_id = c.case_id
SET c.case_remarks = TRIM(TRAILING '\n' FROM LEFT(
    c.case_remarks,
    CHAR_LENGTH(c.case_remarks) - CHAR_LENGTH(ct.screening_notes)
))
WHERE c.report_type = 'Complaint'
  AND c.case_remarks IS NOT NULL
  AND c.case_remarks <> ''
  AND ct.screening_notes IS NOT NULL
  AND ct.screening_notes <> ''
  AND CHAR_LENGTH(c.case_remarks) > CHAR_LENGTH(ct.screening_notes)
  AND RIGHT(c.case_remarks, CHAR_LENGTH(ct.screening_notes)) = ct.screening_notes
  AND SUBSTRING(
        c.case_remarks,
        CHAR_LENGTH(c.case_remarks) - CHAR_LENGTH(ct.screening_notes),
        1
      ) = '\n';
