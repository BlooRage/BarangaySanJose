# Blotter and Complaint QA Test Matrix

## Scope

This matrix covers:
- Resident complaint submission
- Admin complaint encoding
- Complaint tracker list/detail/actions
- Complaint-to-blotter review request flow
- Blotter review queue approval and rejection
- Direct admin blotter encoding
- Blotter tracker list/detail/logs/actions
- Cross-record consistency between complaint, review request, and blotter data

## Suggested Test Data

Use distinct people per run so records are easy to trace:
- Complainant: `Dela Cruz, Maria Santos`
- Subject/Respondent: `Reyes, Juan Carlo`
- Witness: `Torres, Ana Mae`
- Complaint type: `Noise Complaint`
- Blotter type: `Alarm and Scandal`
- Sample blotter number: `2026-001-BSJ`

## Environment Notes

Before testing, confirm:
- Admin account can access complaint pages, blotter pages, and Review Queue
- Resident account can submit complaints
- `complaintstbl` exists
- `barangayblottertbl` exists
- `blotterrequeststbl` exists
- `casesignaturestbl` exists
- `caseupdateslogtbl` exists
- `statuslookuptbl` has complaint, blotter, and `BlotterRequest` statuses

## Test Cases

| ID | Area | Scenario | Steps | Expected Result | Priority | Status |
|---|---|---|---|---|---|---|
| C-01 | Resident Complaint Intake | Submit valid complaint with complete required fields | 1. Log in as resident. 2. Open `Resident-End/Complaints/ComplaintsForm.php`. 3. Fill all required complainant, subject, incident fields. 4. Submit. | Complaint is accepted. Success message appears. A new `casereportstbl` row with `report_type='Complaint'` is created. A `complaintstbl` row is created. Complainant and respondent participants are saved. | High | Not Run |
| C-02 | Resident Complaint Intake | Submit complaint with optional witness | 1. Repeat valid resident complaint submission. 2. Fill witness name/contact/address. 3. Submit. | Complaint is accepted. Witness summary is saved in `complaintstbl`. Witness participant row is created. Witness appears in complaint detail view. | High | Not Run |
| C-03 | Resident Complaint Intake | Reject future incident date/time | 1. Submit resident complaint with future date. 2. Repeat with today plus future time if possible. | Submission is blocked with validation error. No new complaint/case rows are created. | High | Not Run |
| C-04 | Resident Complaint Intake | Reject incident older than 6 months | 1. Submit resident complaint with incident date older than 6 months. | Submission is blocked with validation error. No new rows are created. | High | Not Run |
| C-05 | Resident Complaint Intake | Reject invalid complainant phone | 1. Submit resident complaint with malformed complainant phone. | Submission is blocked. Error message references `09XXXXXXXXX` format. | Medium | Not Run |
| C-06 | Resident Complaint Intake | Submit without witness | 1. Submit a valid complaint leaving witness fields blank. | Complaint is accepted. No witness participant is created. Detail view shows witness fields as empty or `-`. | Medium | Not Run |
| C-07 | Resident Complaint Intake | Subject classification handling | 1. Submit one complaint with subject type `Business`. 2. Submit another with `Unknown`. | Saved `subject_kind` matches chosen value. Complaint detail displays correct subject type. | Medium | Not Run |
| C-08 | Resident Activity | Resident can see own complaint activity | 1. After resident submission, open resident activity page. | Complaint appears with correct complaint ID, type, timestamp, and pending-like status. | High | Not Run |
| A-01 | Admin Complaint Intake | Admin encodes valid complaint | 1. Log in as admin. 2. Open `Admin-End/Complaints/ComplaintForm.php`. 3. Fill required fields and submit. | Complaint is accepted. `complaint_origin='AdminEncoded'`. New case, complaint, and participant rows are created. | High | Not Run |
| A-02 | Admin Complaint Intake | Intake notes saved on creation | 1. Admin submits complaint with `Initial Notes`. 2. Open complaint detail in tracker. | Intake notes are present in detail view and stored in `complaintstbl.intake_notes`. | High | Not Run |
| A-03 | Admin Complaint Intake | Address system validation for complainant | 1. Choose `house` but omit house/street. 2. Choose `lot_block` but omit lot/block/phase. | Submission is blocked with address validation error. | High | Not Run |
| A-04 | Admin Complaint Intake | Optional witness handling in admin path | 1. Submit admin complaint with witness details. | Witness participant is created and visible in tracker detail. | Medium | Not Run |
| A-05 | Admin Complaint Tracker | Complaint list shows new record | 1. Open `Admin-End/Complaints/ComplaintTracker.php`. 2. Search by complaint ID/name. | New complaint is visible in list with submitted date, complainant, subject, complaint type, status, and level. | High | Not Run |
| A-06 | Admin Complaint Tracker | Complaint detail shows complete data | 1. Open complaint detail from tracker. | Detail includes complainant, subject, witness, incident data, narration, intake notes, screening notes, and blotter linkage state. | High | Not Run |
| A-07 | Admin Complaint Tracker | Update intake notes after creation | 1. Open complaint detail. 2. Edit intake notes. 3. Save. | Save succeeds. Reopening detail shows updated intake notes. Case updater is recorded. A case log entry for intake note update exists if `caseupdateslogtbl` is present. | High | Not Run |
| A-08 | Admin Complaint Tracker | Search/filter pending complaints | 1. Create records in multiple complaint states. 2. Use search and status filters. | Search results and pending badge match actual complaint records. | Medium | Not Run |
| A-09 | Complaint Outcome | Resolve pending complaint | 1. Open pending complaint. 2. Choose `Mark Resolved`. 3. Enter remarks and confirm. | Status becomes `Resolved`. Level remains `Complaint Only`. Action buttons disappear. Screening notes include entered remarks. | High | Not Run |
| A-10 | Complaint Outcome | Drop pending complaint | 1. Open pending complaint. 2. Choose `Drop Complaint`. 3. Enter remarks and confirm. | Status becomes `Dropped`. Level remains `Complaint Only`. Action buttons disappear. Screening notes include entered remarks. | High | Not Run |
| A-11 | Complaint Review Request | Send complaint for blotter review | 1. Open pending complaint. 2. Choose `Send for Blotter Review`. 3. Enter screening notes and confirm. | Complaint remains without linked blotter. A `blotterrequeststbl` row is created with `Pending` status. Complaint detail shows that the blotter request is under review. Action buttons are hidden while the request is pending. | Critical | Not Run |
| A-12 | Complaint Review Request | Hide blotter request details on plain pending complaints | 1. Open a complaint that has never been sent for blotter review. | Blotter request metadata fields are not shown in the notes section. Only normal complaint notes are visible. | High | Not Run |
| A-13 | Complaint Review Request | Prevent duplicate pending review requests | 1. Send complaint for blotter review once. 2. Attempt to send it again while request is pending. | Backend rejects duplicate request creation. No second request row is created. | Critical | Not Run |
| A-14 | Complaint Review Request | Pending review note hides actions | 1. Send complaint for blotter review. 2. Reopen complaint detail. | Complaint action buttons stay hidden. Notes section shows `Blotter request is still under review.` | High | Not Run |
| A-15 | Complaint Review Recovery | Rejected request restores complaint actions | 1. Send complaint for blotter review. 2. Reject it from Review Queue with review notes. 3. Reopen complaint detail. | Complaint returns to `Pending` / `Complaint Only`. Action buttons come back. Notes section shows rejection note with review remarks. Full request metadata block is not shown. | High | Not Run |
| A-16 | Complaint Review Audit | Request creation and rejection are logged | 1. Send complaint for review with distinctive notes. 2. Reject it with distinctive review notes. 3. Inspect complaint detail and case logs. | Screening notes and case remarks retain the review context. Case logs mention request creation/rejection and review notes. | High | Not Run |
| B-01 | Admin Blotter Intake | Submit valid blotter with typed narrative | 1. Open `Admin-End/Blotter/BlotterForm.php`. 2. Fill required fields. 3. Choose text narrative. 4. Submit. | Blotter is accepted. New `casereportstbl` row with `report_type='Blotter'` is created. `barangayblottertbl` row is created. Complainant/respondent participants are created. | High | Not Run |
| B-02 | Admin Blotter Intake | Submit valid blotter with uploaded narrative file | 1. Choose file narrative. 2. Upload allowed file type. 3. Submit. | File saves under `UnifiedFileAttachment/BlotterNarratives`. `case_details` stores file path. `case_remarks` indicates narrative file upload. | High | Not Run |
| B-03 | Admin Blotter Intake | Reject invalid narrative file type | 1. Upload unsupported file type. | Submission is blocked with invalid file type error. No partial blotter record remains. | High | Not Run |
| B-04 | Admin Blotter Intake | Reject future incident date/time | 1. Submit blotter with future incident timestamp. | Submission is blocked. No blotter/case rows are created. | High | Not Run |
| B-05 | Admin Blotter Intake | Direct blotter appears in tracker | 1. Submit direct blotter. 2. Open blotter tracker. | Blotter appears in list with blotter ID, blotter number, complainant, respondent, active status, and level. | High | Not Run |
| B-06 | Admin Blotter Tracker | View direct blotter detail | 1. Open direct blotter detail. | Detail shows incident data, participants, initial narrative, status, level, and saved signatures when applicable. | High | Not Run |
| B-07 | Blotter Tracker | Add narrative entry to active blotter | 1. Open active blotter. 2. Add narrative update. | Update succeeds. Entry appears in narrative reports section and case logs as `Narrative report added:`. | High | Not Run |
| B-08 | Blotter Tracker | Add general case log to active blotter | 1. Open active blotter. 2. Add case update/log. | Update succeeds. Entry appears in case logs modal. | High | Not Run |
| B-09 | Blotter Outcome | Resolve active blotter | 1. Open active blotter. 2. Choose `Mark as Resolved`. 3. Enter remarks and confirm. | Status becomes `Resolved`. Level becomes `Settled`. Further edits/log additions are blocked. | Critical | Not Run |
| B-10 | Blotter Outcome | Drop active blotter | 1. Open active blotter. 2. Choose `Mark as Dropped`. 3. Enter remarks and confirm. | Status becomes `Dropped`. Level becomes `Unsettled`. Further edits/log additions are blocked. | Critical | Not Run |
| B-11 | Blotter Outcome | Endorse active blotter to Lupon | 1. Open active blotter. 2. Choose endorsement. 3. Select `Lupon`. 4. Enter remarks and confirm. | Status becomes `Endorsed`. Level becomes `Endorsed to Lupon`. Further edits/log additions are blocked. | Critical | Not Run |
| B-12 | Blotter Outcome | Endorse active blotter to PNP | 1. Open active blotter. 2. Choose endorsement. 3. Select `PNP`. 4. Enter remarks and confirm. | Status becomes `Endorsed`. Level becomes `Endorsed to PNP`. Further edits/log additions are blocked. | Critical | Not Run |
| B-13 | Blotter Finalization | Prevent logs on finalized blotter | 1. Finalize blotter. 2. Attempt to add narrative/log. | Backend rejects the action. No new case log is created. | Critical | Not Run |
| B-14 | Blotter Review Queue | Pending request appears in Review Queue | 1. Send complaint for blotter review. 2. Open `Review Queue` under e-Blotter Tools. | Pending request appears with request ID, complaint ID, complainant, complaint type, and `Pending` status. | High | Not Run |
| B-15 | Blotter Review Queue | Review queue detail shows complaint context | 1. Open a pending review request. | Detail shows request metadata, complaint details, participants, narration, intake notes, screening notes, and case remarks. | High | Not Run |
| B-16 | Blotter Review Queue | Approve request requires admin-entered blotter number | 1. Open pending request. 2. Click approve. 3. Leave blotter number blank. 4. Retry with invalid format. 5. Retry with valid blotter number. | Blank or invalid blotter number is rejected. Valid blotter number allows approval. | Critical | Not Run |
| B-17 | Complaint-to-Blotter Linkage | Approved request creates final blotter | 1. Approve pending request with valid blotter number. 2. Open blotter tracker. | A real blotter is created only on approval. `barangayblottertbl.blotter_number` uses the admin-entered value. Complaint becomes endorsed and linked to the created `blotter_id`. | Critical | Not Run |
| B-18 | Complaint-to-Blotter Linkage | Participant copy integrity on approval | 1. Approve a request for a complaint with complainant/respondent/witness. 2. Open linked blotter detail. | Blotter contains copied participants from the complaint case, including witness data where applicable. | High | Not Run |
| B-19 | Complaint-to-Blotter Linkage | Approved blotter is searchable | 1. Approve a request. 2. Search linked blotter in blotter tracker by `blotter_id` and by admin-entered blotter number. | Linked blotter is found and detail opens correctly. | High | Not Run |
| B-20 | Complaint-to-Blotter Linkage | Compare direct vs review-approved blotter display | 1. Create one direct blotter and one review-approved blotter. 2. Compare tracker list/detail. | Both are readable in UI. Both have meaningful blotter numbers. Search and display work for both entry paths. | Medium | Not Run |
| X-01 | Data Integrity | Complaint remains visible after review request creation | 1. Send complaint for blotter review. 2. Return to complaint tracker. | Original complaint record remains visible with review-state context and no linked blotter yet. | High | Not Run |
| X-02 | Data Integrity | Approved request creates separate blotter case ID | 1. Approve a blotter request. 2. Compare complaint case ID and blotter case ID. | IDs are different. Linkage is maintained through `complaintstbl.blotter_id` and `blotterrequeststbl.approved_blotter_case_id`. | High | Not Run |
| X-03 | Data Integrity | Logs are attributable to acting user | 1. Perform intake note update, send for review, approve/reject request, blotter narrative update, blotter status change. 2. Open case logs. | Entries show timestamp and acting admin identity where logs are supported. | Medium | Not Run |
| X-04 | Data Integrity | Resident activity reflects approved complaint escalation | 1. Resident submits complaint. 2. Admin sends it for review. 3. Reviewer approves it. 4. Resident opens activity page. | Complaint status reflects final endorsement only after approval and, where shown, linked blotter ID appears only after approval. | High | Not Run |
| X-05 | Regression | Blotter signatures are persisted for typed narratives | 1. Submit direct blotter with typed narrative and drawn signatures. 2. Open blotter detail. 3. Repeat with missing signatures. | Typed narrative submission stores complainant/respondent signatures and shows them in detail. Missing signatures are rejected server-side. | High | Not Run |
| X-06 | Regression | Direct blotter creation is CSRF-protected | 1. Submit blotter form normally. 2. Attempt submission without valid CSRF token. | Valid form submits successfully. Missing/invalid token is rejected. | High | Not Run |
| X-07 | Regression | Complaint outcome remarks also update case remarks | 1. Resolve or drop a complaint with screening notes. 2. Reopen detail. | `screening_notes` and `case_remarks` both include the final-action remarks. | High | Not Run |
| X-08 | Regression | Review-approved blotter number is admin-controlled | 1. Approve a pending request with an admin-entered blotter number. 2. Open created blotter. | Review-approved blotter uses the admin-entered blotter number, not the generated `blotter_id`. | High | Not Run |

## Focus Areas For Exploratory Testing

- Rapid double-click or repeated confirmation on review-request approval
- Opening modals repeatedly after state changes
- Long intake notes, screening notes, and review notes
- Unusual names with suffixes, commas, or single-word values
- Blank optional contact fields mixed with present optional address fields
- Search behavior for pending review requests and approved blotters
- Behavior when `caseupdateslogtbl` is missing

## Known Gaps To Track Separately

- Review-approved blotter creation still copies complaint data directly into the final blotter; if a full editable pre-creation review form is desired, that would be a later enhancement.