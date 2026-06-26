<?php
require_once __DIR__ . '/../General/connection.php';
require_once __DIR__ . '/../General/security.php';
require_once __DIR__ . '/../General/adminModulePermissions.php';
require_once __DIR__ . '/../General/siteContent.php';

requireRoleSession(['SuperAdmin', 'Official', 'Officials', 'Personnel', 'Personnels', 'Admin'], false);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . appUrl('/Admin-End/Contents/ContentManagement.php'));
    exit;
}

verifyCsrfToken(false);

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection unavailable.';
    exit;
}

amp_ensure_permission_storage($conn);
$currentUserId = trim((string)($_SESSION['user_id'] ?? ''));
$currentRole = trim((string)($_SESSION['role'] ?? ''));
$allowedPermissions = amp_get_allowed_permission_keys($conn, $currentUserId, $currentRole);
$pageKey = cms_content_normalize_page_key((string)($_POST['page_key'] ?? ''));
$requiredPermission = $pageKey === 'faq' ? 'announcements_faq' : 'announcements_tracker';
if (!amp_permission_key_allowed($allowedPermissions, $requiredPermission)) {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

function cms_action_redirect(string $module, string $message, string $type = 'success', string $requestId = ''): void
{
    $_SESSION['cms_content_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    $query = ['module' => $module !== '' ? $module : 'requests'];
    if ($requestId !== '') {
        $query['request_id'] = $requestId;
    }

    header('Location: ' . appUrl('/Admin-End/Contents/ContentManagement.php') . '?' . http_build_query($query));
    exit;
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));
$requestId = trim((string)($_POST['request_id'] ?? ''));
$redirectModule = strtolower(trim((string)($_POST['redirect_module'] ?? ($pageKey !== '' ? $pageKey : 'requests'))));
if ($redirectModule === '' || $redirectModule === 'announcements') {
    $redirectModule = $pageKey !== '' ? $pageKey : 'requests';
}

$currentUserLabel = cms_content_current_user_display($conn, $currentUserId, $currentUserId);
$canReview = cms_content_can_review($conn, $currentUserId, $currentRole);
$request = $requestId !== '' ? cms_content_request($conn, $requestId) : null;

if (!in_array($action, ['save_draft', 'submit_request', 'auto_approve', 'approve_request', 'deny_request', 'revert_to_this_version', 'revert_to_previous_version', 'archive_request', 'restore_request'], true)) {
    cms_action_redirect($redirectModule, 'Invalid content action.', 'warning', $requestId);
}

if ($action === 'archive_request') {
    if (!$request) {
        cms_action_redirect('requests', 'Content request not found.', 'warning');
    }

    $status = strtolower(trim((string)($request['status'] ?? 'draft')));
    $liveRequest = $status === 'approved' ? cms_content_live_request($conn, (string)($request['page_key'] ?? '')) : null;
    $isLiveApprovedRequest = $liveRequest && (string)($liveRequest['request_id'] ?? '') === (string)$request['request_id'];
    if (!cms_content_request_is_archivable_by($request, $currentUserId, $canReview, $isLiveApprovedRequest)) {
        $message = $isLiveApprovedRequest
            ? 'The current live approved version cannot be archived.'
            : 'You do not have permission to archive this content request.';
        cms_action_redirect('requests', $message, 'warning', $requestId);
    }

    if (!cms_content_archive_request($conn, $requestId, $currentUserId, $currentUserLabel)) {
        cms_action_redirect('requests', 'Unable to archive the selected content request.', 'danger', $requestId);
    }

    cms_action_redirect('requests', 'Content request archived.', 'success');
}

if ($action === 'restore_request') {
    if (!$request) {
        cms_action_redirect('requests', 'Content request not found.', 'warning');
    }
    if (!cms_content_request_is_restorable_by($request, $currentUserId, $canReview)) {
        cms_action_redirect('requests', 'You do not have permission to restore this content request.', 'warning', $requestId);
    }
    if (!cms_content_restore_request($conn, $requestId, $currentUserLabel)) {
        cms_action_redirect('requests', 'Unable to restore the selected content request.', 'danger', $requestId);
    }

    cms_action_redirect('requests', 'Content request restored.', 'success', $requestId);
}

if (in_array($action, ['revert_to_this_version', 'revert_to_previous_version'], true)) {
    if (!$canReview) {
        cms_action_redirect('requests', 'Only SuperAdmin or the appointed Barangay Secretary can revert published content versions.', 'danger');
    }
    if (!$request) {
        cms_action_redirect('requests', 'The selected content version could not be found.', 'warning');
    }
    if (strtolower(trim((string)($request['status'] ?? 'draft'))) !== 'approved') {
        cms_action_redirect('requests', 'Only approved content versions can be used for a revert.', 'warning', $requestId);
    }

    $sourceRequest = $request;
    $revertMessage = 'Live content reverted to the selected version.';
    $reviewNote = 'Reverted live page to version ' . (string)$request['request_id'] . ' by ' . $currentUserLabel . '.';

    if ($action === 'revert_to_previous_version') {
        $previousRequest = cms_content_previous_approved_request($conn, (string)$request['page_key'], (string)$request['request_id']);
        if (!$previousRequest) {
            cms_action_redirect('requests', 'There is no previous approved version available to revert to.', 'warning', $requestId);
        }

        $sourceRequest = $previousRequest;
        $revertMessage = 'Live content reverted to the previous approved version.';
        $reviewNote = 'Reverted live page from version ' . (string)$request['request_id']
            . ' to previous version ' . (string)$previousRequest['request_id']
            . ' by ' . $currentUserLabel . '.';
    }

    $savedRequestId = cms_content_upsert_request(
        $conn,
        (string)$sourceRequest['page_key'],
        (array)($sourceRequest['content'] ?? []),
        'approved',
        $currentUserId,
        $currentUserLabel,
        $currentRole,
        '',
        $reviewNote,
        $currentUserId,
        $currentUserLabel
    );
    if ($savedRequestId === '') {
        cms_action_redirect('requests', 'Unable to create the reverted content version.', 'danger', $requestId);
    }

    $savedRequest = cms_content_request($conn, $savedRequestId);
    if (!$savedRequest || !cms_content_apply_live_page($conn, (string)$savedRequest['page_key'], (array)($savedRequest['content'] ?? []), $currentUserId, $currentUserLabel)) {
        cms_action_redirect('requests', 'The revert was recorded, but the live page could not be updated.', 'danger', $savedRequestId);
    }

    cms_action_redirect('requests', $revertMessage, 'success', $savedRequestId);
}

if (in_array($action, ['approve_request', 'deny_request'], true)) {
    if (!$canReview) {
        cms_action_redirect('requests', 'Only SuperAdmin or the appointed Barangay Secretary can review content requests.', 'danger');
    }
    if (!$request) {
        cms_action_redirect('requests', 'Content request not found.', 'warning');
    }
    if (strtolower(trim((string)($request['status'] ?? 'draft'))) !== 'pending') {
        cms_action_redirect('requests', 'Only pending content requests can be reviewed.', 'warning', $requestId);
    }

    $reviewActionStatus = $action === 'approve_request' ? 'approved' : 'denied';
    $reviewMessage = $action === 'approve_request'
        ? 'Content request approved and published.'
        : 'Content request denied.';
    $reviewNote = trim((string)($_POST['review_note'] ?? ''));
    if ($reviewNote === '') {
        $reviewNote = $action === 'approve_request'
            ? 'Approved by ' . $currentUserLabel . '.'
            : 'Denied by ' . $currentUserLabel . '.';
    }

    $savedRequestId = cms_content_upsert_request(
        $conn,
        (string)$request['page_key'],
        (array)($request['content'] ?? []),
        $reviewActionStatus,
        (string)($request['created_by_user_id'] ?? ''),
        (string)($request['created_by_label'] ?? ''),
        (string)($request['created_by_role'] ?? ''),
        (string)$request['request_id'],
        $reviewNote,
        $currentUserId,
        $currentUserLabel
    );
    if ($savedRequestId === '') {
        cms_action_redirect('requests', 'Unable to update the content request.', 'danger', $requestId);
    }

    if ($action === 'approve_request') {
        $savedRequest = cms_content_request($conn, $savedRequestId);
        if (!$savedRequest || !cms_content_apply_live_page($conn, (string)$savedRequest['page_key'], (array)($savedRequest['content'] ?? []), $currentUserId, $currentUserLabel)) {
            cms_action_redirect('requests', 'The request was approved but the live page could not be updated.', 'danger', $savedRequestId);
        }
    }

    cms_action_redirect('requests', $reviewMessage, 'success', $savedRequestId);
}

if ($pageKey === '') {
    cms_action_redirect($redirectModule, 'Select a valid content page first.', 'warning', $requestId);
}

$payloadJson = (string)($_POST['payload_json'] ?? '');
$payload = json_decode($payloadJson, true);
if (!is_array($payload)) {
    cms_action_redirect($redirectModule, 'Unable to read the content changes that were submitted.', 'danger', $requestId);
}

if ($request) {
    if ((string)($request['page_key'] ?? '') !== $pageKey) {
        cms_action_redirect($redirectModule, 'The selected request does not match the current content page.', 'warning', $requestId);
    }

    $canEditExistingRequest = cms_content_request_is_editable_by($request, $currentUserId)
        || ($action === 'auto_approve' && $canReview && cms_content_request_is_owned_by($request, $currentUserId));

    if (!$canEditExistingRequest) {
        cms_action_redirect($redirectModule, 'This content request can no longer be edited from the page editor.', 'danger', $requestId);
    }
}

$requestRoleForSave = $request ? (string)($request['created_by_role'] ?? $currentRole) : $currentRole;
$requestIdForSave = $request ? (string)$request['request_id'] : '';

if ($action === 'save_draft') {
    $savedRequestId = cms_content_upsert_request(
        $conn,
        $pageKey,
        $payload,
        'draft',
        $currentUserId,
        $currentUserLabel,
        $requestRoleForSave,
        $requestIdForSave
    );
    if ($savedRequestId === '') {
        cms_action_redirect($redirectModule, 'Unable to save the content draft.', 'danger', $requestId);
    }

    cms_action_redirect($pageKey, 'Content draft saved.', 'success', $savedRequestId);
}

if ($action === 'submit_request') {
    $savedRequestId = cms_content_upsert_request(
        $conn,
        $pageKey,
        $payload,
        'pending',
        $currentUserId,
        $currentUserLabel,
        $requestRoleForSave,
        $requestIdForSave
    );
    if ($savedRequestId === '') {
        cms_action_redirect($redirectModule, 'Unable to submit the content request.', 'danger', $requestId);
    }

    cms_action_redirect($pageKey, 'Content request submitted for review.', 'success', $savedRequestId);
}

if (!$canReview) {
    cms_action_redirect($redirectModule, 'Only SuperAdmin or the appointed Barangay Secretary can auto-approve content changes.', 'danger', $requestId);
}

$savedRequestId = cms_content_upsert_request(
    $conn,
    $pageKey,
    $payload,
    'approved',
    $currentUserId,
    $currentUserLabel,
    $requestRoleForSave,
    $requestIdForSave,
    'Auto-approved by ' . $currentUserLabel . '.',
    $currentUserId,
    $currentUserLabel
);
if ($savedRequestId === '') {
    cms_action_redirect($redirectModule, 'Unable to auto-approve the content changes.', 'danger', $requestId);
}

$savedRequest = cms_content_request($conn, $savedRequestId);
if (!$savedRequest || !cms_content_apply_live_page($conn, (string)$savedRequest['page_key'], (array)($savedRequest['content'] ?? []), $currentUserId, $currentUserLabel)) {
    cms_action_redirect($pageKey, 'The request was saved, but the live page could not be updated.', 'danger', $savedRequestId);
}

cms_action_redirect($pageKey, 'Content changes approved and published.', 'success', $savedRequestId);
