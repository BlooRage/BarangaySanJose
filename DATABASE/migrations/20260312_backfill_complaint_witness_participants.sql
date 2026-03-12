-- Backfill old complaint witness_summary values into caseparticipantstbl.
-- Only inserts a Witness row when:
-- - the complaint has a non-empty witness_summary
-- - the case does not already have a Witness participant row

INSERT INTO caseparticipantstbl (
    case_id,
    participant_role,
    lastname,
    firstname,
    middlename,
    suffix,
    contact_number,
    email,
    address,
    age,
    sex,
    remarks
)
SELECT
    ct.case_id,
    'Witness' AS participant_role,
    NULLIF(
        TRIM(
            SUBSTRING_INDEX(
                SUBSTRING_INDEX(ct.witness_summary, ' | ', 1),
                'Name:',
                -1
            )
        ),
        ''
    ) AS lastname,
    NULL AS firstname,
    NULL AS middlename,
    NULL AS suffix,
    NULLIF(
        TRIM(
            SUBSTRING_INDEX(
                SUBSTRING_INDEX(ct.witness_summary, ' | ', 2),
                'Contact:',
                -1
            )
        ),
        ''
    ) AS contact_number,
    NULL AS email,
    NULLIF(
        TRIM(
            SUBSTRING_INDEX(ct.witness_summary, 'Address:', -1)
        ),
        ''
    ) AS address,
    NULL AS age,
    NULL AS sex,
    'Backfilled from complaintstbl.witness_summary' AS remarks
FROM complaintstbl ct
LEFT JOIN caseparticipantstbl cp
    ON cp.case_id = ct.case_id
   AND cp.participant_role = 'Witness'
WHERE cp.case_id IS NULL
  AND ct.witness_summary IS NOT NULL
  AND TRIM(ct.witness_summary) <> '';
