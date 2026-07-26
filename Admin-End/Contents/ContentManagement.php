<?php
require_once __DIR__ . '/../includes/admin_guard.php';
require_once __DIR__ . '/../../PhpFiles/General/siteContent.php';

cms_content_ensure_schema($conn);

$allowedPermissions = [];
if (isset($conn) && $conn instanceof mysqli) {
    $allowedPermissions = amp_get_allowed_permission_keys(
        $conn,
        (string)($_SESSION['user_id'] ?? ''),
        (string)($_SESSION['role'] ?? '')
    );
}

$canManageAnnouncements = amp_permission_keys_have_any($allowedPermissions, [
    'announcements_page',
    'announcements_delivery',
    'announcements_faq',
    'announcements_tracker',
]);
$canManageNews = amp_permission_key_allowed($allowedPermissions, 'news_management');
$canViewReports = amp_permission_keys_have_any($allowedPermissions, [
    'reports_certificate_issuance',
    'reports_clearance_issuance',
    'reports_financial',
    'reports_residents',
    'reports_blotter',
    'reports_complaints',
]);

$currentUserId = trim((string)($_SESSION['user_id'] ?? ''));
$currentRole = trim((string)($_SESSION['role'] ?? ''));
$currentUserLabel = cms_content_current_user_display($conn, $currentUserId, $currentUserId);
$canReviewContent = cms_content_can_review($conn, $currentUserId, $currentRole);

$contentToolsUrl = appUrl('Admin-End/Contents/Contents.php') . '?tool=tracker#tracker-card';
$newsToolsUrl = appUrl('Admin-End/Contents/Contents.php') . '?tool=tracker&type_filter=news#tracker-card';
$createNewsUrl = appUrl('Admin-End/Contents/CreateNews.php');
$createAnnouncementUrl = appUrl('Admin-End/Contents/CreateContent.php') . '?type=page';
$reportsUrl = appUrl('Admin-End/Reports/Reports.php') . '?module=certificate_issuance';

$contentModules = [
    'requests' => [
        'label' => 'Content Change Request',
        'title' => 'Content Change Request',
        'icon' => 'fa-code-compare',
        'status' => 'Live Module',
        'summary' => 'Track drafts, pending reviews, approvals, denials, and auto-approved updates for CMS pages.',
    ],
    'home' => [
        'label' => 'Home Page',
        'title' => 'Home Page',
        'icon' => 'fa-house',
        'status' => 'Live Editor',
        'summary' => 'Edit the home banner, About section, mission and vision, and history copy.',
    ],
    'government' => [
        'label' => 'Government',
        'title' => 'Government Page',
        'icon' => 'fa-landmark',
        'status' => 'Live Editor',
        'summary' => 'Manage the government banner, Punong Barangay section, officials, and area listings.',
    ],
    'services' => [
        'label' => 'Services',
        'title' => 'Services Page',
        'icon' => 'fa-briefcase',
        'status' => 'Live Editor',
        'summary' => 'Update the services banner and the description of each service card.',
    ],
    'faq' => [
        'label' => 'FAQ',
        'title' => 'FAQ Page',
        'icon' => 'fa-circle-question',
        'status' => 'Live Editor',
        'summary' => 'Edit the FAQ page banner and manage the public FAQ questions and answers in one page.',
    ],
    'contact' => [
        'label' => 'Contact',
        'title' => 'Contact Page',
        'icon' => 'fa-phone-volume',
        'status' => 'Live Editor',
        'summary' => 'Update the contact banner, emergency section, and area hotline tiles.',
    ],
    'login' => [
        'label' => 'Login',
        'title' => 'Login Page',
        'icon' => 'fa-right-to-bracket',
        'status' => 'Live Editor',
        'summary' => 'Manage the public login image and register image panels.',
    ],
];

$hiddenEditorModules = [
    'announcements' => [
        'label' => 'News Page',
        'title' => 'News Page',
        'icon' => 'fa-newspaper',
        'status' => 'Live Editor',
        'summary' => 'Edit the public news page banner image, title, and message while the news feed stays connected to the Announcements module.',
    ],
];

$editorMeta = [
    'announcements' => [
        'subtitle' => 'This editor controls the public News page banner. News posts now use a dedicated Create News workflow while announcements stay in the Announcements module.',
        'notes' => [
            'Use Create News for full public news articles with a headline image and section builder.',
            'Use the Announcements tools for public announcements, SMS, email, and FAQ items.',
        ],
        'quick_links' => [
            ['label' => 'Open Content Tools', 'href' => $canManageAnnouncements ? $contentToolsUrl : ($canManageNews ? $newsToolsUrl : '')],
            ['label' => 'Create News', 'href' => $canManageNews ? $createNewsUrl : ''],
            ['label' => 'Create Announcement', 'href' => $canManageAnnouncements ? $createAnnouncementUrl : ''],
        ],
    ],
    'home' => [
        'subtitle' => 'The Meet the Council section automatically uses the approved Government page officials and Punong Barangay profile.',
        'notes' => [
            'The council preview below is derived from the approved Government page content.',
            'History images stay on their current design and only the history message is editable here.',
        ],
        'quick_links' => [
            ['label' => 'Open Request Queue', 'href' => cms_request_view_url('my_requests')],
        ],
    ],
    'government' => [
        'subtitle' => 'Government page changes also drive the Home page council section after approval.',
        'notes' => [
            'Official images are editable here so the Home page council preview stays in sync with the Government page.',
        ],
        'quick_links' => [
            ['label' => 'Open Request Queue', 'href' => cms_request_view_url('my_requests')],
        ],
    ],
    'services' => [
        'subtitle' => 'Service titles stay aligned to the current public cards while each description is editable.',
        'notes' => [],
        'quick_links' => [
            ['label' => 'Open Request Queue', 'href' => cms_request_view_url('my_requests')],
        ],
    ],
    'faq' => [
        'subtitle' => 'Update the FAQ banner and the public question-and-answer list from this page editor.',
        'notes' => [
            'Add, remove, and edit FAQ entries here before saving, submitting, or publishing.',
        ],
        'quick_links' => [
            ['label' => 'Open Request Queue', 'href' => cms_request_view_url('my_requests')],
        ],
    ],
    'contact' => [
        'subtitle' => 'Add or remove hotline tiles as needed and preview the layout before submitting.',
        'notes' => [
            'Area hotlines support additional tiles whenever you need to expand the section.',
        ],
        'quick_links' => [
            ['label' => 'Open Request Queue', 'href' => cms_request_view_url('my_requests')],
        ],
    ],
    'login' => [
        'subtitle' => 'The login panel and signup panel images are managed here and update the public login screen.',
        'notes' => [],
        'quick_links' => [
            ['label' => 'Open Request Queue', 'href' => cms_request_view_url('my_requests')],
        ],
    ],
];

$allModuleMeta = $contentModules + $hiddenEditorModules;
$selectedModuleKey = strtolower(trim((string)($_GET['module'] ?? 'requests')));
if (!isset($allModuleMeta[$selectedModuleKey])) {
    $selectedModuleKey = 'requests';
}
$selectedModule = $allModuleMeta[$selectedModuleKey];

$allRequests = cms_content_requests($conn);
$myRequests = array_values(array_filter($allRequests, static function (array $request) use ($currentUserId): bool {
    return cms_content_request_is_owned_by($request, $currentUserId);
}));
$myActiveRequests = array_values(array_filter($myRequests, static function (array $request): bool {
    return strtolower((string)($request['status'] ?? 'draft')) !== 'archived';
}));
$archivedRequests = array_values(array_filter($allRequests, static function (array $request) use ($currentUserId, $canReviewContent): bool {
    return strtolower((string)($request['status'] ?? 'draft')) === 'archived'
        && cms_content_request_is_viewable_by($request, $currentUserId, $canReviewContent);
}));
$reviewQueue = array_values(array_filter($allRequests, static function (array $request) use ($canReviewContent): bool {
    return $canReviewContent && strtolower((string)($request['status'] ?? 'draft')) === 'pending';
}));
$draftCount = count(array_filter($myRequests, static fn(array $request): bool => strtolower((string)($request['status'] ?? '')) === 'draft'));
$myPendingCount = count(array_filter($myRequests, static fn(array $request): bool => strtolower((string)($request['status'] ?? '')) === 'pending'));
$myApprovedCount = count(array_filter($myRequests, static fn(array $request): bool => strtolower((string)($request['status'] ?? '')) === 'approved'));
$myDeniedCount = count(array_filter($myRequests, static fn(array $request): bool => strtolower((string)($request['status'] ?? '')) === 'denied'));
$myArchivedCount = count(array_filter($myRequests, static fn(array $request): bool => strtolower((string)($request['status'] ?? '')) === 'archived'));
$pendingReviewCount = count($reviewQueue);
$approvedHistoryRequests = array_values(array_filter($allRequests, static fn(array $request): bool => strtolower((string)($request['status'] ?? '')) === 'approved'));
$requestViewDefinitions = [
    'my_requests' => [
        'nav_label' => 'My Requests',
        'nav_icon' => 'fa-file-lines',
        'title' => 'My Content Requests',
        'description' => 'Track your drafts, submitted changes, and active CMS requests from the page editors.',
        'requests' => $myActiveRequests,
        'empty_message' => 'No active CMS requests yet. Start from any page editor to save a draft or submit changes.',
    ],
];
if ($canReviewContent) {
    $requestViewDefinitions['review_queue'] = [
        'nav_label' => 'Review Queue',
        'nav_icon' => 'fa-clipboard-check',
        'title' => 'Review Queue',
        'description' => 'Pending CMS requests waiting for approval or denial.',
        'requests' => $reviewQueue,
        'empty_message' => 'No pending content requests to review right now.',
    ];
}
$requestViewDefinitions['archived_requests'] = [
    'nav_label' => 'Archived Requests',
    'nav_icon' => 'fa-box-archive',
    'title' => $canReviewContent ? 'Archived Requests' : 'My Archived Requests',
    'description' => $canReviewContent
        ? 'Archived CMS requests that remain available for reference.'
        : 'Your archived CMS requests kept for reference.',
    'requests' => $archivedRequests,
    'empty_message' => $canReviewContent
        ? 'No archived CMS requests are available right now.'
        : 'You do not have any archived CMS requests right now.',
];
if ($canReviewContent) {
    $requestViewDefinitions['approved_history'] = [
        'nav_label' => 'Approved Version History',
        'nav_icon' => 'fa-clock-rotate-left',
        'title' => 'Approved Version History',
        'description' => 'Approved CMS versions sorted from newest to oldest.',
        'requests' => $approvedHistoryRequests,
        'empty_message' => 'No approved content versions are available yet.',
    ];
}
$contentRequestsView = strtolower(trim((string)($_GET['requests_view'] ?? 'my_requests')));
if (!isset($requestViewDefinitions[$contentRequestsView])) {
    $contentRequestsView = 'my_requests';
}
usort($approvedHistoryRequests, static function (array $left, array $right): int {
    return cms_content_request_sort_timestamp($right) <=> cms_content_request_sort_timestamp($left);
});
usort($myActiveRequests, static function (array $left, array $right): int {
    return cms_content_request_sort_timestamp($right) <=> cms_content_request_sort_timestamp($left);
});
usort($archivedRequests, static function (array $left, array $right): int {
    return cms_content_request_sort_timestamp($right) <=> cms_content_request_sort_timestamp($left);
});
$requestViewDefinitions['my_requests']['requests'] = $myActiveRequests;
if ($canReviewContent) {
    $requestViewDefinitions['review_queue']['requests'] = $reviewQueue;
}
$requestViewDefinitions['archived_requests']['requests'] = $archivedRequests;
if ($canReviewContent) {
    $requestViewDefinitions['approved_history']['requests'] = $approvedHistoryRequests;
}

$approvedRequestsByPage = [];
foreach ($approvedHistoryRequests as $request) {
    $pageKey = (string)($request['page_key'] ?? '');
    if ($pageKey === '') {
        continue;
    }
    $approvedRequestsByPage[$pageKey][] = $request;
}

$requestVersionMeta = [];
foreach ($approvedRequestsByPage as $pageKey => $requests) {
    $livePayloadForPage = cms_content_page($conn, $pageKey);
    $liveRequestId = '';

    foreach ($requests as $request) {
        if (cms_content_payloads_match($pageKey, $livePayloadForPage, (array)($request['content'] ?? []))) {
            $liveRequestId = (string)($request['request_id'] ?? '');
            break;
        }
    }

    foreach ($requests as $index => $request) {
        $currentRequestId = (string)($request['request_id'] ?? '');
        $previousRequestId = (string)($requests[$index + 1]['request_id'] ?? '');
        $isLiveVersion = $liveRequestId !== '' && $currentRequestId === $liveRequestId;

        $requestVersionMeta[$currentRequestId] = [
            'is_live' => $isLiveVersion,
            'previous_request_id' => $previousRequestId,
            'can_revert_to_this' => !$isLiveVersion,
            'can_revert_to_previous' => $isLiveVersion && $previousRequestId !== '',
        ];
    }
}

$flash = $_SESSION['cms_content_flash'] ?? null;
unset($_SESSION['cms_content_flash']);

$selectedRequestId = trim((string)($_GET['request_id'] ?? ''));
$editorRequest = null;
$editorPayload = [];
$livePayload = [];
$editorReadOnly = false;
$editorReadOnlyMessage = '';

if (in_array($selectedModuleKey, cms_content_editable_page_keys(), true)) {
    $livePayload = $selectedModuleKey === 'home'
        ? cms_content_page_with_context($conn, $selectedModuleKey)
        : cms_content_page($conn, $selectedModuleKey);
    $editorPayload = $livePayload;

    if ($selectedRequestId !== '') {
        $request = cms_content_request($conn, $selectedRequestId);
        if (
            $request
            && (string)($request['page_key'] ?? '') === $selectedModuleKey
            && cms_content_request_is_viewable_by($request, $currentUserId, $canReviewContent)
        ) {
            $editorRequest = $request;
            $editorPayload = cms_content_payload_with_context($conn, $selectedModuleKey, (array)($request['content'] ?? []));
            if (!cms_content_request_is_editable_by($request, $currentUserId)) {
                $editorReadOnly = true;
                $editorStatus = strtolower(trim((string)($request['status'] ?? 'draft')));
                $editorReadOnlyMessage = match ($editorStatus) {
                    'pending' => 'This request is already pending review. You can preview it here, and authorized reviewers can approve or deny it from the request queue.',
                    'approved' => 'This approved request is read-only. Start a new edit from the current live content when you need another change.',
                    'archived' => 'This request is archived. You can review it here and restore it from the request tracker when needed.',
                    default => 'This request is read-only.',
                };
            }
        }
    }
}

$editorReviewAvailable = $editorRequest && $canReviewContent && strtolower((string)($editorRequest['status'] ?? 'draft')) === 'pending';

$pageDefinitions = cms_content_page_definitions();
$announcementRatio = (array)($pageDefinitions['announcements']['image_fields']['banner_image']['ratio'] ?? [4500, 1281]);
$homeBannerRatio = (array)($pageDefinitions['home']['image_fields']['banner_image']['ratio'] ?? [1500, 800]);
$homeAboutRatio = (array)($pageDefinitions['home']['image_fields']['about_image']['ratio'] ?? [1125, 1575]);
$governmentBannerRatio = (array)($pageDefinitions['government']['image_fields']['banner_image']['ratio'] ?? [1440, 410]);
$governmentPunongRatio = (array)($pageDefinitions['government']['image_fields']['punong_barangay_image']['ratio'] ?? [4000, 6000]);
$governmentOfficialRatio = (array)($pageDefinitions['government']['image_fields']['officials[*].image']['ratio'] ?? [4000, 6000]);
$servicesBannerRatio = (array)($pageDefinitions['services']['image_fields']['banner_image']['ratio'] ?? [4500, 1281]);
$faqBannerRatio = (array)($pageDefinitions['faq']['image_fields']['banner_image']['ratio'] ?? [1440, 410]);
$contactBannerRatio = (array)($pageDefinitions['contact']['image_fields']['banner_image']['ratio'] ?? [4499, 1281]);
$loginImageRatio = (array)($pageDefinitions['login']['image_fields']['login_image']['ratio'] ?? [1587, 2245]);
$registerImageRatio = (array)($pageDefinitions['login']['image_fields']['register_image']['ratio'] ?? [1587, 2245]);

function cms_nav_url(string $moduleKey, string $requestId = ''): string
{
    $query = ['module' => $moduleKey];
    if ($requestId !== '') {
        $query['request_id'] = $requestId;
    }
    return appUrl('Admin-End/Contents/ContentManagement.php') . '?' . http_build_query($query);
}

function cms_request_view_url(string $requestView = 'my_requests'): string
{
    $allowedViews = ['my_requests', 'review_queue', 'archived_requests', 'approved_history'];
    if (!in_array($requestView, $allowedViews, true)) {
        $requestView = 'my_requests';
    }

    return appUrl('Admin-End/Contents/ContentManagement.php') . '?' . http_build_query([
        'module' => 'requests',
        'requests_view' => $requestView,
    ]);
}

function cms_status_class(string $status): string
{
    return match (strtolower(trim($status))) {
        'live module', 'live editor' => 'is-live',
        'navigation' => 'is-nav',
        default => 'is-spec',
    };
}

function cms_request_status_class(string $status): string
{
    return match (strtolower(trim($status))) {
        'pending' => 'is-pending',
        'approved' => 'is-approved',
        'archived' => 'is-archived',
        'denied' => 'is-denied',
        default => 'is-draft',
    };
}

function cms_request_table_status_class(string $status): string
{
    return match (strtolower(trim($status))) {
        'pending' => 'pending',
        'approved' => 'approved',
        'archived' => 'archived',
        'denied' => 'denied',
        default => 'draft',
    };
}

function cms_format_datetime(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return 'Not yet';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('F d, Y g:i A', $timestamp);
}

function cms_render_request_action_form(string $action, string $requestId, string $label, string $buttonClass, string $confirmMessage): string
{
    ob_start();
    ?>
    <form method="post" action="../../PhpFiles/Admin-End/siteContentActions.php" class="d-inline" data-confirm="<?= htmlspecialchars($confirmMessage, ENT_QUOTES, 'UTF-8') ?>">
      <?= csrfTokenField() ?>
      <input type="hidden" name="action" value="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="request_id" value="<?= htmlspecialchars($requestId, ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="btn btn-sm compact-table-btn <?= htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars($label) ?>
      </button>
    </form>
    <?php
    return (string)ob_get_clean();
}

function cms_render_text_field(string $fieldKey, string $label, string $value = '', string $placeholder = '', string $help = '', bool $itemField = false): string
{
    $attribute = $itemField ? 'data-cms-item-field' : 'data-cms-field';
    ob_start();
    ?>
    <article class="cms-editor-card">
      <label class="form-label fw-semibold mb-2"><?= htmlspecialchars($label) ?></label>
      <?php if ($help !== ''): ?>
        <p class="cms-field-help"><?= htmlspecialchars($help) ?></p>
      <?php endif; ?>
      <input
        type="text"
        class="form-control cms-editor-input"
        value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
        <?= $attribute ?>="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>">
    </article>
    <?php
    return (string)ob_get_clean();
}

function cms_render_textarea_field(string $fieldKey, string $label, string $value = '', string $placeholder = '', string $help = '', int $rows = 3, bool $itemField = false): string
{
    $attribute = $itemField ? 'data-cms-item-field' : 'data-cms-field';
    ob_start();
    ?>
    <article class="cms-editor-card">
      <label class="form-label fw-semibold mb-2"><?= htmlspecialchars($label) ?></label>
      <?php if ($help !== ''): ?>
        <p class="cms-field-help"><?= htmlspecialchars($help) ?></p>
      <?php endif; ?>
      <textarea
        class="form-control cms-editor-input"
        rows="<?= (int)$rows ?>"
        placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
        <?= $attribute ?>="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></textarea>
    </article>
    <?php
    return (string)ob_get_clean();
}

function cms_render_richtext_field(string $fieldKey, string $label, string $value = '', string $placeholder = '', string $help = '', bool $itemField = false, string $editorHeight = '240'): string
{
    $attribute = $itemField ? 'data-cms-item-field' : 'data-cms-field';
    ob_start();
    ?>
    <article class="cms-editor-card">
      <label class="form-label fw-semibold mb-2"><?= htmlspecialchars($label) ?></label>
      <?php if ($help !== ''): ?>
        <p class="cms-field-help"><?= htmlspecialchars($help) ?></p>
      <?php endif; ?>
      <div
        class="cms-richtext-editor"
        data-cms-editor-host
        data-placeholder="<?= htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') ?>"
        data-editor-height="<?= htmlspecialchars($editorHeight, ENT_QUOTES, 'UTF-8') ?>"
        data-initial-html="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"></div>
      <input type="hidden" <?= $attribute ?>="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
    </article>
    <?php
    return (string)ob_get_clean();
}

function cms_render_image_field(string $fieldKey, string $label, string $value, array $ratio, string $help = '', bool $itemField = false): string
{
    $attribute = $itemField ? 'data-cms-item-field' : 'data-cms-field';
    $ratioWidth = (int)($ratio[0] ?? 1);
    $ratioHeight = (int)($ratio[1] ?? 1);
    $previewUrl = cms_content_public_asset_url($value);
    ob_start();
    ?>
    <article class="cms-editor-card">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div>
          <h5 class="cms-editor-card-title mb-1"><?= htmlspecialchars($label) ?></h5>
          <?php if ($help !== ''): ?>
            <p class="cms-field-help mb-0"><?= htmlspecialchars($help) ?></p>
          <?php endif; ?>
        </div>
        <span class="cms-ratio-badge"><?= $ratioWidth ?>:<?= $ratioHeight ?></span>
      </div>
      <div
        class="cms-image-picker"
        data-cms-image-picker
        data-ratio-width="<?= $ratioWidth ?>"
        data-ratio-height="<?= $ratioHeight ?>">
        <div class="cms-image-preview-shell">
          <?php if ($previewUrl !== ''): ?>
            <img src="<?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" class="cms-image-preview" data-cms-image-preview>
          <?php else: ?>
            <div class="cms-image-preview-placeholder" data-cms-image-placeholder>Upload and crop an image for this section.</div>
            <img src="" alt="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" class="cms-image-preview d-none" data-cms-image-preview>
          <?php endif; ?>
        </div>
        <div class="cms-image-picker-actions">
          <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" data-cms-image-select>
            <i class="fa-solid fa-image me-2"></i>Upload and Crop
          </button>
          <span class="cms-access-note">Fixed live ratio: <?= $ratioWidth ?>:<?= $ratioHeight ?></span>
        </div>
        <input type="file" accept="image/*" class="d-none" data-cms-image-input>
        <input type="hidden" <?= $attribute ?>="<?= htmlspecialchars($fieldKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>">
      </div>
    </article>
    <?php
    return (string)ob_get_clean();
}

function cms_render_official_item(array $item, array $ratio): string
{
    ob_start();
    ?>
    <article class="cms-repeater-item" data-cms-repeater-item>
      <div class="cms-repeater-item-head">
        <h5 class="cms-repeater-title mb-0">Official</h5>
        <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" data-cms-repeater-remove>
          <i class="fa-solid fa-trash-can me-1"></i>Remove
        </button>
      </div>
      <div class="row g-3">
        <div class="col-12 col-lg-5">
          <?= cms_render_image_field('image', 'Official Image', (string)($item['image'] ?? ''), $ratio, 'Used on the Government page and the Home page council section.', true) ?>
        </div>
        <div class="col-12 col-lg-7">
          <div class="row g-3">
            <div class="col-12">
              <?= cms_render_text_field('name_html', 'Official Name', (string)($item['name_html'] ?? ''), 'Enter official name', '', true) ?>
            </div>
            <div class="col-12">
              <?= cms_render_text_field('position_html', 'Official Position', (string)($item['position_html'] ?? ''), 'Enter official position', '', true) ?>
            </div>
          </div>
        </div>
      </div>
    </article>
    <?php
    return (string)ob_get_clean();
}

function cms_render_area_item(array $item): string
{
    ob_start();
    ?>
    <article class="cms-repeater-item" data-cms-repeater-item>
      <div class="cms-repeater-item-head">
        <h5 class="cms-repeater-title mb-0">Area</h5>
        <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" data-cms-repeater-remove>
          <i class="fa-solid fa-trash-can me-1"></i>Remove
        </button>
      </div>
      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <?= cms_render_text_field('title_html', 'Area Number / Title', (string)($item['title_html'] ?? ''), 'Area 01', '', true) ?>
        </div>
        <div class="col-12 col-lg-8">
          <?= cms_render_textarea_field('description_html', 'Area Locations / Description', (string)($item['description_html'] ?? ''), 'Enter locations or description', '', 3, true) ?>
        </div>
      </div>
    </article>
    <?php
    return (string)ob_get_clean();
}

function cms_render_service_item(array $item): string
{
    $title = (string)($item['title_html'] ?? '');
    $description = (string)($item['description_html'] ?? '');
    ob_start();
    ?>
    <article class="cms-repeater-item cms-repeater-item--static" data-cms-repeater-item>
      <div class="cms-repeater-item-head">
        <h5 class="cms-repeater-title mb-0"><?= htmlspecialchars($title) ?></h5>
      </div>
      <input type="hidden" data-cms-item-field="title_html" value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
      <?= cms_render_richtext_field('description_html', 'Service Description', $description, 'Write the service description here...', '', true, '180') ?>
    </article>
    <?php
    return (string)ob_get_clean();
}

function cms_render_emergency_item(array $item): string
{
    ob_start();
    ?>
    <article class="cms-repeater-item" data-cms-repeater-item>
      <div class="cms-repeater-item-head">
        <h5 class="cms-repeater-title mb-0">Emergency Hotline</h5>
        <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" data-cms-repeater-remove>
          <i class="fa-solid fa-trash-can me-1"></i>Remove
        </button>
      </div>
      <div class="row g-3">
        <div class="col-12 col-lg-5">
          <?= cms_render_text_field('title_html', 'Area Title', (string)($item['title_html'] ?? ''), 'Area 01', '', true) ?>
        </div>
        <div class="col-12 col-lg-7">
          <?= cms_render_text_field('number_html', 'Hotline Number', (string)($item['number_html'] ?? ''), '+63 900 000 0000', '', true) ?>
        </div>
      </div>
    </article>
    <?php
    return (string)ob_get_clean();
}

function cms_render_contact_tile_item(array $item): string
{
    ob_start();
    ?>
    <article class="cms-repeater-item" data-cms-repeater-item>
      <div class="cms-repeater-item-head">
        <h5 class="cms-repeater-title mb-0">Area Hotline Tile</h5>
        <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" data-cms-repeater-remove>
          <i class="fa-solid fa-trash-can me-1"></i>Remove
        </button>
      </div>
      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <?= cms_render_text_field('title_html', 'Tile Title', (string)($item['title_html'] ?? ''), 'AREA 01', '', true) ?>
        </div>
        <div class="col-12 col-lg-4">
          <?= cms_render_textarea_field('location_html', 'Description / Location', (string)($item['location_html'] ?? ''), 'Enter location details', '', 2, true) ?>
        </div>
        <div class="col-12 col-lg-4">
          <?= cms_render_text_field('number_html', 'Hotline Number', (string)($item['number_html'] ?? ''), '+63 900 000 0000', '', true) ?>
        </div>
      </div>
    </article>
    <?php
    return (string)ob_get_clean();
}

function cms_render_faq_item(array $item, int $index = 0): string
{
    $question = (string)($item['question'] ?? '');
    $answer = (string)($item['answer'] ?? '');
    $placeholders = [
        'How do I request a barangay certificate or clearance?',
        'How do I apply for a Barangay ID?',
        'How do I schedule an appointment with the barangay office?',
        'How do I file a complaint with the barangay?',
        'How long does barangay document processing take?',
        'What documents are required for barangay services?',
    ];
    $placeholder = (string)($placeholders[$index % count($placeholders)] ?? 'Enter FAQ question');
    ob_start();
    ?>
    <article class="cms-repeater-item" data-cms-repeater-item data-cms-faq-item>
      <div class="cms-repeater-item-head">
        <h5 class="cms-repeater-title mb-0" data-cms-faq-title>Question <?= (int)$index + 1 ?></h5>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" data-cms-faq-add>
            <i class="fa-solid fa-plus me-1"></i>Add
          </button>
          <button type="button" class="btn btn-outline-danger btn-sm fw-semibold" data-cms-faq-remove>
            <i class="fa-solid fa-trash-can me-1"></i>Remove
          </button>
        </div>
      </div>
      <div class="row g-3">
        <div class="col-12">
          <?= cms_render_text_field('question', 'Question', $question, $placeholder, '', true) ?>
        </div>
        <div class="col-12">
          <?= cms_render_richtext_field('answer', 'Answer', $answer, 'Write the answer here...', '', true, '180') ?>
        </div>
      </div>
    </article>
    <?php
    return (string)ob_get_clean();
}

function cms_render_request_table(array $requests, string $emptyMessage, string $currentUserId, bool $canReviewContent, array $requestVersionMeta = []): string
{
    ob_start();
    ?>
    <div class="table-responsive compact-admin-table-shell">
      <table class="table align-middle mb-0 compact-admin-table compact-admin-table--wide cms-request-table">
        <thead>
          <tr class="table-light">
            <th>Request Info</th>
            <th>Created By</th>
            <th>Status</th>
            <th>Updated</th>
            <th>Submitted</th>
            <th>Reviewed</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$requests): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4"><?= htmlspecialchars($emptyMessage) ?></td>
            </tr>
          <?php else: ?>
            <?php foreach ($requests as $request): ?>
              <?php
                $status = strtolower(trim((string)($request['status'] ?? 'draft')));
                $requestId = (string)($request['request_id'] ?? '');
                $pageKey = (string)($request['page_key'] ?? 'home');
                $pageLabel = (string)($request['page_label'] ?? cms_content_page_label($pageKey));
                $reviewNote = trim((string)($request['review_note'] ?? ''));
                $archivedFromStatus = trim((string)($request['archived_from_status'] ?? ''));
                $archivedAt = trim((string)($request['archived_at'] ?? ''));
                $archivedByLabel = trim((string)($request['archived_by_label'] ?? ''));
                $filterStatus = $status === 'archived' && $archivedFromStatus !== '' ? strtolower($archivedFromStatus) : $status;
                $searchValue = strtolower(implode(' ', [
                    $requestId,
                    $pageKey,
                    $pageLabel,
                    (string)($request['created_by_label'] ?? ''),
                    (string)($request['created_by_role'] ?? ''),
                    $status,
                    $archivedFromStatus,
                    (string)($request['updated_at'] ?? ''),
                    (string)($request['submitted_at'] ?? ''),
                    (string)($request['reviewed_at'] ?? ''),
                ]));
                $versionMeta = $requestVersionMeta[$requestId] ?? [];
                $isLiveVersion = (bool)($versionMeta['is_live'] ?? false);
                $canArchive = $status !== 'denied'
                    && cms_content_request_is_archivable_by($request, $currentUserId, $canReviewContent, $isLiveVersion);
              ?>
              <tr
                data-cms-request-row
                data-cms-request-status="<?= htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8') ?>"
                data-cms-request-search="<?= htmlspecialchars($searchValue, ENT_QUOTES, 'UTF-8') ?>">
                <td>
                  <div class="cms-request-table-primary"><?= htmlspecialchars($pageLabel) ?></div>
                </td>
                <td>
                  <div class="cms-request-table-primary"><?= htmlspecialchars((string)($request['created_by_label'] ?? '-')) ?></div>
                  <?php if (trim((string)($request['created_by_role'] ?? '')) !== ''): ?>
                    <div class="cms-request-table-meta"><?= htmlspecialchars((string)$request['created_by_role']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-2">
                    <span class="status-pill <?= htmlspecialchars(cms_request_table_status_class($status)) ?>">
                      <?= htmlspecialchars(ucfirst($status)) ?>
                    </span>
                    <?php if ($isLiveVersion): ?>
                      <span class="status-pill live">Current Live</span>
                    <?php endif; ?>
                    <?php if ($status === 'archived' && $archivedFromStatus !== ''): ?>
                      <span class="status-pill <?= htmlspecialchars(cms_request_table_status_class($archivedFromStatus)) ?>">From <?= htmlspecialchars(ucfirst($archivedFromStatus)) ?></span>
                    <?php endif; ?>
                  </div>
                </td>
                <td><?= htmlspecialchars(cms_format_datetime((string)($request['updated_at'] ?? ''))) ?></td>
                <td><?= htmlspecialchars(cms_format_datetime((string)($request['submitted_at'] ?? ''))) ?></td>
                <td><?= htmlspecialchars(cms_format_datetime((string)($request['reviewed_at'] ?? ''))) ?></td>
                <td>
                  <div class="compact-table-actions cms-request-table-actions">
                    <a href="<?= htmlspecialchars(cms_nav_url($pageKey, $requestId)) ?>" class="btn btn-primary btn-sm compact-table-btn">View</a>
                    <?php if ($canArchive): ?>
                      <?= cms_render_request_action_form('archive_request', $requestId, 'Archive', 'btn-warning', 'Archive this content request? You can restore it later from the tracker.') ?>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          <tr class="d-none" data-cms-request-no-results>
            <td colspan="7" class="text-center text-muted py-4">No content requests match the selected status and search.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php
    return (string)ob_get_clean();
}

$previewAssetBase = rtrim(appUrl('/'), '/') . '/';
$previewRuntimeJs = appUrl('JS-Script-Files/siteContentRuntime.js');
$previewCssAssets = [
    'bootstrap' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css',
    'guest' => appUrl('CSS-Styles/Guest-End-CSS/GuestPage.css'),
    'home' => appUrl('CSS-Styles/Guest-End-CSS/HomePage.css'),
    'news' => appUrl('CSS-Styles/Guest-End-CSS/NewsModule.css'),
    'faq' => appUrl('CSS-Styles/Guest-End-CSS/FAQSModule.css'),
    'contact' => appUrl('CSS-Styles/Guest-End-CSS/ContactModule.css'),
    'login' => appUrl('CSS-Styles/Guest-End-CSS/LoginModule.css'),
    'navbar' => appUrl('CSS-Styles/NavbarFooterStyle.css'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Content Management System</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../../summernote-0.9.0-dist/summernote-lite.min.css?v=20260307-2" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ResidentMasterlistStyle.css?v=20260321-2">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ContentNavigator.css?v=20260722-4">
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light cms-page-root">
      <h2 class="mb-4" style="font-family: 'Charis SIL Bold'; color: #DE710C;">
        Content Management System
      </h2>
      <hr class="mb-4">

      <?php if (is_array($flash) && !empty($flash['message'])): ?>
        <div class="alert alert-<?= htmlspecialchars((string)($flash['type'] ?? 'info')) ?> shadow-sm border-0 mb-4">
          <?= htmlspecialchars((string)$flash['message']) ?>
        </div>
      <?php endif; ?>

      <?php if ($selectedModuleKey === 'requests'): ?>
        <ul class="nav nav-tabs mb-0 cms-request-view-tabs" aria-label="Content request views">
          <?php foreach ($requestViewDefinitions as $requestViewKey => $requestViewMeta): ?>
            <?php $isRequestViewActive = $contentRequestsView === $requestViewKey; ?>
            <li class="nav-item">
              <button
                type="button"
                class="nav-link fw-semibold <?= $isRequestViewActive ? 'active' : '' ?>"
                data-cms-request-tab="<?= htmlspecialchars($requestViewKey, ENT_QUOTES, 'UTF-8') ?>"
                data-cms-request-url="<?= htmlspecialchars(cms_request_view_url($requestViewKey), ENT_QUOTES, 'UTF-8') ?>"
                aria-controls="cms-request-panel-<?= htmlspecialchars($requestViewKey, ENT_QUOTES, 'UTF-8') ?>"
                aria-selected="<?= $isRequestViewActive ? 'true' : 'false' ?>">
                <i class="fa-solid <?= htmlspecialchars((string)$requestViewMeta['nav_icon']) ?> me-1" aria-hidden="true"></i>
                <?= htmlspecialchars((string)$requestViewMeta['nav_label']) ?>
                <?php if ($requestViewKey === 'review_queue' && $pendingReviewCount > 0): ?>
                  <span class="pending-count-badge"><?= (int)$pendingReviewCount ?></span>
                <?php endif; ?>
              </button>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php foreach ($requestViewDefinitions as $requestViewKey => $requestViewMeta): ?>
          <?php
            $isRequestViewActive = $contentRequestsView === $requestViewKey;
            $requestViewCount = count((array)$requestViewMeta['requests']);
            $requestViewStatusCounts = ['approved' => 0, 'denied' => 0, 'pending' => 0];
            foreach ((array)$requestViewMeta['requests'] as $requestForCount) {
                $requestStatusForCount = strtolower(trim((string)($requestForCount['status'] ?? 'draft')));
                if ($requestStatusForCount === 'archived') {
                    $requestStatusForCount = strtolower(trim((string)($requestForCount['archived_from_status'] ?? 'archived')));
                }
                if (isset($requestViewStatusCounts[$requestStatusForCount])) {
                    $requestViewStatusCounts[$requestStatusForCount]++;
                }
            }
          ?>
          <section
            id="cms-request-panel-<?= htmlspecialchars($requestViewKey, ENT_QUOTES, 'UTF-8') ?>"
            class="bg-white p-4 rounded-4 shadow-sm border resident-masterlist-shell cms-request-tracker-shell mb-4 <?= $isRequestViewActive ? '' : 'd-none' ?>"
            data-cms-request-panel="<?= htmlspecialchars($requestViewKey, ENT_QUOTES, 'UTF-8') ?>">
            <div class="cms-request-tracker-header mb-3">
              <div>
                <h3 class="cms-section-title mb-1"><?= htmlspecialchars((string)$requestViewMeta['title']) ?></h3>
                <p class="small text-muted mb-0"><?= htmlspecialchars((string)$requestViewMeta['description']) ?></p>
              </div>
              <span class="badge rounded-pill text-bg-light border cms-request-tracker-badge" data-cms-visible-entry-badge>
                <?= (int)$requestViewCount ?> <?= $requestViewCount === 1 ? 'entry' : 'entries' ?>
              </span>
            </div>

            <div class="admin-list-toolbar mb-3 flex-wrap" data-cms-request-toolbar>
              <div class="admin-list-tabs">
                <button type="button" class="btn btn-outline-primary btn-sm status-filter-btn fw-semibold active px-3" data-filter="ALL" data-cms-status-filter="all">All</button>
                <?php foreach (['approved' => 'Approved', 'denied' => 'Denied', 'pending' => 'Pending'] as $statusFilterKey => $statusFilterLabel): ?>
                  <?php $statusFilterCount = (int)$requestViewStatusCounts[$statusFilterKey]; ?>
                  <button type="button" class="btn btn-outline-secondary btn-sm status-filter-btn fw-semibold px-3 <?= $requestViewKey !== 'archived_requests' && $statusFilterKey === 'pending' && $statusFilterCount > 0 ? 'has-notif' : '' ?>" data-filter="<?= strtoupper($statusFilterKey) ?>" data-cms-status-filter="<?= $statusFilterKey ?>">
                    <?= $statusFilterLabel ?>
                    <?php if ($requestViewKey !== 'archived_requests' && $statusFilterKey === 'pending' && $statusFilterCount > 0): ?>
                      <span class="pending-count-badge"><?= $statusFilterCount ?></span>
                    <?php endif; ?>
                  </button>
                <?php endforeach; ?>
              </div>
              <div class="admin-list-actions">
                <div class="input-group admin-search">
                  <input
                    type="search"
                    class="form-control"
                    placeholder="Request ID, page, creator, status"
                    aria-label="Search content requests"
                    data-cms-request-search-input>
                  <button class="btn btn-outline-secondary bg-white" type="button" title="Search" aria-label="Search" data-cms-request-search-button><i class="fas fa-search" aria-hidden="true"></i></button>
                </div>
                <button class="btn btn-outline-secondary btn-icon admin-filter" type="button" data-bs-toggle="modal" data-bs-target="#cmsRequestFilterModal" title="Filter" aria-label="Filter">
                  <i class="fas fa-filter" aria-hidden="true"></i>
                </button>
                <button class="btn btn-outline-primary btn-icon admin-columns" type="button" data-bs-toggle="modal" data-bs-target="#cmsRequestColumnsModal" title="Columns" aria-label="Columns">
                  <i class="fa-solid fa-sliders" aria-hidden="true"></i>
                </button>
                <button class="btn btn-outline-warning btn-icon admin-refresh" type="button" title="Refresh tracker" aria-label="Refresh tracker" data-cms-refresh-list>
                  <i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i>
                </button>
              </div>
            </div>

            <?= cms_render_request_table(
              (array)$requestViewMeta['requests'],
              (string)$requestViewMeta['empty_message'],
              $currentUserId,
              $canReviewContent,
              $requestVersionMeta
            ) ?>

            <div class="resident-table-footer mt-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
              <div class="d-flex align-items-center gap-2">
                <label class="small text-muted mb-0">Entries</label>
                <span class="small fw-semibold" data-cms-visible-entry-count><?= (int)$requestViewCount ?></span>
                <span class="badge rounded-pill bg-light text-secondary border" data-cms-visible-entry-summary>
                  Showing <?= (int)$requestViewCount ?> <?= $requestViewCount === 1 ? 'request' : 'requests' ?>
                </span>
              </div>
            </div>
          </section>
        <?php endforeach; ?>
      <?php else: ?>
        <?php
        $selectedRequestStatus = strtolower(trim((string)($editorRequest['status'] ?? '')));
        ?>
        <section class="cms-section-card">
          <div class="cms-detail-header cms-detail-header--editor">
            <div class="cms-detail-icon">
              <i class="fa-solid <?= htmlspecialchars((string)$selectedModule['icon']) ?>"></i>
            </div>
            <div class="cms-detail-copy">
              <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h3 class="cms-section-title mb-0"><?= htmlspecialchars((string)$selectedModule['title']) ?></h3>
                <?php if ($editorRequest): ?>
                  <span class="cms-request-status <?= htmlspecialchars(cms_request_status_class($selectedRequestStatus)) ?>">
                    <?= htmlspecialchars(ucfirst($selectedRequestStatus)) ?> Request
                  </span>
                <?php endif; ?>
              </div>
              <?php if ($editorRequest): ?>
                <p class="cms-request-meta mb-0">
                  Request ID: <?= htmlspecialchars((string)$editorRequest['request_id']) ?> |
                  Created by: <?= htmlspecialchars((string)($editorRequest['created_by_label'] ?? '-')) ?>
                </p>
              <?php endif; ?>
            </div>
            <button
              type="button"
              class="btn btn-outline-primary fw-semibold btn-sm ms-auto"
              data-bs-toggle="modal"
              data-bs-target="#cmsPreviewModal"
              data-cms-open-preview>
              View Preview
            </button>
          </div>

          <?php if ($editorReadOnlyMessage !== ''): ?>
            <div class="alert alert-warning border-0 shadow-sm mb-4">
              <?= htmlspecialchars($editorReadOnlyMessage) ?>
            </div>
          <?php endif; ?>

          <?php if ($editorReviewAvailable): ?>
            <article class="cms-detail-panel mb-4">
              <h4 class="cms-detail-panel-title">Review Actions</h4>
              <div class="cms-detail-actions">
                <form method="post" action="../../PhpFiles/Admin-End/siteContentActions.php" data-confirm="Approve and publish this content request?">
                  <?= csrfTokenField() ?>
                  <input type="hidden" name="action" value="approve_request">
                  <input type="hidden" name="request_id" value="<?= htmlspecialchars((string)$editorRequest['request_id'], ENT_QUOTES, 'UTF-8') ?>">
                  <button type="submit" class="btn btn-success btn-sm fw-semibold">Approve</button>
                </form>
                <form method="post" action="../../PhpFiles/Admin-End/siteContentActions.php" data-confirm="Deny this content request?">
                  <?= csrfTokenField() ?>
                  <input type="hidden" name="action" value="deny_request">
                  <input type="hidden" name="request_id" value="<?= htmlspecialchars((string)$editorRequest['request_id'], ENT_QUOTES, 'UTF-8') ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm fw-semibold">Deny</button>
                </form>
              </div>
            </article>
          <?php endif; ?>

          <form id="cmsEditorForm" method="post" action="../../PhpFiles/Admin-End/siteContentActions.php" class="cms-editor-form">
                <?= csrfTokenField() ?>
                <input type="hidden" name="action" id="cmsActionInput" value="save_draft">
                <input type="hidden" name="page_key" value="<?= htmlspecialchars($selectedModuleKey, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="request_id" value="<?= htmlspecialchars((string)($editorRequest['request_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="redirect_module" value="<?= htmlspecialchars($selectedModuleKey, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="payload_json" id="cmsPayloadInput" value="">

                <?php if ($selectedModuleKey === 'announcements'): ?>
                  <div class="row g-3">
                    <div class="col-12">
                      <?= cms_render_image_field('banner_image', 'Banner Image', (string)($editorPayload['banner_image'] ?? ''), $announcementRatio, 'Crop to the current News page banner ratio before saving.') ?>
                    </div>
                    <div class="col-12">
                      <?= cms_render_richtext_field('banner_title_html', 'Banner Title', (string)($editorPayload['banner_title_html'] ?? ''), 'Write the News page title here...', '', false, '170') ?>
                    </div>
                    <div class="col-12">
                      <?= cms_render_richtext_field('banner_message_html', 'Banner Message', (string)($editorPayload['banner_message_html'] ?? ''), 'Write the News page message here...', '', false, '200') ?>
                    </div>
                  </div>
                <?php elseif ($selectedModuleKey === 'home'): ?>
                  <div class="row g-3">
                    <div class="col-12">
                      <?= cms_render_image_field('banner_image', 'Banner Image', (string)($editorPayload['banner_image'] ?? ''), $homeBannerRatio, 'Crop to the current Home page banner ratio before saving.') ?>
                    </div>
                    <div class="col-12">
                      <?= cms_render_richtext_field('about_message_html', 'About Message', (string)($editorPayload['about_message_html'] ?? ''), 'Write the About Us message here...') ?>
                    </div>
                    <div class="col-12">
                      <?= cms_render_image_field('about_image', 'About Us Image', (string)($editorPayload['about_image'] ?? ''), $homeAboutRatio, 'Crop to match the current About Us portrait ratio.') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('mission_message_html', 'Mission Message', (string)($editorPayload['mission_message_html'] ?? ''), 'Write the mission message here...', '', false, '220') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('vision_message_html', 'Vision Message', (string)($editorPayload['vision_message_html'] ?? ''), 'Write the vision message here...', '', false, '220') ?>
                    </div>
                    <div class="col-12">
                      <?= cms_render_richtext_field('history_message_html', 'Our History', (string)($editorPayload['history_message_html'] ?? ''), 'Write the history section here...') ?>
                    </div>
                  </div>
                <?php elseif ($selectedModuleKey === 'government'): ?>
                  <div class="row g-3">
                    <div class="col-12">
                      <?= cms_render_image_field('banner_image', 'Banner Image', (string)($editorPayload['banner_image'] ?? ''), $governmentBannerRatio, 'Crop to the current Government page banner ratio before saving.') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('banner_title_html', 'Banner Title', (string)($editorPayload['banner_title_html'] ?? ''), 'Write the banner title here...', '', false, '170') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('banner_message_html', 'Banner Message', (string)($editorPayload['banner_message_html'] ?? ''), 'Write the banner message here...', '', false, '170') ?>
                    </div>
                    <div class="col-12 col-xl-5">
                      <?= cms_render_image_field('punong_barangay_image', 'Punong Barangay Image', (string)($editorPayload['punong_barangay_image'] ?? ''), $governmentPunongRatio, 'Crop to the current Punong Barangay portrait ratio.') ?>
                    </div>
                    <div class="col-12 col-xl-7">
                      <div class="row g-3">
                        <div class="col-12">
                          <?= cms_render_text_field('punong_barangay_name_html', 'Punong Barangay Name', (string)($editorPayload['punong_barangay_name_html'] ?? ''), 'Enter the Punong Barangay name') ?>
                        </div>
                        <div class="col-12">
                          <?= cms_render_text_field('punong_barangay_position_html', 'Punong Barangay Position', (string)($editorPayload['punong_barangay_position_html'] ?? ''), 'Enter the Punong Barangay position') ?>
                        </div>
                        <div class="col-12">
                          <?= cms_render_richtext_field('punong_barangay_welcome_message_html', 'Welcome Message', (string)($editorPayload['punong_barangay_welcome_message_html'] ?? ''), 'Write the welcome message here...') ?>
                        </div>
                      </div>
                    </div>
                    <div class="col-12">
                      <section class="cms-editor-group">
                        <div class="cms-editor-group-head">
                          <div>
                            <h4 class="cms-detail-panel-title mb-1">Barangay Officials</h4>
                            <p class="cms-field-help mb-0">Edit official images, names, and positions. These also drive the Home page council section after approval.</p>
                          </div>
                          <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" data-cms-repeater-add-target="government-officials">
                            <i class="fa-solid fa-plus me-1"></i>Add Official
                          </button>
                        </div>
                        <div class="cms-repeater-stack" data-cms-repeater="officials" id="government-officials">
                          <?php foreach ((array)($editorPayload['officials'] ?? []) as $official): ?>
                            <?= cms_render_official_item((array)$official, $governmentOfficialRatio) ?>
                          <?php endforeach; ?>
                        </div>
                        <template id="cms-template-government-officials"><?= cms_render_official_item(['name_html' => '', 'position_html' => '', 'image' => ''], $governmentOfficialRatio) ?></template>
                      </section>
                    </div>
                    <div class="col-12">
                      <section class="cms-editor-group">
                        <div class="cms-editor-group-head">
                          <div>
                            <h4 class="cms-detail-panel-title mb-1">Area Listings</h4>
                            <p class="cms-field-help mb-0">Edit the area number or title together with the location or description shown on the Government page.</p>
                          </div>
                          <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" data-cms-repeater-add-target="government-areas">
                            <i class="fa-solid fa-plus me-1"></i>Add Area
                          </button>
                        </div>
                        <div class="cms-repeater-stack" data-cms-repeater="areas" id="government-areas">
                          <?php foreach ((array)($editorPayload['areas'] ?? []) as $area): ?>
                            <?= cms_render_area_item((array)$area) ?>
                          <?php endforeach; ?>
                        </div>
                        <template id="cms-template-government-areas"><?= cms_render_area_item(['title_html' => '', 'description_html' => '']) ?></template>
                      </section>
                    </div>
                  </div>
                <?php elseif ($selectedModuleKey === 'services'): ?>
                  <div class="row g-3">
                    <div class="col-12">
                      <?= cms_render_image_field('banner_image', 'Banner Image', (string)($editorPayload['banner_image'] ?? ''), $servicesBannerRatio, 'Crop to the current Services page banner ratio before saving.') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('banner_title_html', 'Banner Title', (string)($editorPayload['banner_title_html'] ?? ''), 'Write the banner title here...', '', false, '170') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('banner_message_html', 'Banner Message', (string)($editorPayload['banner_message_html'] ?? ''), 'Write the banner message here...', '', false, '170') ?>
                    </div>
                    <div class="col-12">
                      <section class="cms-editor-group">
                        <div class="cms-editor-group-head">
                          <div>
                            <h4 class="cms-detail-panel-title mb-1">Service Descriptions</h4>
                            <p class="cms-field-help mb-0">Each service keeps its current card title while the description remains editable.</p>
                          </div>
                        </div>
                        <div class="cms-repeater-stack" data-cms-repeater="services">
                          <?php foreach ((array)($editorPayload['services'] ?? []) as $service): ?>
                            <?= cms_render_service_item((array)$service) ?>
                          <?php endforeach; ?>
                        </div>
                      </section>
                    </div>
                  </div>
                <?php elseif ($selectedModuleKey === 'faq'): ?>
                  <div class="row g-3">
                    <div class="col-12">
                      <?= cms_render_image_field('banner_image', 'Banner Image', (string)($editorPayload['banner_image'] ?? ''), $faqBannerRatio, 'Crop to the current FAQ page banner ratio before saving.') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('banner_title_html', 'Banner Title', (string)($editorPayload['banner_title_html'] ?? ''), 'Write the FAQ banner title here...', '', false, '170') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('banner_message_html', 'Banner Message', (string)($editorPayload['banner_message_html'] ?? ''), 'Write the FAQ banner message here...', '', false, '170') ?>
                    </div>
                    <div class="col-12">
                      <section class="announcement-section-card announcement-faq-shell">
                        <div id="cmsFaqItemsContainer" class="announcement-faq-list cms-repeater-stack" data-cms-repeater="faq_items">
                          <?php
                          $faqItems = (array)($editorPayload['faq_items'] ?? []);
                          if (!$faqItems) {
                              $faqItems = [['question' => '', 'answer' => '']];
                          }
                          foreach ($faqItems as $index => $faqItem):
                          ?>
                            <?= cms_render_faq_item((array)$faqItem, (int)$index) ?>
                          <?php endforeach; ?>
                        </div>
                        <template id="cms-template-cmsFaqItemsContainer"><?= cms_render_faq_item(['question' => '', 'answer' => ''], 0) ?></template>
                      </section>
                    </div>
                  </div>
                <?php elseif ($selectedModuleKey === 'contact'): ?>
                  <div class="row g-3">
                    <div class="col-12">
                      <?= cms_render_image_field('banner_image', 'Banner Image', (string)($editorPayload['banner_image'] ?? ''), $contactBannerRatio, 'Crop to the current Contact page banner ratio before saving.') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('banner_title_html', 'Banner Title', (string)($editorPayload['banner_title_html'] ?? ''), 'Write the Contact banner title here...', '', false, '170') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('banner_message_html', 'Banner Message', (string)($editorPayload['banner_message_html'] ?? ''), 'Write the Contact banner message here...', '', false, '170') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('emergency_title_html', 'Emergency Hotlines Title', (string)($editorPayload['emergency_title_html'] ?? ''), 'Write the emergency title here...', '', false, '180') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_richtext_field('emergency_description_html', 'Emergency Hotlines Description', (string)($editorPayload['emergency_description_html'] ?? ''), 'Write the emergency description here...', '', false, '180') ?>
                    </div>
                    <div class="col-12">
                      <section class="cms-editor-group">
                        <div class="cms-editor-group-head">
                          <div>
                            <h4 class="cms-detail-panel-title mb-1">Per Area Emergency Hotlines</h4>
                            <p class="cms-field-help mb-0">Edit the quick emergency hotline list shown below the emergency section heading.</p>
                          </div>
                          <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" data-cms-repeater-add-target="contact-emergency">
                            <i class="fa-solid fa-plus me-1"></i>Add Hotline
                          </button>
                        </div>
                        <div class="cms-repeater-stack" data-cms-repeater="emergency_hotlines" id="contact-emergency">
                          <?php foreach ((array)($editorPayload['emergency_hotlines'] ?? []) as $item): ?>
                            <?= cms_render_emergency_item((array)$item) ?>
                          <?php endforeach; ?>
                        </div>
                        <template id="cms-template-contact-emergency"><?= cms_render_emergency_item(['title_html' => '', 'number_html' => '']) ?></template>
                      </section>
                    </div>
                    <div class="col-12">
                      <section class="cms-editor-group">
                        <div class="cms-editor-group-head">
                          <div>
                            <h4 class="cms-detail-panel-title mb-1">Area Hotline Tiles</h4>
                            <p class="cms-field-help mb-0">Add, remove, and update hotline tiles for each location.</p>
                          </div>
                          <button type="button" class="btn btn-outline-primary btn-sm fw-semibold" data-cms-repeater-add-target="contact-area-hotlines">
                            <i class="fa-solid fa-plus me-1"></i>Add Tile
                          </button>
                        </div>
                        <div class="cms-repeater-stack" data-cms-repeater="area_hotlines" id="contact-area-hotlines">
                          <?php foreach ((array)($editorPayload['area_hotlines'] ?? []) as $item): ?>
                            <?= cms_render_contact_tile_item((array)$item) ?>
                          <?php endforeach; ?>
                        </div>
                        <template id="cms-template-contact-area-hotlines"><?= cms_render_contact_tile_item(['title_html' => '', 'location_html' => '', 'number_html' => '']) ?></template>
                      </section>
                    </div>
                  </div>
                <?php elseif ($selectedModuleKey === 'login'): ?>
                  <div class="row g-3">
                    <div class="col-12 col-xl-6">
                      <?= cms_render_image_field('login_image', 'Login Image', (string)($editorPayload['login_image'] ?? ''), $loginImageRatio, 'Crop to the current login-side portrait ratio.') ?>
                    </div>
                    <div class="col-12 col-xl-6">
                      <?= cms_render_image_field('register_image', 'Register Image', (string)($editorPayload['register_image'] ?? ''), $registerImageRatio, 'Crop to the current register-side portrait ratio.') ?>
                    </div>
                  </div>
                <?php endif; ?>

                <div class="cms-editor-actions mt-4">
                  <?php if (!$editorReadOnly): ?>
                    <button type="button" class="btn btn-outline-primary fw-semibold" data-submit-action="save_draft" data-confirm="Save these content changes as a draft?">
                      Save Draft
                    </button>
                    <button type="button" class="btn btn-primary fw-semibold" data-submit-action="submit_request" data-confirm="Submit these content changes for review?">
                      Submit Changes
                    </button>
                    <?php if ($canReviewContent): ?>
                      <button type="button" class="btn btn-success fw-semibold" data-submit-action="auto_approve" data-confirm="Auto-approve and publish these content changes now?">
                        Auto-Approve and Publish
                      </button>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="cms-access-note">This request is read-only from the page editor.</span>
                  <?php endif; ?>
                </div>
          </form>
        </section>
      <?php endif; ?>
    </main>
  </div>

  <div class="modal fade" id="cmsImageCropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-1">Crop Image</h5>
            <p class="text-muted small mb-0">Drag the image and adjust the zoom so it fits the fixed live ratio.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="cms-crop-shell">
            <div class="cms-crop-canvas-shell">
              <div class="cms-crop-frame" id="cmsCropFrame">
                <img id="cmsCropImage" alt="Crop preview">
              </div>
            </div>
            <div class="cms-crop-controls">
              <div class="cms-crop-control-card">
                <label for="cmsCropZoom" class="form-label fw-semibold">Zoom</label>
                <input type="range" class="form-range" id="cmsCropZoom" min="1" max="4" step="0.01" value="1">
              </div>
              <div class="cms-crop-control-card">
                <h6 class="fw-semibold mb-2">Current Ratio</h6>
                <div class="cms-ratio-badge" id="cmsCropRatioLabel">1:1</div>
              </div>
              <div class="cms-crop-control-card">
                <h6 class="fw-semibold mb-2">Tip</h6>
                <p class="text-muted small mb-0">The full image can be any size. The saved version will use only the cropped area inside this fixed frame.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="cmsCropSaveBtn">Save Crop</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="cmsPreviewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title">Page Preview</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="cms-preview-stage cms-preview-stage--modal" id="cmsPreviewStage">
            <iframe
              id="cmsPreviewFrame"
              class="cms-preview-frame"
              title="CMS Page Preview"
              loading="lazy"></iframe>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="cmsRequestFilterModal" tabindex="-1" aria-labelledby="cmsRequestFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title" id="cmsRequestFilterModalLabel">Filter Content Requests</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label class="form-label fw-semibold">Status</label>
          <div class="d-grid gap-2">
            <?php foreach (['all' => 'All statuses', 'approved' => 'Approved', 'denied' => 'Denied', 'pending' => 'Pending'] as $modalStatusKey => $modalStatusLabel): ?>
              <label class="form-check border rounded-3 p-3 ps-5 mb-0">
                <input class="form-check-input" type="radio" name="cms_request_modal_status" value="<?= $modalStatusKey ?>" <?= $modalStatusKey === 'all' ? 'checked' : '' ?> data-cms-modal-status-filter>
                <span class="form-check-label"><?= $modalStatusLabel ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-cms-modal-filter-reset>Reset</button>
          <button type="button" class="btn btn-primary" data-cms-modal-filter-apply>Apply Filter</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="cmsRequestColumnsModal" tabindex="-1" aria-labelledby="cmsRequestColumnsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header">
          <h5 class="modal-title" id="cmsRequestColumnsModalLabel">Content Request Columns</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">Choose the columns shown in the current request view.</p>
          <div class="row g-3">
            <?php foreach ([2 => 'Created By', 3 => 'Status', 4 => 'Updated', 5 => 'Submitted', 6 => 'Reviewed'] as $modalColumnIndex => $modalColumnLabel): ?>
              <div class="col-12 col-sm-6">
                <label class="form-check border rounded-3 p-3 ps-5 mb-0 h-100">
                  <input class="form-check-input" type="checkbox" value="<?= $modalColumnIndex ?>" checked data-cms-modal-column-toggle="<?= $modalColumnIndex ?>">
                  <span class="form-check-label"><?= $modalColumnLabel ?></span>
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-cms-modal-columns-reset>Show All</button>
          <button type="button" class="btn btn-primary" data-cms-modal-columns-apply>Apply Columns</button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="appDialogModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title" id="appDialogTitle">Notice</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-2">
          <p class="mb-0" id="appDialogMessage"></p>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" id="appDialogCancelBtn" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="appDialogConfirmBtn">OK</button>
        </div>
      </div>
    </div>
  </div>

  <div class="d-none">
    <template id="cms-preview-template-announcements">
      <main class="cms-preview-doc">
        <div class="banner">
          <img src="" alt="News Banner" class="bannerImage" data-cms-announcements="banner-image">
          <div class="bannerText">
            <h1 data-cms-announcements="banner-title">News and Announcements</h1>
            <p data-cms-announcements="banner-message"></p>
          </div>
        </div>
        <span class="pageDivider"></span>
        <main class="mainContentWrapper">
          <div class="container newsOuter py-5">
            <div class="row g-5">
              <div class="col-md-8 newsArticleGroup">
                <h2 class="articleHeadline">Latest Barangay News</h2>
                <p class="announcementDate mb-3">Preview only</p>
                <div class="articleBody">
                  <p>News articles and announcements continue to come from the Announcements module after the banner update is approved.</p>
                </div>
              </div>
              <div class="col-md-4 newsSidebarGroup">
                <div class="sidebarSection announcementsGroup">
                  <h3 class="sidebarTitle">ANNOUNCEMENTS</h3>
                  <div class="announcementItem">
                    <p class="announcementText">Sidebar announcement preview</p>
                    <div class="announcementPreviewText">The actual announcement feed remains connected to the current content tools.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </main>
      </main>
    </template>

    <template id="cms-preview-template-home">
      <main class="cms-preview-doc">
        <div class="homeBanner">
          <img src="" alt="Home Banner" class="homeBannerImage" data-cms-home="banner-image">
        </div>
        <section class="aboutSection">
          <div class="container">
            <div class="row gx-4">
              <div class="col-12">
                <h1 class="sectionTitle text-center">About Us</h1>
              </div>
            </div>
            <div class="row align-items-center gx-4 aboutContentRow">
              <div class="col-md-4">
                <img src="" id="imgPortrait" alt="About Us" class="img-fluid mx-auto d-block" data-cms-home="about-image">
              </div>
              <div class="col-md-8" data-cms-home="about-message"></div>
            </div>
            <div class="row gx-4 missionVisionRow">
              <div class="col-md-6">
                <h2 class="sectionTitle text-center">Mission</h2>
                <div data-cms-home="mission-message"></div>
              </div>
              <div class="col-md-6">
                <h2 class="sectionTitle text-center">Vision</h2>
                <div data-cms-home="vision-message"></div>
              </div>
            </div>
          </div>
        </section>
        <section class="historySection text-center">
          <h1 class="my-5">Our History</h1>
          <div class="container mt-5">
            <div class="row align-items-center gx-4">
              <div class="col-md-6 text-start" data-cms-home="history-message"></div>
              <div class="col-md-6">
                <img src="<?= htmlspecialchars(cms_content_public_asset_url('Images/Our_History_1.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="History" id="imgLandscape" class="img-fluid mx-auto d-block">
                <br>
                <img src="<?= htmlspecialchars(cms_content_public_asset_url('Images/Our_History_2.jpg'), ENT_QUOTES, 'UTF-8') ?>" alt="History" id="imgLandscape" class="img-fluid mx-auto d-block">
              </div>
            </div>
          </div>
        </section>
        <section class="meetTheCouncil text-center">
          <h1 class="my-5">Meet The Council</h1>
          <div class="container">
            <div class="council-window">
              <div class="council-track" data-cms-home="council-list"></div>
            </div>
          </div>
        </section>
      </main>
    </template>

    <template id="cms-preview-template-government">
      <main class="cms-preview-doc">
        <div class="banner">
          <img src="" alt="Government Banner" class="bannerImage" data-cms-government="banner-image">
          <div class="bannerText">
            <h1 data-cms-government="banner-title">Government</h1>
            <p data-cms-government="banner-message"></p>
          </div>
        </div>
        <span class="pageDivider"></span>
        <section class="brgyCaptainSection">
          <div class="container align-items-center">
            <div class="row align-items-center mx-5">
              <div class="col-md-4">
                <img src="" alt="Punong Barangay" id="kapDesign" class="img-fluid mx-auto d-block" data-cms-government="punong-image">
              </div>
              <div class="col-md-8">
                <h2 data-cms-government="punong-name"></h2>
                <h4 style="color:#000000;" data-cms-government="punong-position"></h4>
                <br>
                <div data-cms-government="punong-message"></div>
              </div>
            </div>
          </div>
        </section>
        <span class="labelDivider">Barangay Officials</span>
        <section class="brgyOfficialSection">
          <div class="container text-center">
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3" data-cms-government-list="officials"></div>
          </div>
        </section>
        <span class="labelDivider">Barangay Vicinity</span>
        <section class="deptSection">
          <div class="container">
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3 text-start vicinityGrid" data-cms-government-list="areas"></div>
          </div>
        </section>
      </main>
    </template>

    <template id="cms-preview-template-services">
      <main class="cms-preview-doc">
        <div class="banner">
          <img src="" alt="Services Banner" class="bannerImage" data-cms-services="banner-image">
          <div class="bannerText">
            <h1 data-cms-services="banner-title">Services</h1>
            <p data-cms-services="banner-message"></p>
          </div>
        </div>
        <span class="pageDivider"></span>
        <section class="servicesSection">
          <div class="container">
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3 text-start justify-content-center" data-cms-services-list="items"></div>
          </div>
        </section>
      </main>
    </template>

    <template id="cms-preview-template-faq">
      <main class="cms-preview-doc">
        <div class="banner">
          <img src="" alt="FAQ Banner" class="bannerImage" data-cms-faq="banner-image">
          <div class="bannerText">
            <h1 data-cms-faq="banner-title">Frequently Asked Questions</h1>
            <p data-cms-faq="banner-message"></p>
          </div>
        </div>
        <span class="pageDivider"></span>
        <main class="mainContent">
          <div class="container faqGridGroup py-5">
            <div class="row g-4">
              <div class="col-md-6">
                <div class="accordionGroup" data-cms-faq-list="left"></div>
              </div>
              <div class="col-md-6">
                <div class="accordionGroup" data-cms-faq-list="right"></div>
              </div>
            </div>
          </div>
        </main>
      </main>
    </template>

    <template id="cms-preview-template-contact">
      <main class="cms-preview-doc">
        <div class="banner">
          <img src="" alt="Contact Banner" class="bannerImage" data-cms-contact="banner-image">
          <div class="bannerText">
            <h1 data-cms-contact="banner-title">Contact</h1>
            <p data-cms-contact="banner-message"></p>
          </div>
        </div>
        <span class="pageDivider"></span>
        <main class="mainContentWrapper">
          <section class="contactContentArea contactCentered">
            <div class="contactSectionGroup bherSection">
              <h2 class="sectionTitle" data-cms-contact="emergency-title"></h2>
              <p class="sectionText" data-cms-contact="emergency-description"></p>
              <div class="row g-4 contactContactRow contactContactRow--tight" data-cms-contact-list="emergency-hotlines"></div>
            </div>
          </section>
          <span class="labelDivider areaHotlinesDivider">Area Hotlines</span>
          <div class="container contactGridGroup py-5">
            <div class="row g-4 contactGridRow" data-cms-contact-list="area-hotlines"></div>
          </div>
        </main>
      </main>
    </template>

    <template id="cms-preview-template-login">
      <main class="cms-preview-doc">
        <div class="login-signup-container" data-cms-login-root>
          <div class="auth-image login-image"></div>
          <div class="form-wrapper">
            <form class="form-box active">
              <h1 class="mb-1 fs-2 text-center"><strong>Welcome Back!</strong></h1>
              <p class="text-center fs-6 text-muted intro-message">Please enter your credentials.</p>
              <h4 class="mb-3 fs-4 text-center"><strong>Login</strong></h4>
              <input type="text" class="fs-6 form-control mb-3" placeholder="Email / Phone">
              <div class="input-group mb-3">
                <input type="password" class="form-control" placeholder="Password">
                <span class="input-group-text"><i class="bi bi-eye"></i></span>
              </div>
              <button type="button" class="btn btn-primary w-100">Login</button>
            </form>
          </div>
        </div>
      </main>
    </template>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../summernote-0.9.0-dist/summernote-lite.min.js?v=20260307-2"></script>
  <script src="../../JS-Script-Files/siteContentRuntime.js?v=20260325-1"></script>
  <script>
    (function () {
      const requestViewTabs = Array.from(document.querySelectorAll("[data-cms-request-tab]"));
      const requestViewPanels = Array.from(document.querySelectorAll("[data-cms-request-panel]"));

      function activateRequestView(viewKey) {
        const selectedTab = requestViewTabs.find(function (tab) {
          return tab.dataset.cmsRequestTab === viewKey;
        });
        const selectedPanel = requestViewPanels.find(function (panel) {
          return panel.dataset.cmsRequestPanel === viewKey;
        });
        if (!selectedTab || !selectedPanel) {
          return;
        }

        requestViewTabs.forEach(function (tab) {
          const isActive = tab === selectedTab;
          tab.classList.toggle("active", isActive);
          tab.setAttribute("aria-selected", isActive ? "true" : "false");
        });
        requestViewPanels.forEach(function (panel) {
          panel.classList.toggle("d-none", panel !== selectedPanel);
        });

        const targetUrl = selectedTab.dataset.cmsRequestUrl;
        if (targetUrl && window.history && window.history.replaceState) {
          window.history.replaceState({}, "", targetUrl);
        }
      }

      requestViewTabs.forEach(function (tab) {
        tab.addEventListener("click", function () {
          activateRequestView(tab.dataset.cmsRequestTab || "my_requests");
        });
      });

      requestViewPanels.forEach(function (panel) {
        const rows = Array.from(panel.querySelectorAll("[data-cms-request-row]"));
        const filterButtons = Array.from(panel.querySelectorAll("[data-cms-status-filter]"));
        const searchInput = panel.querySelector("[data-cms-request-search-input]");
        const searchButton = panel.querySelector("[data-cms-request-search-button]");
        const refreshButton = panel.querySelector("[data-cms-refresh-list]");
        const noResultsRow = panel.querySelector("[data-cms-request-no-results]");
        const entryBadge = panel.querySelector("[data-cms-visible-entry-badge]");
        const entryCount = panel.querySelector("[data-cms-visible-entry-count]");
        const entrySummary = panel.querySelector("[data-cms-visible-entry-summary]");
        let activeStatus = "all";

        function updateVisibleRequestRows() {
          const searchTerm = String(searchInput?.value || "").trim().toLowerCase();
          let visibleCount = 0;

          rows.forEach(function (row) {
            const rowStatus = String(row.dataset.cmsRequestStatus || "").toLowerCase();
            const rowSearch = String(row.dataset.cmsRequestSearch || "").toLowerCase();
            const statusMatches = activeStatus === "all" || rowStatus === activeStatus;
            const searchMatches = searchTerm === "" || rowSearch.includes(searchTerm);
            const isVisible = statusMatches && searchMatches;
            row.classList.toggle("d-none", !isVisible);
            if (isVisible) {
              visibleCount += 1;
            }
          });

          if (noResultsRow) {
            noResultsRow.classList.toggle("d-none", rows.length === 0 || visibleCount > 0);
          }
          if (entryBadge) {
            entryBadge.textContent = visibleCount + (visibleCount === 1 ? " entry" : " entries");
          }
          if (entryCount) {
            entryCount.textContent = String(visibleCount);
          }
          if (entrySummary) {
            entrySummary.textContent = "Showing " + visibleCount + (visibleCount === 1 ? " request" : " requests");
          }
        }

        filterButtons.forEach(function (button) {
          button.addEventListener("click", function () {
            activeStatus = String(button.dataset.cmsStatusFilter || "all").toLowerCase();
            filterButtons.forEach(function (candidate) {
              candidate.classList.toggle("active", candidate === button);
            });
            updateVisibleRequestRows();
          });
        });
        searchInput?.addEventListener("input", updateVisibleRequestRows);
        searchButton?.addEventListener("click", updateVisibleRequestRows);
        refreshButton?.addEventListener("click", function () {
          refreshButton.classList.add("is-loading");
          refreshButton.disabled = true;
          window.location.reload();
        });
        updateVisibleRequestRows();
      });

      function activeRequestPanel() {
        return requestViewPanels.find(function (panel) {
          return !panel.classList.contains("d-none");
        }) || null;
      }

      const requestFilterModalEl = document.getElementById("cmsRequestFilterModal");
      const requestFilterModalInputs = Array.from(document.querySelectorAll("[data-cms-modal-status-filter]"));
      const requestFilterApplyButton = document.querySelector("[data-cms-modal-filter-apply]");
      const requestFilterResetButton = document.querySelector("[data-cms-modal-filter-reset]");
      requestFilterModalEl?.addEventListener("show.bs.modal", function () {
        const panel = activeRequestPanel();
        const activeButton = panel?.querySelector("[data-cms-status-filter].active");
        const activeValue = activeButton?.dataset.cmsStatusFilter || "all";
        requestFilterModalInputs.forEach(function (input) {
          input.checked = input.value === activeValue;
        });
      });
      requestFilterApplyButton?.addEventListener("click", function () {
        const selectedStatus = requestFilterModalInputs.find(function (input) {
          return input.checked;
        })?.value || "all";
        activeRequestPanel()?.querySelector('[data-cms-status-filter="' + selectedStatus + '"]')?.click();
        if (requestFilterModalEl) {
          bootstrap.Modal.getOrCreateInstance(requestFilterModalEl).hide();
        }
      });
      requestFilterResetButton?.addEventListener("click", function () {
        requestFilterModalInputs.forEach(function (input) {
          input.checked = input.value === "all";
        });
      });

      const requestColumnsModalEl = document.getElementById("cmsRequestColumnsModal");
      const requestColumnInputs = Array.from(document.querySelectorAll("[data-cms-modal-column-toggle]"));
      const requestColumnsApplyButton = document.querySelector("[data-cms-modal-columns-apply]");
      const requestColumnsResetButton = document.querySelector("[data-cms-modal-columns-reset]");
      requestColumnsModalEl?.addEventListener("show.bs.modal", function () {
        const table = activeRequestPanel()?.querySelector(".cms-request-table");
        requestColumnInputs.forEach(function (input) {
          const columnIndex = Number(input.dataset.cmsModalColumnToggle || 0);
          const header = table?.querySelector("thead th:nth-child(" + columnIndex + ")");
          input.checked = Boolean(header && !header.classList.contains("d-none"));
        });
      });
      requestColumnsApplyButton?.addEventListener("click", function () {
        const table = activeRequestPanel()?.querySelector(".cms-request-table");
        requestColumnInputs.forEach(function (input) {
          const columnIndex = Number(input.dataset.cmsModalColumnToggle || 0);
          table?.querySelectorAll("tr > :nth-child(" + columnIndex + ")").forEach(function (cell) {
            cell.classList.toggle("d-none", !input.checked);
          });
        });
        if (requestColumnsModalEl) {
          bootstrap.Modal.getOrCreateInstance(requestColumnsModalEl).hide();
        }
      });
      requestColumnsResetButton?.addEventListener("click", function () {
        requestColumnInputs.forEach(function (input) {
          input.checked = true;
        });
      });

      const selectedPageKey = <?= json_encode(in_array($selectedModuleKey, cms_content_editable_page_keys(), true) ? $selectedModuleKey : '') ?>;
      const previewAssetBase = <?= json_encode($previewAssetBase) ?>;
      const previewRuntimeJs = <?= json_encode($previewRuntimeJs) ?>;
      const previewCssAssets = <?= json_encode($previewCssAssets) ?>;
      const faqMaxItems = 20;
      const previewFrame = document.getElementById("cmsPreviewFrame");
      const editorForm = document.getElementById("cmsEditorForm");
      const payloadInput = document.getElementById("cmsPayloadInput");
      const actionInput = document.getElementById("cmsActionInput");
      const cmsFaqItemsContainer = document.getElementById("cmsFaqItemsContainer");
      const cmsFaqQuestionTarget = document.getElementById("cmsFaqQuestionTarget");
      const cmsFaqItemCount = document.getElementById("cmsFaqItemCount");
      const cropModalEl = document.getElementById("cmsImageCropModal");
      const cropFrameEl = document.getElementById("cmsCropFrame");
      const cropImageEl = document.getElementById("cmsCropImage");
      const cropZoomEl = document.getElementById("cmsCropZoom");
      const cropRatioLabelEl = document.getElementById("cmsCropRatioLabel");
      const cropSaveBtn = document.getElementById("cmsCropSaveBtn");
      const cropModal = cropModalEl ? new bootstrap.Modal(cropModalEl) : null;
      const appDialogModalEl = document.getElementById("appDialogModal");
      const appDialogTitle = document.getElementById("appDialogTitle");
      const appDialogMessage = document.getElementById("appDialogMessage");
      const appDialogConfirmBtn = document.getElementById("appDialogConfirmBtn");
      const appDialogCancelBtn = document.getElementById("appDialogCancelBtn");
      const appDialogModal = appDialogModalEl ? bootstrap.Modal.getOrCreateInstance(appDialogModalEl, {
        backdrop: "static",
        keyboard: false
      }) : null;
      const MAX_IMAGE_SIZE_BYTES = 50 * 1024 * 1024;
      let previewTimer = null;
      let cropTargetPicker = null;
      let cropSourceImage = null;
      let cropState = {
        ratioWidth: 1,
        ratioHeight: 1,
        baseScale: 1,
        zoom: 1,
        translateX: 0,
        translateY: 0,
        dragActive: false,
        dragStartX: 0,
        dragStartY: 0,
        dragOriginX: 0,
        dragOriginY: 0
      };
      let appDialogResolver = null;
      let appDialogResult = false;

      function escapeHtml(value) {
        return String(value == null ? "" : value)
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;");
      }

      function settleAppDialog(result) {
        appDialogResult = result;
        if (appDialogModal) {
          appDialogModal.hide();
          return;
        }
        if (appDialogResolver) {
          const resolve = appDialogResolver;
          appDialogResolver = null;
          resolve(result);
        }
      }

      if (appDialogModalEl) {
        appDialogModalEl.addEventListener("hidden.bs.modal", function () {
          if (!appDialogResolver) {
            return;
          }
          const resolve = appDialogResolver;
          const result = appDialogResult;
          appDialogResolver = null;
          appDialogResult = false;
          resolve(result);
        });
      }

      if (appDialogConfirmBtn) {
        appDialogConfirmBtn.addEventListener("click", function () {
          settleAppDialog(true);
        });
      }

      if (appDialogCancelBtn) {
        appDialogCancelBtn.addEventListener("click", function () {
          settleAppDialog(false);
        });
      }

      function showAppDialog(options) {
        const config = options || {};
        if (!appDialogModal || !appDialogTitle || !appDialogMessage || !appDialogConfirmBtn || !appDialogCancelBtn) {
          if (config.cancelText) {
            return Promise.resolve(window.confirm(config.message || ""));
          }
          window.alert(config.message || "");
          return Promise.resolve(true);
        }

        appDialogTitle.textContent = config.title || "Notice";
        appDialogMessage.textContent = config.message || "";
        appDialogConfirmBtn.textContent = config.confirmText || "OK";
        appDialogConfirmBtn.className = "btn " + (config.confirmClass || "btn-primary");
        appDialogCancelBtn.textContent = config.cancelText || "Cancel";
        appDialogCancelBtn.classList.toggle("d-none", !config.cancelText);
        appDialogResult = false;

        return new Promise(function (resolve) {
          appDialogResolver = resolve;
          appDialogModal.show();
        });
      }

      function showAppAlert(message, title) {
        return showAppDialog({
          title: title || "Notice",
          message: message,
          confirmText: "OK"
        });
      }

      function showAppConfirm(message, title, confirmText, confirmClass) {
        return showAppDialog({
          title: title || "Confirm Action",
          message: message,
          confirmText: confirmText || "Confirm",
          cancelText: "Cancel",
          confirmClass: confirmClass || "btn-primary"
        });
      }

      function joinPreviewAssetPath(path) {
        return String(previewAssetBase || "").replace(/\/+$/, "") + "/" + String(path || "").replace(/^\/+/, "");
      }

      function buildPreviewNavbar(pageKey) {
        if (!pageKey || pageKey === "login") {
          return "";
        }

        const normalizedPageKey = pageKey === "announcements" ? "news" : pageKey;
        const logoUrl = joinPreviewAssetPath("Images/San_Jose_LOGO.jpg");
        const navItems = [
          { key: "home", label: "Home" },
          { key: "government", label: "Government" },
          { key: "services", label: "Services" },
          { key: "news", label: "News" },
          { key: "faq", label: "FAQ" },
          { key: "contact", label: "Contact" }
        ].map(function (item) {
          const isActive = item.key === normalizedPageKey;
          return [
            '<li class="nav-item mx-lg-3">',
            '  <a class="nav-link' + (isActive ? " active" : "") + '"' + (isActive ? ' aria-current="page"' : "") + ' href="#">' + escapeHtml(item.label) + "</a>",
            "</li>"
          ].join("");
        }).join("");

        const navbarClass = normalizedPageKey === "home"
          ? "navbar navbar-expand-xl align-items-center navbarMain navbar-dark"
          : "navbar navbar-expand-xl align-items-center navbar-light bg-white shadow-sm";
        const navbarId = normalizedPageKey === "home" ? ' id="mainNavbar"' : "";

        return [
          '<div class="navbarWrapper">',
          '  <nav' + navbarId + ' class="' + navbarClass + '">',
          '    <div class="container-fluid align-items-center px-4">',
          '      <a id="navbarBrand" class="navbar-brand" href="#">',
          '        <img src="' + escapeHtml(logoUrl) + '" alt="Logo" id="navbarLogo" class="d-inline-block align-text-center">',
          "        Barangay San Jose",
          "      </a>",
          '      <button class="navbar-toggler" type="button" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">',
          '        <span class="navbar-toggler-icon"></span>',
          "      </button>",
          '      <div class="collapse navbar-collapse" id="navbarNav">',
          '        <ul id="navbarLinks" class="navbar-nav ms-auto">',
          navItems,
          '          <li class="nav-item">',
          '            <a class="nav-link btn btn-orange text-white px-4 ms-2" href="#">Login</a>',
          "          </li>",
          "        </ul>",
          "      </div>",
          "    </div>",
          "  </nav>",
          "</div>"
        ].join("");
      }

      function buildPreviewFooter(pageKey) {
        if (!pageKey || pageKey === "login") {
          return "";
        }

        const logoUrl = joinPreviewAssetPath("Images/San_Jose_LOGO.jpg");
        return [
          '<div class="footerWrapper">',
          '  <footer id="footer">',
          '    <div class="container">',
          '      <div class="row">',
          '        <div class="col-8">',
          '          <img src="' + escapeHtml(logoUrl) + '" alt="Logo" id="footerLogo" class="imgfluid rounded-circle p-3">',
          "        </div>",
          '        <div class="col">',
          '          <div class="footerText">',
          "            <h5>Quick Links</h5>",
          '            <ul class="list-unstyled">',
          '              <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="#">Facebook</a></li>',
          '              <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="#">Contact Us</a></li>',
          "            </ul>",
          "          </div>",
          "        </div>",
          '        <div class="col">',
          '          <div class="footerText">',
          "            <h5>Barangay Info</h5>",
          '            <ul class="list-unstyled">',
          '              <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="#">Privacy Policy</a></li>',
          '              <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="#">Terms &amp; Conditions</a></li>',
          '              <li><a id="footerLink" class="link-offset-2 link-underline-light link-underline-opacity-0 link-underline-opacity-75-hover" href="#">Disclaimers</a></li>',
          "            </ul>",
          "          </div>",
          "        </div>",
          "      </div>",
          "    </div>",
          "  </footer>",
          "</div>"
        ].join("");
      }

      function buildPreviewSiteShell(pageKey, innerMarkup) {
        if (!pageKey || pageKey === "login") {
          return innerMarkup;
        }
        return '<div class="cms-preview-site-shell">' + buildPreviewNavbar(pageKey) + innerMarkup + buildPreviewFooter(pageKey) + "</div>";
      }

      function buildPreviewBehaviorScript(pageKey) {
        if (pageKey === "login") {
          return "";
        }

        const behaviorLines = [
          "<script>",
          "(function(){",
          "  var toggler=document.querySelector('.navbar-toggler');",
          "  var navbarCollapse=document.getElementById('navbarNav');",
          "  if(toggler&&navbarCollapse){",
          "    toggler.addEventListener('click',function(){",
          "      var willOpen=!navbarCollapse.classList.contains('show');",
          "      navbarCollapse.classList.toggle('show',willOpen);",
          "      toggler.setAttribute('aria-expanded',willOpen?'true':'false');",
          "    });",
          "  }"
        ];

        if (pageKey === "home") {
          behaviorLines.push(
          "  var navbar=document.getElementById('mainNavbar');",
          "  function updateNavbarState(){",
          "    var top=window.pageYOffset||document.documentElement.scrollTop||document.body.scrollTop||0;",
          "    if(navbar){navbar.classList.toggle('navbar--scrolled',top>20);}",
          "  }",
          "  window.addEventListener('scroll', updateNavbarState, { passive: true });",
          "  updateNavbarState();"
          );
        }

        behaviorLines.push(
          "})();",
          "<\/script>"
        );
        return behaviorLines.join("");
      }

      function buildPreviewDocument(pageKey, payload) {
        const template = document.getElementById("cms-preview-template-" + pageKey);
        if (!template) {
          return "";
        }
        const payloadJson = JSON.stringify(payload).replace(/<\//g, "<\\/");
        const pageMarkup = buildPreviewSiteShell(pageKey, template.innerHTML);

        const cssLinks = [previewCssAssets.bootstrap];
        switch (pageKey) {
          case "home":
            cssLinks.push(previewCssAssets.navbar, previewCssAssets.home);
            break;
          case "announcements":
            cssLinks.push(previewCssAssets.news, previewCssAssets.guest, previewCssAssets.navbar);
            break;
          case "government":
          case "services":
            cssLinks.push(previewCssAssets.guest, previewCssAssets.navbar);
            break;
          case "faq":
            cssLinks.push(previewCssAssets.faq, previewCssAssets.guest, previewCssAssets.navbar);
            break;
          case "contact":
            cssLinks.push(previewCssAssets.contact, previewCssAssets.guest, previewCssAssets.navbar);
            break;
          case "login":
            cssLinks.push(previewCssAssets.login);
            break;
        }

        const headMarkup = cssLinks.map(function (href) {
          return '<link rel="stylesheet" href="' + escapeHtml(href) + '">';
        }).join("");

        return [
          "<!DOCTYPE html>",
          '<html lang="en">',
          "<head>",
          '<meta charset="UTF-8">',
          '<meta name="viewport" content="width=device-width, initial-scale=1.0">',
          headMarkup,
          "<style>",
          "body{margin:0;background:#ffffff;overflow-x:hidden;overflow-y:auto;}",
          ".cms-preview-doc{min-height:100vh;}",
          ".cms-preview-doc *{pointer-events:none !important;}",
          "body a,body button,body input,body textarea,body select{pointer-events:none !important;}",
          ".cms-preview-site-shell .navbar-toggler{pointer-events:auto !important;}",
          ".cms-runtime-richtext p:last-child{margin-bottom:0;}",
          ".login-signup-container{margin:32px auto;}",
          ".bannerText h1,.bannerText p{color:#ffffff !important;}",
          "</style>",
          "</head>",
          '<body data-cms-page="' + escapeHtml(pageKey) + '" data-cms-asset-base="' + escapeHtml(previewAssetBase) + '">',
          pageMarkup,
          "<script>",
          "window.CMS_PREVIEW_PAYLOAD = " + payloadJson + ";",
          "<\/script>",
          '<script src="' + escapeHtml(previewRuntimeJs) + '"><\/script>',
          buildPreviewBehaviorScript(pageKey),
          "</body>",
          "</html>"
        ].join("");
      }

      function getFieldValue(field) {
        if (!field) {
          return "";
        }
        if (field.tagName === "TEXTAREA" || field.tagName === "INPUT") {
          return field.value;
        }
        return "";
      }

      function collectRepeaterItems(container) {
        return Array.from(container.querySelectorAll("[data-cms-repeater-item]")).map(function (item) {
          const row = {};
          item.querySelectorAll("[data-cms-item-field]").forEach(function (field) {
            row[field.dataset.cmsItemField] = getFieldValue(field);
          });
          return row;
        });
      }

      function getFaqItems() {
        if (!cmsFaqItemsContainer) {
          return [];
        }
        return collectRepeaterItems(cmsFaqItemsContainer).map(function (item) {
          return {
            question: String(item.question || "").trim(),
            answer: String(item.answer || "").trim()
          };
        });
      }

      function updateFaqEditorState() {
        if (!cmsFaqItemsContainer) {
          return;
        }
        const items = Array.from(cmsFaqItemsContainer.querySelectorAll("[data-cms-faq-item]"));
        items.forEach(function (item, index) {
          const title = item.querySelector("[data-cms-faq-title]");
          if (title) {
            title.textContent = "Question " + (index + 1);
          }
        });
        if (cmsFaqItemCount) {
          cmsFaqItemCount.textContent = items.length + " / " + faqMaxItems + " Questions";
        }
        if (cmsFaqQuestionTarget) {
          cmsFaqQuestionTarget.value = String(Math.max(1, Math.min(faqMaxItems, items.length || 1)));
        }
      }

      function syncFaqTargetCount(targetCount) {
        if (!cmsFaqItemsContainer) {
          return;
        }
        const desiredCount = Math.max(1, Math.min(faqMaxItems, Number(targetCount) || 1));
        let currentCount = cmsFaqItemsContainer.querySelectorAll("[data-cms-faq-item]").length;
        const template = document.getElementById("cms-template-cmsFaqItemsContainer");
        while (currentCount < desiredCount && template) {
          cmsFaqItemsContainer.insertAdjacentHTML("beforeend", template.innerHTML);
          initEditors(cmsFaqItemsContainer);
          currentCount += 1;
        }
        while (currentCount > desiredCount) {
          const lastItem = cmsFaqItemsContainer.querySelector("[data-cms-faq-item]:last-child");
          if (!lastItem) {
            break;
          }
          lastItem.remove();
          currentCount -= 1;
        }
        updateFaqEditorState();
        schedulePreviewUpdate();
      }

      function validateFaqEditor() {
        if (selectedPageKey !== "faq" || !cmsFaqItemsContainer) {
          return true;
        }
        const faqItems = getFaqItems().filter(function (item) {
          return item.question !== "" || item.answer !== "";
        });
        if (faqItems.length === 0) {
          showAppAlert("Add at least one FAQ question and answer before saving.");
          return false;
        }
        if (faqItems.length > faqMaxItems) {
          showAppAlert("You can only save up to 20 FAQ questions in one content item.");
          return false;
        }
        const hasIncomplete = faqItems.some(function (item) {
          return item.question === "" || item.answer === "";
        });
        if (hasIncomplete) {
          showAppAlert("Complete both the question and answer for every FAQ entry before saving.");
          return false;
        }
        return true;
      }

      function serializePayload() {
        const payload = {};
        if (!editorForm) {
          return payload;
        }

        editorForm.querySelectorAll("[data-cms-field]").forEach(function (field) {
          payload[field.dataset.cmsField] = getFieldValue(field);
        });

        editorForm.querySelectorAll("[data-cms-repeater]").forEach(function (container) {
          payload[container.dataset.cmsRepeater] = collectRepeaterItems(container);
        });

        return payload;
      }

      function schedulePreviewUpdate() {
        if (!previewFrame || !selectedPageKey) {
          return;
        }
        window.clearTimeout(previewTimer);
        previewTimer = window.setTimeout(function () {
          const payload = serializePayload();
          if (payloadInput) {
            payloadInput.value = JSON.stringify(payload);
          }
          previewFrame.srcdoc = buildPreviewDocument(selectedPageKey, payload);
        }, 180);
      }

      function renderPreviewNow() {
        if (!previewFrame || !selectedPageKey) {
          return;
        }
        const payload = serializePayload();
        if (payloadInput) {
          payloadInput.value = JSON.stringify(payload);
        }
        previewFrame.srcdoc = buildPreviewDocument(selectedPageKey, payload);
      }

      function applyToolbarTooltips() {
        const tooltips = [
          [".note-btn[data-event='fontname']", "Font Style"],
          [".note-btn[data-event='fontsize']", "Font Size"],
          [".note-btn[data-event='color']", "Text Color"],
          [".note-btn[data-event='bold']", "Bold"],
          [".note-btn[data-event='italic']", "Italic"],
          [".note-btn[data-event='underline']", "Underline"],
          [".note-btn[data-event='strikethrough']", "Strikethrough"],
          [".note-btn[data-event='ul']", "Bullet List"],
          [".note-btn[data-event='ol']", "Numbered List"],
          [".note-btn[data-event='justifyLeft']", "Align Left"],
          [".note-btn[data-event='justifyCenter']", "Align Center"],
          [".note-btn[data-event='justifyRight']", "Align Right"],
          [".note-btn[data-event='justifyFull']", "Justify"],
          [".note-btn[data-event='link']", "Insert Link"],
          [".note-btn[data-event='picture']", "Insert Image"],
          [".note-btn[data-event='removeFormat']", "Clear Formatting"]
        ];

        tooltips.forEach(function (entry) {
          document.querySelectorAll(".note-toolbar " + entry[0]).forEach(function (el) {
            el.setAttribute("title", entry[1]);
            el.setAttribute("aria-label", entry[1]);
          });
        });
      }

      async function uploadEditorImage(file) {
        const formData = new FormData();
        formData.append("image", file);

        const response = await fetch("../../PhpFiles/Admin-End/uploadAnnouncementEditorImage.php", {
          method: "POST",
          body: formData
        });
        const payload = await response.json();
        const imageUrl = payload.url || payload.location || "";
        if (!response.ok || (!payload.success && !imageUrl) || !imageUrl) {
          throw new Error(payload.message || "Image upload failed.");
        }
        return imageUrl;
      }

      function initEditor(host) {
        if (!host || host.dataset.initialized === "true") {
          return;
        }

        const hiddenInput = host.nextElementSibling;
        if (!hiddenInput) {
          return;
        }

        const editorInstance = window.jQuery ? window.jQuery(host) : null;
        if (!editorInstance || typeof editorInstance.summernote !== "function") {
          return;
        }

        const placeholder = host.dataset.placeholder || "Write here...";
        const editorHeight = Number(host.dataset.editorHeight || 240);
        const initialHtml = host.dataset.initialHtml || hiddenInput.value || "";
        const fullToolbar = [
          ["style", ["style"]],
          ["font", ["bold", "italic", "underline", "clear"]],
          ["fontname", ["fontname"]],
          ["fontsize", ["fontsize"]],
          ["color", ["color"]],
          ["para", ["ul", "ol", "paragraph"]],
          ["height", ["height"]],
          ["insert", ["link", "picture"]],
          ["view", ["codeview"]]
        ];

        editorInstance.summernote({
          placeholder: placeholder,
          height: editorHeight,
          minHeight: Math.max(160, editorHeight - 20),
          dialogsInBody: true,
          toolbar: fullToolbar,
          callbacks: {
            onInit: function () {
              editorInstance.summernote("code", initialHtml);
              hiddenInput.value = editorInstance.summernote("code");
              schedulePreviewUpdate();
            },
            onChange: function (contents) {
              hiddenInput.value = contents;
              schedulePreviewUpdate();
            },
            onImageUpload: async function (files) {
              for (const file of files) {
                if (!file) {
                  continue;
                }
                if (file.size > MAX_IMAGE_SIZE_BYTES) {
                  showAppAlert("Image must be 50MB or less.");
                  continue;
                }
                try {
                  const imageUrl = await uploadEditorImage(file);
                  editorInstance.summernote("insertImage", imageUrl);
                } catch (error) {
                  showAppAlert(error.message || "Unable to upload image.");
                }
              }
              hiddenInput.value = editorInstance.summernote("code");
              schedulePreviewUpdate();
            }
          }
        });

        host.dataset.initialized = "true";
        applyToolbarTooltips();
      }

      function initEditors(root) {
        (root || document).querySelectorAll("[data-cms-editor-host]").forEach(initEditor);
      }

      function updateImagePreview(picker, value) {
        if (!picker) {
          return;
        }
        const previewImage = picker.querySelector("[data-cms-image-preview]");
        const placeholder = picker.querySelector("[data-cms-image-placeholder]");
        const hiddenInput = picker.querySelector("[data-cms-field], [data-cms-item-field]");
        if (hiddenInput) {
          hiddenInput.value = value || "";
        }

        if (previewImage && value) {
          previewImage.src = value;
          previewImage.classList.remove("d-none");
        }
        if (placeholder) {
          placeholder.classList.toggle("d-none", !!value);
        }
        schedulePreviewUpdate();
      }

      function clampCropTranslation() {
        if (!cropSourceImage || !cropFrameEl) {
          return;
        }

        const frameRect = cropFrameEl.getBoundingClientRect();
        if (!frameRect.width || !frameRect.height) {
          return;
        }

        const scale = cropState.baseScale * cropState.zoom;
        const displayWidth = cropSourceImage.naturalWidth * scale;
        const displayHeight = cropSourceImage.naturalHeight * scale;
        const minX = Math.min(0, frameRect.width - displayWidth);
        const minY = Math.min(0, frameRect.height - displayHeight);

        cropState.translateX = Math.max(minX, Math.min(0, cropState.translateX));
        cropState.translateY = Math.max(minY, Math.min(0, cropState.translateY));
      }

      function applyCropTransform() {
        if (!cropSourceImage || !cropFrameEl || !cropImageEl) {
          return;
        }

        clampCropTranslation();
        const scale = cropState.baseScale * cropState.zoom;
        cropImageEl.style.width = (cropSourceImage.naturalWidth * scale) + "px";
        cropImageEl.style.height = (cropSourceImage.naturalHeight * scale) + "px";
        cropImageEl.style.transform = "translate(" + cropState.translateX + "px, " + cropState.translateY + "px)";
      }

      function initCropFrameFromImage() {
        if (!cropSourceImage || !cropFrameEl) {
          return;
        }

        const frameRect = cropFrameEl.getBoundingClientRect();
        if (!frameRect.width || !frameRect.height) {
          return;
        }

        cropState.baseScale = Math.max(
          frameRect.width / cropSourceImage.naturalWidth,
          frameRect.height / cropSourceImage.naturalHeight
        );
        cropState.zoom = 1;
        cropState.translateX = (frameRect.width - (cropSourceImage.naturalWidth * cropState.baseScale)) / 2;
        cropState.translateY = (frameRect.height - (cropSourceImage.naturalHeight * cropState.baseScale)) / 2;
        if (cropZoomEl) {
          cropZoomEl.value = "1";
        }
        applyCropTransform();
      }

      function openCropModalForPicker(picker, file) {
        if (!cropModal || !picker || !file) {
          return;
        }
        if (file.size > MAX_IMAGE_SIZE_BYTES) {
          showAppAlert("Image must be 50MB or less.");
          return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
          const result = String(event.target && event.target.result ? event.target.result : "");
          if (!result) {
            showAppAlert("Unable to read the selected image.");
            return;
          }

          const image = new Image();
          image.onload = function () {
            cropTargetPicker = picker;
            cropSourceImage = image;
            cropState.ratioWidth = Number(picker.dataset.ratioWidth || 1);
            cropState.ratioHeight = Number(picker.dataset.ratioHeight || 1);
            cropImageEl.src = result;
            cropFrameEl.style.aspectRatio = cropState.ratioWidth + " / " + cropState.ratioHeight;
            if (cropRatioLabelEl) {
              cropRatioLabelEl.textContent = cropState.ratioWidth + ":" + cropState.ratioHeight;
            }
            cropModal.show();
          };
          image.src = result;
        };
        reader.readAsDataURL(file);
      }

      function buildCropCanvas() {
        if (!cropSourceImage || !cropFrameEl) {
          return null;
        }

        const scale = cropState.baseScale * cropState.zoom;
        if (!scale) {
          return null;
        }

        const frameRect = cropFrameEl.getBoundingClientRect();
        const outputMaxSide = 1800;
        const ratioScale = outputMaxSide / Math.max(cropState.ratioWidth, cropState.ratioHeight);
        const canvasWidth = Math.max(1, Math.round(cropState.ratioWidth * ratioScale));
        const canvasHeight = Math.max(1, Math.round(cropState.ratioHeight * ratioScale));
        const canvas = document.createElement("canvas");
        canvas.width = canvasWidth;
        canvas.height = canvasHeight;
        const context = canvas.getContext("2d");
        if (!context) {
          return null;
        }

        const sourceX = Math.max(0, (-cropState.translateX) / scale);
        const sourceY = Math.max(0, (-cropState.translateY) / scale);
        const sourceWidth = Math.min(cropSourceImage.naturalWidth, frameRect.width / scale);
        const sourceHeight = Math.min(cropSourceImage.naturalHeight, frameRect.height / scale);

        context.drawImage(
          cropSourceImage,
          sourceX,
          sourceY,
          sourceWidth,
          sourceHeight,
          0,
          0,
          canvas.width,
          canvas.height
        );

        return canvas;
      }

      if (cropModalEl) {
        cropModalEl.addEventListener("shown.bs.modal", function () {
          window.requestAnimationFrame(initCropFrameFromImage);
        });
      }

      if (cropZoomEl) {
        cropZoomEl.addEventListener("input", function () {
          cropState.zoom = Number(cropZoomEl.value || 1);
          applyCropTransform();
        });
      }

      if (cropFrameEl) {
        const onPointerMove = function (event) {
          if (!cropState.dragActive) {
            return;
          }
          cropState.translateX = cropState.dragOriginX + (event.clientX - cropState.dragStartX);
          cropState.translateY = cropState.dragOriginY + (event.clientY - cropState.dragStartY);
          applyCropTransform();
        };

        const endDrag = function () {
          cropState.dragActive = false;
          cropFrameEl.classList.remove("is-dragging");
        };

        cropFrameEl.addEventListener("pointerdown", function (event) {
          if (!cropSourceImage) {
            return;
          }
          cropState.dragActive = true;
          cropState.dragStartX = event.clientX;
          cropState.dragStartY = event.clientY;
          cropState.dragOriginX = cropState.translateX;
          cropState.dragOriginY = cropState.translateY;
          cropFrameEl.classList.add("is-dragging");
        });

        window.addEventListener("pointermove", onPointerMove);
        window.addEventListener("pointerup", endDrag);
        window.addEventListener("pointercancel", endDrag);
      }

      if (cropSaveBtn) {
        cropSaveBtn.addEventListener("click", function () {
          const canvas = buildCropCanvas();
          if (!canvas || !cropTargetPicker) {
            showAppAlert("Unable to save the cropped image.");
            return;
          }

          updateImagePreview(cropTargetPicker, canvas.toDataURL("image/png"));
          cropModal.hide();
        });
      }

      document.addEventListener("change", function (event) {
        const fileInput = event.target.closest("[data-cms-image-input]");
        if (!fileInput) {
          return;
        }
        const picker = fileInput.closest("[data-cms-image-picker]");
        const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        if (picker && file) {
          openCropModalForPicker(picker, file);
        }
        fileInput.value = "";
      });

      document.addEventListener("click", async function (event) {
        const imageButton = event.target.closest("[data-cms-image-select]");
        if (imageButton) {
          const picker = imageButton.closest("[data-cms-image-picker]");
          const input = picker ? picker.querySelector("[data-cms-image-input]") : null;
          if (input) {
            input.click();
          }
          return;
        }

        const previewButton = event.target.closest("[data-cms-open-preview]");
        if (previewButton) {
          window.clearTimeout(previewTimer);
          renderPreviewNow();
          return;
        }

        const addButton = event.target.closest("[data-cms-repeater-add-target]");
        if (addButton) {
          const targetId = addButton.dataset.cmsRepeaterAddTarget;
          const target = targetId ? document.getElementById(targetId) : null;
          const template = document.getElementById("cms-template-" + targetId);
          if (target && template) {
            target.insertAdjacentHTML("beforeend", template.innerHTML);
            initEditors(target);
            schedulePreviewUpdate();
          }
          return;
        }

        const removeButton = event.target.closest("[data-cms-repeater-remove]");
        if (removeButton) {
          const item = removeButton.closest("[data-cms-repeater-item]");
          const container = item ? item.parentElement : null;
          if (!item || !container) {
            return;
          }
          if (container.querySelectorAll("[data-cms-repeater-item]").length <= 1) {
            showAppAlert("At least one item needs to stay in this section.");
            return;
          }
          item.remove();
          schedulePreviewUpdate();
          return;
        }

        const submitButton = event.target.closest("[data-submit-action]");
        if (submitButton && editorForm && actionInput) {
          if (!validateFaqEditor()) {
            return;
          }
          const confirmMessage = submitButton.dataset.confirm || "Continue with this action?";
          if (!await showAppConfirm(confirmMessage, "Confirm Action", "Continue", "btn-primary")) {
            return;
          }
          actionInput.value = submitButton.dataset.submitAction || "save_draft";
          if (payloadInput) {
            payloadInput.value = JSON.stringify(serializePayload());
          }
          editorForm.submit();
          return;
        }

        const faqAddButton = event.target.closest("[data-cms-faq-add]");
        if (faqAddButton && cmsFaqItemsContainer) {
          event.preventDefault();
          if (cmsFaqItemsContainer.querySelectorAll("[data-cms-faq-item]").length >= faqMaxItems) {
            showAppAlert("You can only save up to 20 FAQ questions in one content item.");
            return;
          }
          const template = document.getElementById("cms-template-cmsFaqItemsContainer");
          if (template) {
            const currentItem = faqAddButton.closest("[data-cms-faq-item]");
            if (currentItem) {
              currentItem.insertAdjacentHTML("afterend", template.innerHTML);
            } else {
              cmsFaqItemsContainer.insertAdjacentHTML("beforeend", template.innerHTML);
            }
            initEditors(cmsFaqItemsContainer);
            updateFaqEditorState();
            schedulePreviewUpdate();
          }
          return;
        }

        const faqRemoveButton = event.target.closest("[data-cms-faq-remove]");
        if (faqRemoveButton && cmsFaqItemsContainer) {
          event.preventDefault();
          if (cmsFaqItemsContainer.querySelectorAll("[data-cms-faq-item]").length <= 1) {
            showAppAlert("At least one FAQ item is required.");
            return;
          }
          const item = faqRemoveButton.closest("[data-cms-faq-item]");
          if (item) {
            item.remove();
            updateFaqEditorState();
            schedulePreviewUpdate();
          }
          return;
        }
      });

      document.addEventListener("input", function (event) {
        if (event.target.matches("[data-cms-field], [data-cms-item-field]")) {
          updateFaqEditorState();
          schedulePreviewUpdate();
        }
      });

      if (cmsFaqQuestionTarget) {
        cmsFaqQuestionTarget.addEventListener("change", function () {
          syncFaqTargetCount(cmsFaqQuestionTarget.value);
        });
      }

      document.querySelectorAll("form[data-confirm]").forEach(function (form) {
        form.addEventListener("submit", async function (event) {
          const confirmMessage = form.dataset.confirm || "Continue with this action?";
          if (!await showAppConfirm(confirmMessage, "Confirm Action", "Confirm", "btn-primary")) {
            event.preventDefault();
          }
        });
      });

      initEditors(document);
      updateFaqEditorState();
      schedulePreviewUpdate();
    })();
  </script>
</body>
</html>
