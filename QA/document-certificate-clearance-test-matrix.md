# Document Request QA Test Matrix

## Scope

This matrix covers:
- Resident certificate request submission
- Resident clearance request submission
- Document-specific validation and required attachment handling
- Resident document request tracker and payment flow
- Admin personnel review, rejection, and interview flow
- Finance payment verification and rejection
- Document release, completion, and issued-file access
- Cross-record consistency for `documentrequesttbl`, `unifiedfileattachmenttbl`, finance rows, and issued-document metadata

## In Scope Documents

Visible resident-facing document requests in the current workflow:
- Certificates: `Cohabitation`, `Relationship for Jail Visitation`, `Indigency`, `First Time Job Seeker`, `Good Moral`, `Residency`
- Clearances: `Barangay Clearance for Business Permit`, `Barangay Clearance for Tricycle Permit`, `Barangay Clearance for Electrical Permit`, `Barangay Clearance for Water Permit`, `Barangay Clearance for Residential Permit`, `Barangay Clearance for Commercial Permit`

Out of scope unless separately enabled:
- `Identity` certificate form
- `Other Permits` clearance entry (currently hidden on landing page)

## Workflow Summary

Current backend stages observed in the implementation:
- `submitted`
- `for_interview`
- `interview_failed`
- `rejected`
- `for_payment`
- `payment_submitted`
- `payment_rejected`
- `ready_for_claim`
- `completed`
- `cancelled`

Important flow rules:
- Standard paid documents go from `submitted` to `for_payment` after personnel approval.
- Free documents can move directly to `ready_for_claim`.
- `First Time Job Seeker` goes to `for_interview` first, then either `interview_failed` or `ready_for_claim`.
- Resident online payment submission is `GCash` only.
- Admin finance can record walk-in barangay payment from unpaid/rejected payment states.
- Overdue unpaid requests are auto-cancelled after 5 working days.

## Suggested Test Data

Use distinct residents and business names so records are easy to trace:
- Resident: `Dela Cruz, Maria Santos`
- Cohabitant: `Reyes, Juan Carlo`
- Business name: `San Jose Mini Mart`
- Tricycle plate number: `ABC 1234`
- Purpose text: `Employment requirement`
- OR number: `OR-2026-00045`

## Environment Notes

Before testing, confirm:
- Resident account can access `Resident-End/Certificates/CertificatesLandingPage.php`
- Resident account can access `Resident-End/Clearances/ClearancesLandingPage.php`
- Resident account can access `Resident-End/document_requests.php`
- Personnel/admin account can access `Admin-End/Certificates/CertificateTracker.php`
- Finance/admin account can access `Admin-End/Certificates/FinancePayments.php`
- `documentrequesttbl` exists
- `documenttypelookuptbl` exists
- `unifiedfileattachmenttbl` exists
- `financetransactiontbl` exists
- `generalfeestbl` exists
- `statuslookuptbl` has document request and payment statuses used by the workflow
- `UnifiedFileAttachment/DocumentRequests`, `UnifiedFileAttachment/DocumentPayments`, and `UnifiedFileAttachment/IssuedDocuments` are writable

## Test Cases

| ID | Area | Scenario | Steps | Expected Result | Priority | Status |
|---|---|---|---|---|---|---|
| R-01 | Landing Pages | Certificates landing shows current visible document choices | 1. Log in as resident. 2. Open `Resident-End/Certificates/CertificatesLandingPage.php`. | Visible cards match current enabled certificates. Hidden/disabled document types are not exposed as normal resident actions. | Medium | Not Run |
| R-02 | Landing Pages | Clearances landing shows current visible document choices | 1. Open `Resident-End/Clearances/ClearancesLandingPage.php`. | Visible cards match the enabled clearances. Hidden `Other Permits` option is not shown. | Medium | Not Run |
| R-03 | General Submission | Submit valid residency request | 1. Open `Resident-End/Certificates/ResidencyForm.php`. 2. Fill required fields. 3. Submit. | Request is accepted. `documentrequesttbl` row is created with normalized residency document type, purpose, resident linkage, payload, and `submitted` stage. | High | Not Run |
| R-04 | General Submission | Submit valid indigency request | 1. Open `Resident-End/Certificates/IndigencyForm.php`. 2. Select a valid purpose. 3. Submit. | Request is accepted and appears in resident tracker with pending/submitted state. | High | Not Run |
| R-05 | General Submission | Submit valid good moral request | 1. Open `Resident-End/Certificates/GoodMoralForm.php`. 2. Choose a predefined purpose. 3. Submit. | Request is accepted and stored with the resolved purpose text. | High | Not Run |
| R-06 | General Submission | Good moral custom purpose path | 1. Open good moral form. 2. Choose `Other`. 3. Enter custom purpose. 4. Submit. | Custom purpose is copied into final hidden purpose field and saved correctly. | Medium | Not Run |
| R-07 | General Submission | Submit valid cohabitation certificate | 1. Open `Resident-End/Certificates/CohabitationForm.php`. 2. Fill partner and purpose fields. 3. Upload required partner ID files. 4. Submit. | Request is accepted. Partner ID metadata and file paths are captured in request payload. | High | Not Run |
| R-08 | Relationship Variant | Submit valid relationship-for-jail-visitation request | 1. Open `Resident-End/Certificates/CohabitationForm.php?variant=relationship_jail_visit`. 2. Fill detention facility and purpose fields. 3. Upload at least one relationship proof and one detention proof. 4. Submit. | Request is accepted. Variant-specific proof file paths are saved. Purpose and detention facility are retained. | Critical | Not Run |
| R-09 | Relationship Variant | Block relationship request without relationship proof | 1. Open jail visitation variant. 2. Leave relationship proof uploads empty. 3. Submit. | Submission is rejected with a proof-of-relationship validation error. No request row is created. | Critical | Not Run |
| R-10 | Relationship Variant | Block relationship request without detention proof type/file | 1. Open jail visitation variant. 2. Omit detention proof type or files. 3. Submit. | Submission is rejected with a detention-proof validation error. | Critical | Not Run |
| R-11 | First Time Job Seeker | Submit valid first-time job seeker request | 1. Open `Resident-End/Certificates/FirstTimeJobSeekerForm.php`. 2. Fill required fields. 3. Submit. | Request is accepted as `submitted`. Later approval path should route to `for_interview`, not directly to payment. | Critical | Not Run |
| R-12 | Clearance Submission | Submit valid business clearance as new application | 1. Open `Resident-End/Clearances/BusinessClearanceForm.php`. 2. Choose `New`. 3. Fill required business details. 4. Upload required registration, proof-of-address, and business photo files. 5. Submit. | Request is accepted. Uploaded file paths are saved in payload. Purpose defaults to `Business Permit - New Application` if not manually changed. | Critical | Not Run |
| R-13 | Clearance Submission | Submit valid business clearance as renewal | 1. Open business clearance form. 2. Choose `Renewal`. 3. Fill required fields. 4. Upload updated registration and proof-of-address files. 5. Submit. | Request is accepted. Renewal-specific files are required and saved. Purpose resolves to `Business Permit - Renewal`. | Critical | Not Run |
| R-14 | Clearance Validation | Reject business clearance with missing application type | 1. Attempt submission without `New` or `Renewal`. | Submission is blocked with `Application type is required.` | High | Not Run |
| R-15 | Clearance Validation | Reject renter business clearance without owner name | 1. Select `Renter`. 2. Omit owner first/last name. 3. Submit. | Submission is blocked with renter-owner validation error. | High | Not Run |
| R-16 | Clearance Validation | Reject business clearance without proof address document number when required | 1. Select non-lease proof of address. 2. Leave document number blank. 3. Submit. | Submission is blocked with proof document number validation error. | High | Not Run |
| R-17 | Clearance Submission | Submit valid tricycle clearance as new application | 1. Open `Resident-End/Clearances/TricycleForm.php`. 2. Choose `New`. 3. Fill franchise, vehicle, and owner fields. 4. Upload OR, CR, and TODA/PODA certification. 5. Submit. | Request is accepted. Purpose defaults to `Tricycle Permit - New Application`. | Critical | Not Run |
| R-18 | Clearance Submission | Submit valid tricycle clearance as renewal | 1. Repeat tricycle submission using `Renewal`. 2. Upload previous year clearance. | Request is accepted and previous-clearance file path is saved. | Critical | Not Run |
| R-19 | Clearance Validation | Reject tricycle request without valid franchisee | 1. Force invalid or blank franchisee value. 2. Submit. | Submission is blocked with franchisee validation error. | High | Not Run |
| R-20 | Clearance Validation | Require deed of sale when tricycle vehicle is not named to owner | 1. Set `vehicle_named_to_owner=no`. 2. Omit deed-of-sale upload. 3. Submit. | Submission is rejected with deed-of-sale validation error. | Critical | Not Run |
| R-21 | Clearance Submission | Submit valid electrical permit clearance | 1. Open `Resident-End/Clearances/ElectricalForm.php`. 2. Fill lot/project details. 3. Upload proof of address. 4. Submit. | Request is accepted. Purpose defaults to `Electrical Permit Application`. | High | Not Run |
| R-22 | Clearance Submission | Submit valid water permit clearance | 1. Open `Resident-End/Clearances/WaterForm.php`. 2. Fill required fields and proof-of-address upload. 3. Submit. | Request is accepted. Purpose defaults to `Water Permit Application`. | High | Not Run |
| R-23 | Clearance Submission | Submit valid residential permit clearance | 1. Open `Resident-End/Clearances/ResidentialForm.php`. 2. Fill purpose and lot/project details. 3. Upload proof of address. 4. Submit. | Request is accepted. Manual purpose is required and stored. | High | Not Run |
| R-24 | Clearance Submission | Submit valid commercial permit clearance with SEC certificate | 1. Open `Resident-End/Clearances/CommercialForm.php`. 2. Fill purpose and lot/project details. 3. Upload proof of address and SEC certificate. 4. Submit. | Request is accepted. Both uploads are saved. | Critical | Not Run |
| R-25 | Clearance Validation | Reject general permit request with missing lot address fields | 1. Open electrical, water, residential, or commercial form. 2. Set not-same-address path. 3. Leave required lot address fields incomplete. 4. Submit. | Submission is blocked with lot-address validation error. | High | Not Run |
| R-26 | Clearance Validation | Reject general permit request with invalid proof-of-address type | 1. Force an invalid proof-of-address type value. 2. Submit. | Submission is blocked with `Valid proof of address type is required.` | High | Not Run |
| R-27 | Clearance Validation | Require SEC certificate for commercial permit | 1. Open commercial permit form. 2. Omit SEC certificate. 3. Submit. | Submission is blocked with SEC certificate validation error. | Critical | Not Run |
| R-28 | Clearance Validation | Require SEC certificate for electrical/water when ownership is company or partnership | 1. Open electrical or water permit form. 2. Choose `Company` or `Partnership`. 3. Omit SEC certificate. 4. Submit. | Submission is blocked with SEC certificate validation error. | Critical | Not Run |
| R-29 | File Validation | Reject unsupported upload type in request attachments | 1. Upload unsupported file type to any attachment field. 2. Submit. | Submission is blocked with unsupported file type error. No partial request remains. | High | Not Run |
| R-30 | Resident Tracker | Newly submitted requests appear in resident document tracker | 1. Submit one certificate and one clearance. 2. Open `Resident-End/document_requests.php`. | Both records are listed with request ID, document name, fee, stage badge, and submitted timestamp. | High | Not Run |
| R-31 | Resident Tracker | View modal shows submitted payload details | 1. Open a request from resident tracker. | Modal displays submitted form details from payload/request details instead of blank content. | High | Not Run |
| R-32 | Personnel Review | Personnel approves paid request | 1. Open admin certificate tracker. 2. Find a paid request such as business clearance. 3. Approve. | Request moves from `submitted` to `for_payment`. Notification is generated. Fee amount is resolved from `generalfeestbl`. | Critical | Not Run |
| R-33 | Personnel Review | Personnel approves free request directly to release | 1. Configure or choose a zero-fee document. 2. Approve from admin tracker. | Request moves from `submitted` to `ready_for_claim`. Verification code and issued document metadata are prepared. | High | Not Run |
| R-34 | Personnel Review | Personnel rejects request with required reason | 1. Reject a submitted request. 2. Enter reason. | Request moves to `rejected`. Resident-facing reason is visible in tracker/status detail. | High | Not Run |
| R-35 | Personnel Review | Reject approval without reason is blocked | 1. Attempt rejection with empty reason. | Backend rejects action with `Rejection reason is required.` | High | Not Run |
| R-36 | Interview Flow | First-time job seeker approval routes to interview | 1. Open submitted FTJS request. 2. Approve. | Request moves to `for_interview`, not `for_payment`. Notification mentions interview within 5 working days. | Critical | Not Run |
| R-37 | Interview Flow | Pass first-time job seeker interview | 1. Open FTJS request in `for_interview`. 2. Pass interview. | Request moves to `ready_for_claim`. Issued file and verification code are generated. | Critical | Not Run |
| R-38 | Interview Flow | Fail first-time job seeker interview with reason | 1. Open FTJS request in `for_interview`. 2. Fail interview with reason. | Request moves to `interview_failed`. Resident can see failure state and reason. | Critical | Not Run |
| R-39 | Interview Flow | Fail interview without reason is blocked | 1. Attempt `interview_fail` with empty reason. | Backend rejects the action. Stage remains unchanged. | High | Not Run |
| R-40 | Payment Mode | Resident can choose barangay payment mode for payable request | 1. Open resident tracker for a `for_payment` request. 2. Select `Pay in Barangay`. | Payment mode is saved. Resident sees barangay payment instructions and deadline note. | High | Not Run |
| R-41 | Payment Mode | Resident can choose GCash payment mode for payable request | 1. Open a `for_payment` request. 2. Select `GCash`. | Mode is saved. Resident can open GCash modal with QR, reference, and proof upload fields. | High | Not Run |
| R-42 | Resident Payment | Submit valid GCash payment proof | 1. Open GCash modal for a `for_payment` request. 2. Enter reference number. 3. Upload proof image. 4. Submit. | Request moves to `payment_submitted`. Finance transaction fields store method, reference, proof path, and payment timestamp. | Critical | Not Run |
| R-43 | Resident Payment | Reject GCash submission without reference | 1. Leave reference blank. 2. Submit payment. | Backend rejects submission with reference-required error. | High | Not Run |
| R-44 | Resident Payment | Reject GCash submission without proof | 1. Leave proof blank. 2. Submit payment. | Backend rejects submission with proof-required error. | High | Not Run |
| R-45 | Resident Payment | Online submission blocks barangay mode upload path | 1. Attempt to submit payment payload using `payment_method=barangay`. | Backend rejects with message that online submission is only for GCash and barangay payment must be handled at the office. | High | Not Run |
| R-46 | Finance Review | Finance verifies submitted GCash payment | 1. Open finance payments page. 2. Locate `payment_submitted` request. 3. Verify payment with OR number. | Request moves to `ready_for_claim`. Certificate number, verification code, amount, OR number, finance decision timestamp, and issued file are saved. | Critical | Not Run |
| R-47 | Finance Review | Finance records walk-in payment from unpaid state | 1. Open `for_payment` request. 2. Use walk-in payment action. 3. Enter OR number. | Request moves directly to `ready_for_claim`. Payment method becomes `barangay`. Payment proof/reference are cleared. | Critical | Not Run |
| R-48 | Finance Review | Finance rejects payment with required reason | 1. Open `payment_submitted` request. 2. Reject with reason. | Request moves to `payment_rejected`. Resident sees rejection reason and can re-select payment mode. | High | Not Run |
| R-49 | Finance Review | Reject payment without reason is blocked | 1. Attempt finance rejection with blank reason. | Backend rejects action. Stage remains unchanged. | High | Not Run |
| R-50 | Payment Recovery | Resident can recover from payment rejection | 1. Get request to `payment_rejected`. 2. Reopen resident tracker. 3. Change payment mode. 4. Resubmit payment or choose barangay mode. | Request becomes payable again and is no longer stuck in rejected state. | High | Not Run |
| R-51 | Auto Cancellation | Overdue unpaid payable requests auto-cancel | 1. Create a `for_payment` request. 2. Let payment deadline expire without payment timestamp. 3. Trigger maintenance/backfill path if needed. | Request moves to `cancelled` with auto-cancel remarks referencing 5 working days. | Critical | Not Run |
| R-52 | Release Flow | Ready-for-claim request can be marked completed | 1. Open `ready_for_claim` request in admin tracker. 2. Mark completed / release. | Request moves to `completed`. `completed_at` is stored and resident can view/download issued file. | Critical | Not Run |
| R-53 | Issued Document | Resident can view issued document only after completion | 1. Open resident tracker for a `completed` request. 2. Click `View Document`. | Issued document opens successfully. Download/view endpoint serves the correct file. | High | Not Run |
| R-54 | Issued Document | Admin can preview/view issued document when request is ready or completed | 1. Open request in `ready_for_claim` and `completed`. 2. Use preview/view actions. | Generated document reflects request details, certificate number, verification code, and document-specific template data. | High | Not Run |
| R-55 | Data Integrity | Request creates unified request attachment payload row | 1. Submit any request. 2. Inspect `unifiedfileattachmenttbl`. | Attachment row exists with `source_type='DocumentRequest'`, payload file, document type linkage, uploader, and pending status. | High | Not Run |
| R-56 | Data Integrity | Admin/finance actions stamp actor and timestamps | 1. Approve, reject, verify payment, and complete different requests. 2. Inspect row fields. | `user_id_official_reviewed_by`, `user_id_official_released_by`, `review_timestamp`, `release_timestamp`, and decision timestamps are populated where applicable. | High | Not Run |
| R-57 | Data Integrity | Resident tracker status badges match backend stage | 1. Move requests through `submitted`, `for_payment`, `payment_rejected`, `ready_for_claim`, `completed`, and `cancelled`. 2. Open resident tracker. | Badge labels and tab grouping match actual stage. Rejection reasons and payment deadlines appear only where applicable. | High | Not Run |
| R-58 | Data Integrity | Issued identifiers are generated on finance verification or release path | 1. Verify a paid request. 2. Open resulting request detail. | `certificate_number` and `verification_code` are populated and reusable in issued document preview/view endpoints. | High | Not Run |

## Focus Areas For Exploratory Testing

- Rapid repeated clicking on approve, verify payment, and mark-complete actions
- Reopening resident and admin modals after stage changes
- Long text in purpose, remarks, business address, and detention facility fields
- File upload retry behavior after a validation failure
- Stage drift between `stage`, request status badge, and finance transaction status
- Certificate preview correctness after admin edits before approval
- Resident behavior when issued document exists at `ready_for_claim` vs `completed`

## Known Gaps To Track Separately

- `Identity` certificate has a form but is not currently exposed on the certificate landing page.
- `Other Permits` clearance appears intentionally hidden on the landing page and should be tested only if re-enabled.
- `payment_verified` exists in constants and compatibility logic, but the active admin flow advances directly to `ready_for_claim` after successful finance verification.
