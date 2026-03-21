<?php
require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/connection.php";

$deliveryChannel = strtolower(trim((string)($_GET['channel'] ?? 'all')));
if (!in_array($deliveryChannel, ['all', 'website', 'public', 'public_news', 'sms', 'email'], true)) {
  $deliveryChannel = 'all';
}
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isSuperAdmin = $sessionRole === 'superadmin';
$contentType = strtolower(trim((string)($_GET['type'] ?? 'page')));
if (!in_array($contentType, ['page', 'delivery', 'faq'], true)) {
  $contentType = 'page';
}
$typeMeta = [
  'page' => ['title' => 'Create Page Announcement', 'description' => 'Create content for the guest and account pages, including news and announcement placements.'],
  'delivery' => ['title' => 'Create SMS and Email Announcement', 'description' => 'Create delivery-first content for SMS and/or email recipients.'],
  'faq' => ['title' => 'Create FAQ Page Content', 'description' => 'Create an FAQ item that can be tracked inside content management.']
];
$meta = $typeMeta[$contentType];
$isPageType = $contentType === 'page';
$isDeliveryType = $contentType === 'delivery';
$isFaqType = $contentType === 'faq';
$guideMeta = [
  'page' => [
    'kicker' => 'Before You Start',
    'title' => 'Page Announcement Tips',
    'subtitle' => 'Create content for the guest news section and/or the announcements areas shown on guest and account pages.',
    'text' => 'Use a clear public-facing title, a short opening summary, and readable sections for residents scanning page content.',
    'blocks' => [
      ['title' => 'Page Placement', 'items' => ['News Section appears in the featured news area', 'Announcements appear on guest and/or account views', 'If both are selected, write a separate version for each']],
      ['title' => 'Writing Tips', 'items' => ['Lead with the key update', 'Keep instructions easy to scan', 'Use bullets for schedules or requirements']]
    ]
  ],
  'delivery' => [
    'kicker' => 'Before You Send',
    'title' => 'SMS and Email Tips',
    'subtitle' => 'Create delivery-ready content for direct messages sent through SMS and/or email.',
    'text' => 'Keep SMS concise and place the most important information early. For email, use a clear subject and readable body.',
    'blocks' => [
      ['title' => 'Delivery Channels', 'items' => ['SMS is best for short reminders', 'Email works for longer details', 'Audience and publishing control who receives it']],
      ['title' => 'Writing Tips', 'items' => ['Put the most urgent info first', 'Avoid long SMS blocks', 'Use the body for complete instructions']]
    ]
  ],
  'faq' => [
    'kicker' => 'Before You Publish',
    'title' => 'FAQ Writing Tips',
    'subtitle' => 'Create a question-and-answer item that matches the public FAQ page style.',
    'text' => 'Build a small FAQ set using direct resident questions and short practical answers. You can add up to 20 questions in one content item.',
    'blocks' => [
      ['title' => 'FAQ Format', 'items' => ['Write each question the way a resident would ask it', 'Answer in short paragraphs or steps', 'Keep each answer practical and direct']],
      ['title' => 'Examples', 'items' => ['How do I request a barangay certificate?', 'How do I apply for a Barangay ID?', 'How long does processing take?']]
    ]
  ]
];
$guide = $guideMeta[$contentType];
$audienceAreaOptions = [
  'Barangay Wide',
  'Area 01',
  'Area 1A',
  'Area 02',
  'Area 03',
  'Area 04',
  'Area 05',
  'Area 06',
];
$residentAreaRes = $conn->query("
  SELECT DISTINCT area_number
  FROM residentaddresstbl
  WHERE area_number IS NOT NULL AND TRIM(area_number) <> ''
  ORDER BY area_number ASC
");
if ($residentAreaRes instanceof mysqli_result) {
  while ($row = $residentAreaRes->fetch_assoc()) {
    $value = trim((string)($row['area_number'] ?? ''));
    if ($value !== '' && !in_array($value, $audienceAreaOptions, true)) {
      $audienceAreaOptions[] = $value;
    }
  }
}
$officialAreaRes = $conn->query("
  SELECT DISTINCT area_number
  FROM officialinformationtbl
  WHERE area_number IS NOT NULL AND TRIM(area_number) <> ''
  ORDER BY area_number ASC
");
if ($officialAreaRes instanceof mysqli_result) {
  while ($row = $officialAreaRes->fetch_assoc()) {
    $value = trim((string)($row['area_number'] ?? ''));
    if ($value !== '' && !in_array($value, $audienceAreaOptions, true)) {
      $audienceAreaOptions[] = $value;
    }
  }
}
$sharedMeta = [
  'page' => [
    'title_label' => 'Title',
    'title_placeholder' => 'Enter announcement title',
    'body_label' => 'Body',
    'body_helper' => 'Use headings, lists, and short paragraphs so the announcement stays readable in both news and announcement views.',
    'editor_placeholder' => 'Write your announcement here...'
  ],
  'delivery' => [
    'title_label' => 'Title',
    'title_placeholder' => 'Enter delivery announcement title',
    'body_label' => 'Body',
    'body_helper' => 'Write the full message that will support the selected SMS and/or email delivery.',
    'editor_placeholder' => 'Write the delivery content here...'
  ],
  'faq' => [
    'title_label' => 'Question',
    'title_placeholder' => 'Enter frequently asked question',
    'body_label' => 'Answer',
    'body_helper' => 'Answer the question clearly using the same short, practical style shown on the FAQ page.',
    'editor_placeholder' => 'Write the answer here...'
  ]
][$contentType];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($meta['title']) ?></title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../../summernote-0.9.0-dist/summernote-lite.min.css?v=20260307-2" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css?v=20260318-36">
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h2 class="mb-1" style="font-family: 'Charis SIL Bold'; color: #DE710C;"><?= htmlspecialchars($meta['title']) ?></h2>
          <p class="text-muted mb-0"><?= htmlspecialchars($meta['description']) ?></p>
        </div>
        <a href="<?= htmlspecialchars(appUrl('/Admin-End/Contents/Contents.php')) ?><?= $deliveryChannel !== 'all' ? '?channel=' . urlencode($deliveryChannel) : '' ?>" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i> Go to Content Tools
        </a>
      </div>
      <hr><br>
      <section class="announcement-create-guide mb-4">
        <div class="announcement-create-guide-copy">
          <div class="announcement-create-guide-kicker"><?= htmlspecialchars($guide['kicker']) ?></div>
          <h5 class="announcement-section-title mb-1"><?= htmlspecialchars($guide['title']) ?></h5>
          <p class="announcement-compose-subtitle mb-2"><?= htmlspecialchars($guide['subtitle']) ?></p>
          <p class="announcement-create-guide-text mb-0"><?= htmlspecialchars($guide['text']) ?></p>
        </div>
        <div class="announcement-create-guide-grid">
          <?php foreach ($guide['blocks'] as $block): ?>
            <div class="announcement-guide-block">
              <h6 class="announcement-guide-title"><?= htmlspecialchars($block['title']) ?></h6>
              <ul class="announcement-guide-list">
                <?php foreach ($block['items'] as $item): ?>
                  <li><?= htmlspecialchars($item) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <form class="announcement-create-shell p-3 p-md-4 shadow-sm" action="../../PhpFiles/Admin-End/announcementsCreation.php" method="post">
        <input type="hidden" name="channel_context" value="<?= htmlspecialchars($deliveryChannel) ?>">
        <input type="hidden" name="content_type" value="<?= htmlspecialchars($contentType) ?>">
        <div class="row g-4">
          <?php if (!$isFaqType): ?>
          <div class="col-12">
            <section class="announcement-section-card">
              <h5 class="announcement-section-title">Distribution and Audience Setup</h5>
              <div class="announcement-config-grid <?= $isPageType ? 'announcement-config-grid--page' : 'announcement-config-grid--delivery' ?>">
                <?php if ($isPageType): ?>
                <div class="announcement-config-panel" id="pagePlacementPanel">
                  <h6 class="announcement-card-title">Page Placement</h6>
                  <label class="form-label fw-semibold mb-2">Where should this appear?</label>
                  <div class="form-check mb-2">
                    <input class="form-check-input placement-checkbox" type="checkbox" value="public_news" id="placementPublicNews" name="placements[]" <?= $deliveryChannel === 'public_news' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="placementPublicNews">News Section</label>
                    <div class="form-text mt-0">Shows in the featured news area of the guest news page.</div>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input placement-checkbox" type="checkbox" value="announcement" id="placementPublic" name="placements[]" <?= in_array($deliveryChannel, ['public', 'website', 'all'], true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="placementPublic">Announcements</label>
                    <div class="form-text mt-0">Shows in the announcements area for guest and/or account views.</div>
                  </div>
                  <div id="dualPlacementNotice" class="rounded-3 border bg-light px-3 py-2 small text-body-secondary mt-3 mb-0 d-none" role="status">
                    Create a separate News Section version and Announcements version below.
                  </div>
                </div>

                <div class="announcement-config-panel" id="announcementDestinationsGroup">
                  <h6 class="announcement-card-title">Page Destinations</h6>
                  <p class="announcement-editor-helper mb-3">Choose where the announcement version should be shown once the Announcements placement is enabled.</p>
                  <div class="form-check mb-3">
                    <input class="form-check-input channel-checkbox" type="checkbox" value="public" id="channelGuestPage" name="channels[]" <?= $deliveryChannel === 'public' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="channelGuestPage">Guest Page</label>
                  </div>

                  <div class="form-check mb-0">
                    <input class="form-check-input channel-checkbox" type="checkbox" value="website" id="channelWebsite" name="channels[]" <?= $deliveryChannel === 'website' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="channelWebsite">Account Page</label>
                  </div>
                </div>
                <?php endif; ?>

                <div class="announcement-config-panel announcement-config-panel--audience-publishing">
                  <h6 class="announcement-card-title">Audience and Publishing</h6>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="audience_scope" id="audienceAll" value="all" checked>
                    <label class="form-check-label" for="audienceAll">All Residents</label>
                  </div>
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="audience_scope" id="audienceCustom" value="custom">
                    <label class="form-check-label" for="audienceCustom">Custom Audience</label>
                  </div>

                  <div id="customAudienceFields" class="row g-3 d-none">
                    <div class="col-12">
                      <label class="form-label mb-1">Area</label>
                      <p class="announcement-editor-helper mb-2">Choose the area that should receive this announcement.</p>
                      <select class="form-select" name="area" disabled>
                        <option value="">Select Area</option>
                        <?php foreach ($audienceAreaOptions as $areaOption): ?>
                          <option><?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12">
                      <label class="form-label mb-1">Role Group</label>
                      <p class="announcement-editor-helper mb-2">Filter recipients by role when this update is only for a specific group.</p>
                      <select class="form-select" name="role_group" disabled>
                        <option value="">Select Group</option>
                        <option>Officials</option>
                        <option>Employees</option>
                        <option>Residents</option>
                      </select>
                    </div>
                  </div>

                  <div class="announcement-create-divider"></div>
                  <div class="row g-3 announcement-publish-grid">
                    <div class="col-12 col-xl-6">
                      <label class="form-label mb-1">Schedule Date (optional)</label>
                      <input type="date" class="form-control" name="schedule_date">
                    </div>
                    <div class="col-12 col-xl-6">
                      <label class="form-label mb-1">Schedule Time (optional)</label>
                      <input type="time" class="form-control" name="schedule_time">
                    </div>
                  </div>
                </div>

                <?php if ($isDeliveryType): ?>
                <div class="announcement-config-panel announcement-config-panel--delivery">
                  <h6 class="announcement-card-title">Delivery</h6>
                  <p class="announcement-editor-helper mb-3">Choose the channels to send and compose the message details below.</p>
                  <div class="announcement-delivery-grid">
                    <div class="announcement-channel-item announcement-delivery-card">
                      <div class="form-check mb-0">
                        <input class="form-check-input channel-checkbox" type="checkbox" value="sms" id="channelSms" name="channels[]" <?= $deliveryChannel === 'sms' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="channelSms">SMS</label>
                      </div>
                      <div id="smsField" class="channel-field channel-field-collapsible is-collapsed" aria-hidden="true">
                        <label for="smsPreview" class="form-label mb-1">SMS Message</label>
                        <textarea id="smsPreview" class="form-control" rows="5" name="sms_message" maxlength="320"></textarea>
                        <small id="smsCounter" class="text-muted">0 / 320 characters</small>
                      </div>
                    </div>

                    <div class="announcement-channel-item announcement-delivery-card">
                      <div class="form-check mb-0">
                        <input class="form-check-input channel-checkbox" type="checkbox" value="email" id="channelEmail" name="channels[]" <?= $deliveryChannel === 'email' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="channelEmail">Email</label>
                      </div>
                      <div id="emailField" class="channel-field channel-field-collapsible is-collapsed" aria-hidden="true">
                        <label for="emailSubject" class="form-label mb-1">Email Subject</label>
                        <input id="emailSubject" type="text" class="form-control" name="email_subject" placeholder="Enter email subject">
                      </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
              </div>
            </section>
          </div>
          <?php endif; ?>

          <div class="col-12">
            <?php if ($isFaqType): ?>
            <section class="announcement-section-card announcement-faq-shell mb-4">
              <div class="announcement-faq-header">
                <div>
                  <h5 class="announcement-section-title mb-1">FAQ Entries</h5>
                  <p class="announcement-editor-helper mb-0">Create one FAQ content item with up to 20 questions and answers. These will be saved together and tracked as one FAQ page entry.</p>
                </div>
                <div class="announcement-faq-controls">
                  <label for="faqQuestionTarget" class="form-label mb-0 fw-semibold">Questions</label>
                  <select id="faqQuestionTarget" class="form-select form-select-sm announcement-faq-target-select" aria-label="FAQ question count">
                    <?php for ($i = 1; $i <= 20; $i++): ?>
                      <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                  </select>
                  <span id="faqItemCount" class="announcement-faq-count">0 / 20 Questions</span>
                </div>
              </div>
              <div id="faqItemsContainer" class="announcement-faq-list"></div>
            </section>
            <?php endif; ?>

            <?php if (!$isFaqType): ?>
            <div id="sharedContentFields">
              <section class="announcement-section-card">
                <div class="mb-3 announcement-primary-title-wrap">
                  <label for="announcementTitle" class="form-label fw-semibold"><?= htmlspecialchars($sharedMeta['title_label']) ?></label>
                  <input id="announcementTitle" name="title" type="text" class="form-control announcement-primary-title-input" placeholder="<?= htmlspecialchars($sharedMeta['title_placeholder']) ?>" required>
                </div>

                <div class="announcement-create-divider"></div>

                <div class="announcement-editor-panel">
                  <div class="announcement-editor-panel-head">
                    <div>
                      <label class="form-label fw-semibold mb-1"><?= htmlspecialchars($sharedMeta['body_label']) ?></label>
                      <p class="announcement-editor-helper mb-0"><?= htmlspecialchars($sharedMeta['body_helper']) ?></p>
                    </div>
                  </div>
                  <div id="announcementEditor"></div>
                  <input type="hidden" id="announcementContent" name="content_html">
                  <div id="sharedSidebarWarning" class="announcement-counter-note d-none mt-2">
                    <span id="sharedSidebarCounter" class="announcement-counter-text">0 characters</span>
                  </div>
                </div>
              </section>
            </div>
            <?php endif; ?>

            <?php if ($isPageType): ?>
            <div id="dualPlacementFields" class="d-none">
                <div class="row g-4">
                  <div class="col-12">
                    <div class="announcement-section-card announcement-dual-card h-100">
                      <h6 class="announcement-card-title announcement-placement-title">News Section</h6>
                      <div class="mb-3">
                        <label for="publicNewsTitle" class="form-label fw-semibold">Title</label>
                        <input id="publicNewsTitle" name="public_news_title" type="text" class="form-control announcement-secondary-title-input" placeholder="Enter main news title">
                      </div>
                      <div class="announcement-editor-panel">
                        <div class="announcement-editor-panel-head">
                          <div>
                            <label class="form-label fw-semibold mb-1"><?= htmlspecialchars($sharedMeta['body_label']) ?></label>
                            <p class="announcement-editor-helper mb-0">This version appears in the featured news area of the guest page.</p>
                          </div>
                        </div>
                        <div id="publicNewsEditor"></div>
                        <input type="hidden" id="publicNewsContent" name="public_news_content_html">
                        <div class="announcement-counter-note mt-2">
                          <span id="publicNewsCounter" class="announcement-counter-text">0 characters</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="col-12">
                    <div class="announcement-section-card announcement-dual-card h-100">
                      <h6 class="announcement-card-title announcement-placement-title">Announcements</h6>
                      <div class="mb-3">
                        <label for="publicAnnouncementTitle" class="form-label fw-semibold">Title</label>
                        <input id="publicAnnouncementTitle" name="public_title" type="text" class="form-control announcement-secondary-title-input" placeholder="Enter sidebar announcement title">
                      </div>
                      <div class="announcement-editor-panel">
                        <div class="announcement-editor-panel-head">
                          <div>
                            <label class="form-label fw-semibold mb-1"><?= htmlspecialchars($sharedMeta['body_label']) ?></label>
                            <p class="announcement-editor-helper mb-0">This version appears in the announcements area for guest and account views.</p>
                          </div>
                        </div>
                        <div id="publicAnnouncementEditor"></div>
                        <input type="hidden" id="publicAnnouncementContent" name="public_content_html">
                        <div class="announcement-counter-note mt-2">
                          <span id="publicAnnouncementCounter" class="announcement-counter-text">0 characters</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="announcement-sticky-actions mt-4">
          <div class="announcement-modal-footer-start">
            <button type="submit" name="submit_action" value="draft" class="btn btn-warning text-dark">Save as Draft</button>
            <?php if ($isSuperAdmin): ?>
              <button type="submit" id="btnPostAnnouncement" name="submit_action" value="approved" class="btn btn-primary text-white">Post</button>
            <?php else: ?>
              <button type="submit" name="submit_action" value="pending" class="btn btn-primary text-white">Submit for Review</button>
            <?php endif; ?>
          </div>
          <div class="announcement-modal-footer-end">
            <a href="<?= htmlspecialchars(appUrl('/Admin-End/Contents/Contents.php')) ?><?= $deliveryChannel !== 'all' ? '?channel=' . urlencode($deliveryChannel) : '' ?>" class="btn btn-outline-secondary">Close</a>
          </div>
        </div>
      </form>

      <?php if ($isSuperAdmin): ?>
        <div class="modal fade" id="modalSuperAdminPostConfirm" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header border-0 pb-0 bg-white">
                <h5 class="modal-title w-100 text-center text-dark">Confirm Post Content</h5>
              </div>
              <hr class="my-0">
              <div class="modal-body text-center">
                <p class="mb-0">Are you sure this content is ready to post?</p>
              </div>
              <div class="modal-footer border-0 pt-0 d-flex gap-2">
                <button type="button" class="btn btn-primary text-white flex-fill" id="btnConfirmPostAnnouncement">Yes, Post Announcement</button>
                <button type="button" class="btn btn-secondary flex-fill" data-bs-dismiss="modal">Later</button>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="../../summernote-0.9.0-dist/summernote-lite.min.js?v=20260307-2"></script>
  <script>
    (function () {
      const MAX_IMAGE_SIZE_BYTES = 25 * 1024 * 1024;
      const contentType = <?= json_encode($contentType) ?>;

      const sharedContentFields = document.getElementById("sharedContentFields");
      const dualPlacementFields = document.getElementById("dualPlacementFields");
      const sharedTitleInput = document.getElementById("announcementTitle");
      const contentInput = document.getElementById("announcementContent");
      const publicNewsTitleInput = document.getElementById("publicNewsTitle");
      const publicAnnouncementTitleInput = document.getElementById("publicAnnouncementTitle");
      const publicNewsContentInput = document.getElementById("publicNewsContent");
      const publicAnnouncementContentInput = document.getElementById("publicAnnouncementContent");
      const publicNewsCounter = document.getElementById("publicNewsCounter");
      const sharedSidebarWarning = document.getElementById("sharedSidebarWarning");
      const sharedSidebarCounter = document.getElementById("sharedSidebarCounter");
      const publicAnnouncementCounter = document.getElementById("publicAnnouncementCounter");
      const smsPreview = document.getElementById("smsPreview");
      const smsCounter = document.getElementById("smsCounter");
      const placementPublicNews = document.getElementById("placementPublicNews");
      const placementPublic = document.getElementById("placementPublic");
      const dualPlacementNotice = document.getElementById("dualPlacementNotice");
      const announcementDestinationsGroup = document.getElementById("announcementDestinationsGroup");
      const channelGuestPage = document.getElementById("channelGuestPage");
      const channelSms = document.getElementById("channelSms");
      const channelEmail = document.getElementById("channelEmail");
      const smsField = document.getElementById("smsField");
      const emailField = document.getElementById("emailField");
      const audienceAll = document.getElementById("audienceAll");
      const customAudienceFields = document.getElementById("customAudienceFields");
      const audienceCustom = document.getElementById("audienceCustom");
      const faqItemsContainer = document.getElementById("faqItemsContainer");
      const faqAddItemBtn = document.getElementById("faqAddItemBtn");
      const faqItemCount = document.getElementById("faqItemCount");
      const faqQuestionTarget = document.getElementById("faqQuestionTarget");
      const sharedEditorEl = $("#announcementEditor");
      const publicNewsEditorEl = $("#publicNewsEditor");
      const publicAnnouncementEditorEl = $("#publicAnnouncementEditor");
      const sharedEditorPlaceholder = <?= json_encode($sharedMeta['editor_placeholder']) ?>;
      const faqMaxItems = 20;
      const faqQuestionPlaceholders = [
        "How do I request a barangay certificate or clearance?",
        "How do I apply for a Barangay ID?",
        "How do I schedule an appointment with the barangay office?",
        "How do I file a complaint with the barangay?",
        "How long does barangay document processing take?",
        "What documents are required for barangay services?"
      ];
      let smsManuallyEdited = false;
      const fullToolbar = [
        ["style", ["style"]],
        ["font", ["bold", "italic", "underline", "clear"]],
        ["fontname", ["fontname"]],
        ["fontsize", ["fontsize"]],
        ["color", ["color"]],
        ["para", ["ul", "ol", "paragraph"]],
        ["table", ["table"]],
        ["insert", ["link", "picture"]],
        ["view", ["fullscreen", "codeview", "help"]]
      ];

      function escapeHtml(value) {
        return String(value || "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/\"/g, "&quot;")
          .replace(/'/g, "&#39;");
      }

      function getPlainTextFromHtml(html) {
        const temp = document.createElement("div");
        temp.innerHTML = html;
        return (temp.textContent || temp.innerText || "").trim();
      }

      function getEditorCode(editorInstance) {
        return editorInstance && editorInstance.length ? editorInstance.summernote("code") : "";
      }

      function isDualPlacementSelected() {
        return contentType === "page" && !!(placementPublicNews && placementPublicNews.checked && placementPublic && placementPublic.checked);
      }

      function buildFaqItemMarkup(index, question = "", answer = "") {
        const placeholder = faqQuestionPlaceholders[index % faqQuestionPlaceholders.length] || "Enter FAQ question";
        return `
          <article class="announcement-faq-item" data-faq-item>
            <div class="announcement-faq-item-head">
              <h6 class="announcement-card-title mb-0">Question ${index + 1}</h6>
            </div>
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label fw-semibold">Question</label>
                <input type="text" class="form-control faq-question-input" name="faq_questions[]" value="${escapeHtml(question)}" placeholder="${escapeHtml(placeholder)}">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Answer</label>
                <div class="faq-answer-editor" data-initial-answer="${escapeHtml(answer)}"></div>
                <input type="hidden" class="faq-answer-input" name="faq_answers[]" value="${escapeHtml(answer)}">
              </div>
            </div>
            <div class="announcement-faq-item-actions">
              <button type="button" class="btn btn-outline-primary btn-sm faq-add-btn">Add</button>
              <button type="button" class="btn btn-outline-danger btn-sm faq-remove-btn">Remove</button>
            </div>
          </article>
        `;
      }

      function initFaqEditor(item) {
        const editorHost = item?.querySelector('.faq-answer-editor');
        const hiddenInput = item?.querySelector('.faq-answer-input');
        if (!editorHost || !hiddenInput || editorHost.dataset.initialized === 'true') {
          return;
        }
        const initialAnswer = editorHost.dataset.initialAnswer || hiddenInput.value || '';
        const editorInstance = $(editorHost);
        editorInstance.summernote({
          placeholder: 'Write the answer here...',
          height: 180,
          minHeight: 160,
          dialogsInBody: true,
          toolbar: fullToolbar,
          callbacks: {
            onInit: function () {
              editorInstance.summernote('code', initialAnswer);
              hiddenInput.value = editorInstance.summernote('code');
            },
            onChange: function (contents) {
              hiddenInput.value = contents;
            },
            onImageUpload: async function (files) {
              for (const file of files) {
                if (!file) continue;
                if (file.size > MAX_IMAGE_SIZE_BYTES) {
                  alert('Image must be 25MB or less.');
                  continue;
                }
                try {
                  const imageUrl = await uploadEditorImage(file);
                  editorInstance.summernote('insertImage', imageUrl);
                } catch (err) {
                  alert(err.message || 'Unable to upload image.');
                }
              }
            }
          }
        });
        editorHost.dataset.initialized = 'true';
      }

      function destroyFaqEditor(item) {
        const editorHost = item?.querySelector('.faq-answer-editor');
        const hiddenInput = item?.querySelector('.faq-answer-input');
        if (!editorHost || editorHost.dataset.initialized !== 'true') {
          return;
        }
        const editorInstance = $(editorHost);
        if (hiddenInput) {
          hiddenInput.value = editorInstance.summernote('code');
        }
        editorInstance.summernote('destroy');
        editorHost.dataset.initialized = 'false';
      }

      function collectFaqItems() {
        if (!faqItemsContainer) {
          return [];
        }
        return Array.from(faqItemsContainer.querySelectorAll('[data-faq-item]')).map((item) => ({
          question: (item.querySelector('.faq-question-input')?.value || '').trim(),
          answer: (item.querySelector('.faq-answer-input')?.value || '').trim()
        }));
      }

      function updateFaqCount() {
        if (!faqItemsContainer || !faqItemCount) {
          return;
        }
        const count = faqItemsContainer.querySelectorAll('[data-faq-item]').length;
        faqItemCount.textContent = `${count} / ${faqMaxItems} Questions`;
        if (faqQuestionTarget) {
          faqQuestionTarget.value = String(count || 1);
        }
      }

      function renumberFaqItems() {
        if (!faqItemsContainer) {
          return;
        }
        Array.from(faqItemsContainer.querySelectorAll('[data-faq-item]')).forEach((item, index) => {
          const heading = item.querySelector('.announcement-card-title');
          if (heading) {
            heading.textContent = `Question ${index + 1}`;
          }
          const questionInput = item.querySelector('.faq-question-input');
          if (questionInput && !questionInput.value.trim()) {
            questionInput.placeholder = faqQuestionPlaceholders[index % faqQuestionPlaceholders.length] || 'Enter FAQ question';
          }
        });
        updateFaqCount();
      }

      function addFaqItem(question = '', answer = '') {
        if (!faqItemsContainer) {
          return;
        }
        const currentCount = faqItemsContainer.querySelectorAll('[data-faq-item]').length;
        if (currentCount >= faqMaxItems) {
          return;
        }
        faqItemsContainer.insertAdjacentHTML('beforeend', buildFaqItemMarkup(currentCount, question, answer));
        const newItem = faqItemsContainer.querySelectorAll('[data-faq-item]')[currentCount];
        if (newItem) {
          initFaqEditor(newItem);
        }
        renumberFaqItems();
      }

      function setFaqItemTargetCount(targetCount) {
        if (!faqItemsContainer) {
          return;
        }
        const desiredCount = Math.max(1, Math.min(faqMaxItems, Number(targetCount) || 1));
        let currentCount = faqItemsContainer.querySelectorAll('[data-faq-item]').length;
        while (currentCount < desiredCount) {
          addFaqItem();
          currentCount = faqItemsContainer.querySelectorAll('[data-faq-item]').length;
        }
        while (currentCount > desiredCount) {
          const lastItem = faqItemsContainer.querySelector('[data-faq-item]:last-child');
          if (!lastItem) break;
          destroyFaqEditor(lastItem);
          lastItem.remove();
          currentCount -= 1;
        }
        renumberFaqItems();
      }

      function updateEditorOutputs() {
        const sharedHtml = getEditorCode(sharedEditorEl);
        const publicNewsHtml = getEditorCode(publicNewsEditorEl);
        const publicAnnouncementHtml = getEditorCode(publicAnnouncementEditorEl);
        const dualPlacementActive = isDualPlacementSelected();
        const sharedPlain = getPlainTextFromHtml(sharedHtml);
        const publicNewsPlain = getPlainTextFromHtml(publicNewsHtml);
        const publicAnnouncementPlain = getPlainTextFromHtml(publicAnnouncementHtml);
        const sidebarOnlyMode = !dualPlacementActive && placementPublic && placementPublic.checked && (!placementPublicNews || !placementPublicNews.checked);

        if (contentInput) {
          contentInput.value = dualPlacementActive ? publicNewsHtml : sharedHtml;
        }
        if (publicNewsContentInput) {
          publicNewsContentInput.value = publicNewsHtml;
        }
        if (publicAnnouncementContentInput) {
          publicAnnouncementContentInput.value = publicAnnouncementHtml;
        }

        if (dualPlacementActive && sharedTitleInput) {
          sharedTitleInput.value = (publicNewsTitleInput.value || publicAnnouncementTitleInput.value || "").trim();
        }

        const previewSource = dualPlacementActive ? publicNewsHtml : sharedHtml;
        const plain = getPlainTextFromHtml(previewSource);
        if (smsPreview && (!smsManuallyEdited || !smsPreview.value.trim())) {
          smsPreview.value = plain;
        }
        if (smsCounter) {
          smsCounter.textContent = (smsPreview?.value || "").length + " / 320 characters";
        }

        if (sharedSidebarWarning && sharedSidebarCounter) {
          sharedSidebarWarning.classList.toggle("d-none", !sidebarOnlyMode);
          sharedSidebarCounter.textContent = sharedPlain.length + " characters";
        }

        if (publicAnnouncementCounter) {
          publicAnnouncementCounter.textContent = publicAnnouncementPlain.length + " characters";
        }

        if (publicNewsCounter) {
          publicNewsCounter.textContent = publicNewsPlain.length + " characters";
        }
      }

      function toggleChannelFields() {
        if (!smsField || !emailField || !channelSms || !channelEmail) {
          return;
        }
        const showSms = !!channelSms.checked;
        const showEmail = !!channelEmail.checked;
        smsField.classList.toggle("is-collapsed", !showSms);
        smsField.setAttribute("aria-hidden", showSms ? "false" : "true");
        emailField.classList.toggle("is-collapsed", !showEmail);
        emailField.setAttribute("aria-hidden", showEmail ? "false" : "true");
      }

      function togglePlacementGuidance() {
        if (contentType !== "page") {
          return;
        }
        const hasNewsPlacement = placementPublicNews && placementPublicNews.checked;
        const hasAnnouncementPlacement = placementPublic && placementPublic.checked;
        const dualPlacementActive = hasNewsPlacement && hasAnnouncementPlacement;
        if (dualPlacementNotice) {
          dualPlacementNotice.classList.toggle("d-none", !dualPlacementActive);
          dualPlacementNotice.textContent = dualPlacementActive
            ? "Create a separate News Section version and Announcements version below."
            : "";
        }
        if (sharedContentFields && dualPlacementFields) {
          sharedContentFields.classList.toggle("d-none", dualPlacementActive);
          dualPlacementFields.classList.toggle("d-none", !dualPlacementActive);
        }
        if (announcementDestinationsGroup) {
          announcementDestinationsGroup.classList.toggle("d-none", !hasAnnouncementPlacement);
        }
        if (!hasAnnouncementPlacement) {
          if (channelGuestPage) channelGuestPage.checked = false;
          const accountPageCheckbox = document.getElementById("channelWebsite");
          if (accountPageCheckbox) accountPageCheckbox.checked = false;
        }
        updateEditorOutputs();
      }

      function applyContentTypeMode() {
        if (contentType === "page") {
          togglePlacementGuidance();
          return;
        }
        if (contentType === "delivery") {
          if (sharedContentFields) sharedContentFields.classList.remove("d-none");
          if (dualPlacementFields) dualPlacementFields.classList.add("d-none");
        }
      }

      function toggleAudienceFields() {
        if (!customAudienceFields || !audienceAll || !audienceCustom) {
          return;
        }
        const useCustomAudience = audienceCustom.checked;
        customAudienceFields.classList.toggle("d-none", !useCustomAudience);
        customAudienceFields.querySelectorAll("input, select, textarea").forEach((field) => {
          field.disabled = !useCustomAudience;
          if (!useCustomAudience) {
            if (field.tagName === "SELECT") {
              field.selectedIndex = 0;
            } else if (field.type === "checkbox" || field.type === "radio") {
              field.checked = false;
            } else {
              field.value = "";
            }
          }
        });
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

        tooltips.forEach(([selector, label]) => {
          document.querySelectorAll(".note-toolbar " + selector).forEach((el) => {
            el.setAttribute("title", label);
            el.setAttribute("aria-label", label);
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

      function buildEditorConfig(placeholder, editorInstance) {
        return {
          placeholder: placeholder,
          height: 260,
          minHeight: 220,
          dialogsInBody: true,
          fontNames: [
            "Arial", "Arial Black", "Comic Sans MS", "Courier New", "Helvetica", "Impact",
            "Lucida Grande", "Tahoma", "Times New Roman", "Trebuchet MS", "Verdana", "Georgia"
          ],
          fontSizes: ["8", "9", "10", "11", "12", "14", "16", "18", "20", "24", "28", "32", "36", "48", "64", "82", "150"],
          toolbar: fullToolbar,
          callbacks: {
            onChange: function () {
              updateEditorOutputs();
            },
            onImageUpload: async function (files) {
              for (const file of files) {
                if (!file) continue;
                if (file.size > MAX_IMAGE_SIZE_BYTES) {
                  alert("Image must be 25MB or less.");
                  continue;
                }
                try {
                  const imageUrl = await uploadEditorImage(file);
                  editorInstance.summernote("insertImage", imageUrl);
                } catch (err) {
                  alert(err.message || "Unable to upload image.");
                }
              }
              updateEditorOutputs();
            }
          }
        };
      }

      function initEditor(editorInstance, placeholder) {
        if (!editorInstance || !editorInstance.length) {
          return;
        }
        editorInstance.summernote(buildEditorConfig(placeholder, editorInstance));
        const toolbarGroups = editorInstance.next(".note-editor").find(".note-toolbar .note-btn-group").length;
        if (toolbarGroups <= 1) {
          editorInstance.summernote("destroy");
          editorInstance.summernote(buildEditorConfig(placeholder, editorInstance));
        }
      }

      initEditor(sharedEditorEl, sharedEditorPlaceholder);
      initEditor(publicNewsEditorEl, "Write the main news content here...");
      initEditor(publicAnnouncementEditorEl, "Write the sidebar announcement content here...");
      applyToolbarTooltips();
      applyContentTypeMode();

      if (smsPreview) {
        smsPreview.addEventListener("input", function () {
          smsManuallyEdited = true;
          if (smsCounter) {
            smsCounter.textContent = smsPreview.value.length + " / 320 characters";
          }
        });
      }

      if (contentType === "faq") {
        setFaqItemTargetCount(faqQuestionTarget ? Number(faqQuestionTarget.value || 1) : 1);
      }

      updateEditorOutputs();

      const createForm = document.querySelector("form.announcement-create-shell");
      if (createForm) {
        createForm.addEventListener("submit", function (event) {
          if (contentType === "faq") {
            faqItemsContainer?.querySelectorAll('[data-faq-item]').forEach((item) => {
              const editorHost = item.querySelector('.faq-answer-editor');
              const hiddenInput = item.querySelector('.faq-answer-input');
              if (editorHost && hiddenInput && editorHost.dataset.initialized === 'true') {
                hiddenInput.value = $(editorHost).summernote('code');
              }
            });
            const faqItems = collectFaqItems().filter((item) => item.question !== "" || item.answer !== "");
            if (faqItems.length === 0) {
              event.preventDefault();
              alert("Add at least one FAQ question and answer before saving.");
              return;
            }
            if (faqItems.length > faqMaxItems) {
              event.preventDefault();
              alert("You can only save up to 20 FAQ questions in one content item.");
              return;
            }
            const hasIncompleteFaq = faqItems.some((item) => item.question === "" || item.answer === "");
            if (hasIncompleteFaq) {
              event.preventDefault();
              alert("Complete both the question and answer for every FAQ entry before saving.");
              return;
            }
            return;
          }

          const dualPlacementActive = isDualPlacementSelected();
          const hasAnnouncementPlacement = !!(placementPublic && placementPublic.checked);
          const hasAnnouncementDestination = !!((channelGuestPage && channelGuestPage.checked) || (document.getElementById("channelWebsite") && document.getElementById("channelWebsite").checked));
          if (contentType === "page" && hasAnnouncementPlacement && !hasAnnouncementDestination) {
            event.preventDefault();
            alert("Select Guest Page or Account Page when Announcements is selected.");
            return;
          }
          if (dualPlacementActive) {
            const publicNewsTitle = (publicNewsTitleInput.value || "").trim();
            const publicAnnouncementTitle = (publicAnnouncementTitleInput.value || "").trim();
            const publicNewsBody = getPlainTextFromHtml(getEditorCode(publicNewsEditorEl));
            const publicAnnouncementBody = getPlainTextFromHtml(getEditorCode(publicAnnouncementEditorEl));
            if (publicNewsTitle === "" || publicAnnouncementTitle === "" || publicNewsBody === "" || publicAnnouncementBody === "") {
              event.preventDefault();
              alert("Fill in both the Main News and Announcements title and body before submitting.");
              return;
            }
          }
          updateEditorOutputs();
        });
      }

      <?php if ($isSuperAdmin): ?>
        const postBtn = document.getElementById("btnPostAnnouncement");
        const postConfirmModalEl = document.getElementById("modalSuperAdminPostConfirm");
        const postConfirmBtn = document.getElementById("btnConfirmPostAnnouncement");
        let superAdminPostConfirmed = false;

        if (createForm && postBtn && postConfirmModalEl && postConfirmBtn) {
          const postConfirmModal = bootstrap.Modal.getOrCreateInstance(postConfirmModalEl, {
            backdrop: "static",
            keyboard: false
          });

          createForm.addEventListener("submit", function (event) {
            const submitter = event.submitter || null;
            const isPostAction = submitter === postBtn || (submitter && submitter.value === "approved");
            if (isPostAction && !superAdminPostConfirmed) {
              event.preventDefault();
              postConfirmModal.show();
            }
          });

          postConfirmBtn.addEventListener("click", function () {
            superAdminPostConfirmed = true;
            postConfirmModal.hide();
            if (typeof createForm.requestSubmit === "function") {
              createForm.requestSubmit(postBtn);
              return;
            }
            createForm.submit();
          });

          postConfirmModalEl.addEventListener("hidden.bs.modal", function () {
            superAdminPostConfirmed = false;
          });
        }
      <?php endif; ?>

      document.querySelectorAll(".channel-checkbox").forEach((el) => {
        el.addEventListener("change", toggleChannelFields);
      });
      toggleChannelFields();

      document.querySelectorAll(".placement-checkbox").forEach((el) => {
        el.addEventListener("change", togglePlacementGuidance);
      });
      togglePlacementGuidance();

      [sharedTitleInput, publicNewsTitleInput, publicAnnouncementTitleInput].forEach((el) => {
        if (!el) return;
        el.addEventListener("input", updateEditorOutputs);
      });

      document.querySelectorAll("input[name='audience_scope']").forEach((el) => {
        el.addEventListener("change", toggleAudienceFields);
      });
      toggleAudienceFields();

      if (faqQuestionTarget) {
        faqQuestionTarget.addEventListener('change', function () {
          setFaqItemTargetCount(faqQuestionTarget.value);
        });
      }

      if (faqItemsContainer) {
        faqItemsContainer.addEventListener('click', function (event) {
          const addBtn = event.target.closest('.faq-add-btn');
          if (addBtn) {
            event.preventDefault();
            addFaqItem();
            return;
          }
          const removeBtn = event.target.closest('.faq-remove-btn');
          if (removeBtn) {
            event.preventDefault();
            const item = removeBtn.closest('[data-faq-item]');
            if (!item) {
              return;
            }
            if (faqItemsContainer.querySelectorAll('[data-faq-item]').length <= 1) {
              alert('At least one FAQ item is required.');
              return;
            }
            destroyFaqEditor(item);
            item.remove();
            renumberFaqItems();
          }
        });
      }

      applyContentTypeMode();
    })();
  </script>
</body>
</html>














