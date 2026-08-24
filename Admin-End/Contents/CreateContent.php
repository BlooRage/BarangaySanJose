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
  'page' => ['title' => 'Create Page Announcement', 'description' => 'Create public announcements for the guest and account pages.'],
  'delivery' => ['title' => 'Create SMS and Email Announcement', 'description' => 'Create delivery-first content for SMS and/or email recipients.'],
  'faq' => ['title' => 'Create FAQ Page Content', 'description' => 'Create an FAQ item that can be tracked inside content management.']
];
$meta = $typeMeta[$contentType];
$isPageType = $contentType === 'page';
$isDeliveryType = $contentType === 'delivery';
$isFaqType = $contentType === 'faq';
$usesWizard = $isPageType || $isDeliveryType;
$guideMeta = [
  'page' => [
    'kicker' => 'Before You Start',
    'title' => 'Page Announcement Tips',
    'subtitle' => 'Create announcement content for the public areas shown on the guest and account pages.',
    'text' => 'Use a clear public-facing title, a short opening summary, and readable sections for residents scanning page announcements.',
    'blocks' => [
      ['title' => 'Page Placement', 'items' => ['Announcements appear on guest and/or account views', 'Choose Guest Page, Account Page, or both', 'Use Create News for full news articles']],
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
    'body_helper' => 'Use headings, lists, and short paragraphs so the announcement stays readable on both guest and account announcement areas.',
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
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css?v=20260722-wizard-6">
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

      <form class="announcement-create-shell p-3 p-md-4 shadow-sm<?= $usesWizard ? ' announcement-create-wizard' : '' ?>" action="../../PhpFiles/Admin-End/announcementsCreation.php" method="post">
        <input type="hidden" name="channel_context" value="<?= htmlspecialchars($deliveryChannel) ?>">
        <input type="hidden" name="content_type" value="<?= htmlspecialchars($contentType) ?>">
        <?php if ($usesWizard): ?>
        <nav class="announcement-wizard-progress" aria-label="Announcement creation progress">
          <button type="button" class="announcement-wizard-step is-active" data-wizard-step="1" aria-current="step">
            <span class="announcement-wizard-step-number">1</span>
            <span><strong><?= $isPageType ? 'Placement' : 'Delivery' ?></strong><small><?= $isPageType ? 'Choose the pages' : 'Choose SMS or email' ?></small></span>
          </button>
          <span class="announcement-wizard-line" aria-hidden="true"></span>
          <button type="button" class="announcement-wizard-step" data-wizard-step="2" disabled>
            <span class="announcement-wizard-step-number">2</span>
            <span><strong>Audience</strong><small>Choose residents</small></span>
          </button>
          <span class="announcement-wizard-line" aria-hidden="true"></span>
          <button type="button" class="announcement-wizard-step" data-wizard-step="3" disabled>
            <span class="announcement-wizard-step-number">3</span>
            <span><strong>Write</strong><small>Title and message</small></span>
          </button>
          <span class="announcement-wizard-line" aria-hidden="true"></span>
          <button type="button" class="announcement-wizard-step" data-wizard-step="4" disabled>
            <span class="announcement-wizard-step-number">4</span>
            <span><strong>Review</strong><small>Schedule and publish</small></span>
          </button>
        </nav>
        <?php endif; ?>
        <div class="row g-4">
          <?php if (!$isFaqType): ?>
          <div class="col-12"<?= $usesWizard ? ' data-wizard-panel="1,2"' : '' ?>>
            <section class="announcement-section-card">
              <?php if ($isPageType): ?>
              <div class="announcement-wizard-panel-heading" data-wizard-heading="1">
                <span class="announcement-wizard-eyebrow">Step 1 of 4</span>
                <h5 class="announcement-section-title mb-1">Choose where this announcement will appear</h5>
                <p class="announcement-editor-helper mb-0">Select the guest page, account page, or both.</p>
              </div>
              <div class="announcement-wizard-panel-heading" data-wizard-heading="2" hidden>
                <span class="announcement-wizard-eyebrow">Step 2 of 4</span>
                <h5 class="announcement-section-title mb-1">Choose the audience</h5>
                <p class="announcement-editor-helper mb-0">Share with all residents, or narrow the announcement to selected areas and roles.</p>
              </div>
              <?php elseif ($isDeliveryType): ?>
              <div class="announcement-wizard-panel-heading" data-wizard-heading="1">
                <span class="announcement-wizard-eyebrow">Step 1 of 4</span>
                <h5 class="announcement-section-title mb-1">Choose the delivery channels</h5>
                <p class="announcement-editor-helper mb-0">Select SMS, email, or both. You will write the message details in Step 3.</p>
              </div>
              <div class="announcement-wizard-panel-heading" data-wizard-heading="2" hidden>
                <span class="announcement-wizard-eyebrow">Step 2 of 4</span>
                <h5 class="announcement-section-title mb-1">Choose the audience</h5>
                <p class="announcement-editor-helper mb-0">Send to all residents, or narrow the recipients to selected areas and roles.</p>
              </div>
              <?php else: ?>
              <h5 class="announcement-section-title">Distribution and Audience Setup</h5>
              <?php endif; ?>
              <?php if ($usesWizard): ?><div class="announcement-wizard-error d-none" data-wizard-error role="alert" aria-live="polite"></div><?php endif; ?>
              <div class="announcement-config-grid <?= $isPageType ? 'announcement-config-grid--page' : 'announcement-config-grid--delivery' ?>">
                <?php if ($isPageType): ?>
                <div class="announcement-config-panel" id="pagePlacementPanel" data-wizard-stage="1">
                  <h6 class="announcement-card-title">Page Placement</h6>
                  <label class="form-label fw-semibold mb-2">Where should this announcement appear?</label>
                  <input class="form-check-input placement-checkbox d-none" type="checkbox" value="public_news" id="placementPublicNews" name="placements[]" hidden aria-hidden="true" tabindex="-1">
                  <div class="rounded-3 border bg-light px-3 py-2 small text-body-secondary mb-3">
                    News articles now use the separate <strong>Create News</strong> workflow.
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

                <div class="announcement-config-panel" id="announcementDestinationsGroup" data-wizard-stage="1">
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

                <div class="announcement-config-panel announcement-config-panel--audience-publishing"<?= $usesWizard ? ' data-wizard-stage="2" hidden' : '' ?>>
                  <h6 class="announcement-card-title">Audience</h6>
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
                      <div class="announcement-audience-group">
                        <div class="announcement-audience-group-head">
                          <label class="form-label mb-1">Select Area</label>
                          <p class="announcement-editor-helper mb-0">Choose one or more areas that should receive this announcement.</p>
                        </div>
                        <div class="announcement-checkbox-grid announcement-checkbox-grid--area" data-custom-audience-group="area">
                          <?php foreach ($audienceAreaOptions as $areaOption): ?>
                          <label class="announcement-checkbox-card announcement-checkbox-card--area">
                            <input class="form-check-input" type="checkbox" name="area[]" value="<?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?>" disabled>
                            <span><?= htmlspecialchars($areaOption, ENT_QUOTES, 'UTF-8') ?></span>
                          </label>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </div>
                    <div class="col-12">
                      <div class="announcement-audience-group">
                        <div class="announcement-audience-group-head">
                          <label class="form-label mb-1">Role Group</label>
                          <p class="announcement-editor-helper mb-0">Filter recipients by role when this update is only for a specific group.</p>
                        </div>
                        <div class="announcement-checkbox-grid announcement-checkbox-grid--role" data-custom-audience-group="role_group">
                          <?php foreach (['Officials', 'Personnel', 'Residents'] as $roleGroupOption): ?>
                          <label class="announcement-checkbox-card announcement-checkbox-card--role">
                            <input class="form-check-input" type="checkbox" name="role_group[]" value="<?= htmlspecialchars($roleGroupOption, ENT_QUOTES, 'UTF-8') ?>" disabled>
                            <span><?= htmlspecialchars($roleGroupOption, ENT_QUOTES, 'UTF-8') ?></span>
                          </label>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </div>
                  </div>

                  <?php if (!$usesWizard): ?>
                  <div class="announcement-create-divider"></div>
                  <div class="row g-3 announcement-publish-grid">
                    <div class="col-12 col-xl-6">
                      <label class="form-label mb-1">Schedule Date (optional)</label>
                      <input type="date" class="form-control" name="schedule_date" data-date-modal-style="calendar">
                    </div>
                    <div class="col-12 col-xl-6">
                      <label class="form-label mb-1">Schedule Time (optional)</label>
                      <input type="time" class="form-control" name="schedule_time">
                    </div>
                  </div>
                  <?php endif; ?>
                </div>

                <?php if ($isDeliveryType): ?>
                <div class="announcement-config-panel announcement-config-panel--delivery" data-wizard-stage="1">
                  <h6 class="announcement-card-title">Delivery</h6>
                  <p class="announcement-editor-helper mb-3">Choose one or both channels for this announcement.</p>
                  <div class="announcement-delivery-grid">
                    <div class="announcement-channel-item announcement-delivery-card">
                      <div class="form-check mb-0">
                        <input class="form-check-input channel-checkbox" type="checkbox" value="sms" id="channelSms" name="channels[]" <?= $deliveryChannel === 'sms' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="channelSms">SMS</label>
                      </div>
                    </div>

                    <div class="announcement-channel-item announcement-delivery-card">
                      <div class="form-check mb-0">
                        <input class="form-check-input channel-checkbox" type="checkbox" value="email" id="channelEmail" name="channels[]" <?= $deliveryChannel === 'email' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="channelEmail">Email</label>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
              </div>
            </section>
          </div>
          <?php endif; ?>

          <div class="col-12"<?= $usesWizard ? ' data-wizard-panel="3" hidden' : '' ?>>
            <?php if ($isFaqType): ?>
            <section class="announcement-section-card announcement-faq-shell mb-4">
              <div id="faqItemsContainer" class="announcement-faq-list"></div>
            </section>
            <?php endif; ?>

            <?php if (!$isFaqType): ?>
            <div id="sharedContentFields">
              <section class="announcement-section-card">
                <?php if ($usesWizard): ?>
                <div class="announcement-wizard-panel-heading">
                  <span class="announcement-wizard-eyebrow">Step 3 of 4</span>
                  <h5 class="announcement-section-title mb-1"><?= $isPageType ? 'Write the announcement' : 'Write the message content' ?></h5>
                  <p class="announcement-editor-helper mb-0"><?= $isPageType ? 'Lead with the key update, then add short paragraphs or lists for the details.' : 'Write the complete message that supports the selected SMS and email delivery.' ?></p>
                </div>
                <div class="announcement-wizard-error d-none" data-wizard-error role="alert" aria-live="polite"></div>
                <?php endif; ?>
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

                <?php if ($isDeliveryType): ?>
                <input id="smsPreview" type="hidden" name="sms_message">
                <div id="deliveryEmailDetails" hidden>
                  <div class="announcement-create-divider"></div>
                  <h6 class="announcement-card-title mb-1">Email details</h6>
                  <p class="announcement-editor-helper mb-3">Add a subject for the email selected in Step 1.</p>
                  <div class="announcement-delivery-grid">
                    <div class="announcement-delivery-card" id="emailComposeCard" hidden>
                      <h6 class="announcement-card-title mb-0">Email</h6>
                      <div id="emailField" class="channel-field channel-field-collapsible is-collapsed" aria-hidden="true">
                        <label for="emailSubject" class="form-label mb-1">Email Subject</label>
                        <input id="emailSubject" type="text" class="form-control" name="email_subject" placeholder="Enter email subject">
                      </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
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

          <?php if ($usesWizard): ?>
          <div class="col-12" data-wizard-panel="4" hidden>
            <section class="announcement-section-card">
              <div class="announcement-wizard-panel-heading">
                <span class="announcement-wizard-eyebrow">Step 4 of 4</span>
                <h5 class="announcement-section-title mb-1">Review and publish</h5>
                <p class="announcement-editor-helper mb-0">Check the details below, then publish now or choose an optional schedule.</p>
              </div>

              <div class="announcement-review-grid">
                <div class="announcement-review-item">
                  <span><?= $isPageType ? 'Page destinations' : 'Delivery channels' ?></span>
                  <strong id="wizardReviewDestinations">—</strong>
                  <button type="button" class="announcement-review-edit" data-wizard-go="1">Edit <?= $isPageType ? 'setup' : 'delivery' ?></button>
                </div>
                <div class="announcement-review-item">
                  <span>Audience</span>
                  <strong id="wizardReviewAudience">All Residents</strong>
                  <button type="button" class="announcement-review-edit" data-wizard-go="2">Edit audience</button>
                </div>
                <div class="announcement-review-item announcement-review-item--wide">
                  <span>Announcement title</span>
                  <strong id="wizardReviewTitle">—</strong>
                  <button type="button" class="announcement-review-edit" data-wizard-go="3">Edit content</button>
                </div>
                <?php if ($isDeliveryType): ?>
                <div class="announcement-review-item" id="wizardReviewSmsItem">
                  <span>SMS preview</span>
                  <strong id="wizardReviewSms">—</strong>
                  <button type="button" class="announcement-review-edit" data-wizard-go="3">Edit message</button>
                </div>
                <div class="announcement-review-item" id="wizardReviewEmailItem">
                  <span>Email subject</span>
                  <strong id="wizardReviewEmail">—</strong>
                  <button type="button" class="announcement-review-edit" data-wizard-go="3">Edit email</button>
                </div>
                <?php endif; ?>
                <div class="announcement-review-item announcement-review-item--wide">
                  <span>Message preview</span>
                  <strong id="wizardReviewMessage">—</strong>
                  <button type="button" class="announcement-review-edit" data-wizard-go="3">Edit message</button>
                </div>
              </div>

              <div class="announcement-create-divider"></div>
              <h6 class="announcement-card-title mb-1">Publishing schedule</h6>
              <p class="announcement-editor-helper mb-3">Leave both fields blank to publish as soon as the announcement is approved.</p>
              <div class="row g-3 announcement-publish-grid">
                <div class="col-12 col-md-6">
                  <label class="form-label mb-1">Schedule Date (optional)</label>
                  <input type="date" class="form-control" name="schedule_date" data-date-modal-style="calendar">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label mb-1">Schedule Time (optional)</label>
                  <input type="time" class="form-control" name="schedule_time">
                </div>
              </div>
            </section>
          </div>
          <?php endif; ?>
        </div>

        <div class="announcement-sticky-actions mt-4<?= $usesWizard ? ' announcement-wizard-actions' : '' ?>">
          <?php if ($usesWizard): ?>
          <button type="button" id="wizardBackBtn" class="btn btn-outline-secondary" hidden><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Back</button>
          <span id="wizardStepHint" class="announcement-wizard-action-hint">Next: choose the audience</span>
          <button type="button" id="wizardNextBtn" class="btn btn-primary text-white">Continue<i class="fa-solid fa-arrow-right ms-2" aria-hidden="true"></i></button>
          <?php endif; ?>
          <div class="announcement-modal-footer-start<?= $usesWizard ? ' announcement-wizard-submit-actions' : '' ?>">
            <?php if ($usesWizard): ?><button type="button" class="btn btn-outline-secondary" data-wizard-go="3"><i class="fa-solid fa-arrow-left me-2" aria-hidden="true"></i>Back</button><?php endif; ?>
            <button type="submit" name="submit_action" value="draft" class="btn btn-warning text-dark">Save as Draft</button>
            <?php if ($isSuperAdmin): ?>
              <button type="submit" id="btnPostAnnouncement" name="submit_action" value="approved" class="btn btn-primary text-white">Post</button>
            <?php else: ?>
              <button type="submit" name="submit_action" value="pending" class="btn btn-primary text-white">Submit for Review</button>
            <?php endif; ?>
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
      <div class="modal fade" id="appDialogModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title" id="appDialogTitle">Notice</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-2">
              <p class="mb-0" id="appDialogMessage"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-outline-secondary" id="appDialogCancelBtn" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-primary text-white" id="appDialogConfirmBtn">OK</button>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="../../summernote-0.9.0-dist/summernote-lite.min.js?v=20260307-2"></script>
  <script>
    (function () {
      const MAX_IMAGE_SIZE_BYTES = 50 * 1024 * 1024;
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
      const placementPublicNews = document.getElementById("placementPublicNews");
      const placementPublic = document.getElementById("placementPublic");
      const dualPlacementNotice = document.getElementById("dualPlacementNotice");
      const announcementDestinationsGroup = document.getElementById("announcementDestinationsGroup");
      const channelGuestPage = document.getElementById("channelGuestPage");
      const channelSms = document.getElementById("channelSms");
      const channelEmail = document.getElementById("channelEmail");
      const emailField = document.getElementById("emailField");
      const emailComposeCard = document.getElementById("emailComposeCard");
      const deliveryEmailDetails = document.getElementById("deliveryEmailDetails");
      const emailSubject = document.getElementById("emailSubject");
      const audienceAll = document.getElementById("audienceAll");
      const customAudienceFields = document.getElementById("customAudienceFields");
      const audienceCustom = document.getElementById("audienceCustom");
      const faqItemsContainer = document.getElementById("faqItemsContainer");
      const faqAddItemBtn = document.getElementById("faqAddItemBtn");
      const faqItemCount = document.getElementById("faqItemCount");
      const faqQuestionTarget = document.getElementById("faqQuestionTarget");
      const appDialogModalEl = document.getElementById("appDialogModal");
      const appDialogTitle = document.getElementById("appDialogTitle");
      const appDialogMessage = document.getElementById("appDialogMessage");
      const appDialogConfirmBtn = document.getElementById("appDialogConfirmBtn");
      const appDialogCancelBtn = document.getElementById("appDialogCancelBtn");
      const wizardPanels = Array.from(document.querySelectorAll("[data-wizard-panel]"));
      const wizardSteps = Array.from(document.querySelectorAll("[data-wizard-step]"));
      const wizardBackBtn = document.getElementById("wizardBackBtn");
      const wizardNextBtn = document.getElementById("wizardNextBtn");
      const wizardStepHint = document.getElementById("wizardStepHint");
      const wizardActions = document.querySelector(".announcement-wizard-actions");
      const wizardReviewDestinations = document.getElementById("wizardReviewDestinations");
      const wizardReviewAudience = document.getElementById("wizardReviewAudience");
      const wizardReviewTitle = document.getElementById("wizardReviewTitle");
      const wizardReviewMessage = document.getElementById("wizardReviewMessage");
      const wizardReviewSms = document.getElementById("wizardReviewSms");
      const wizardReviewEmail = document.getElementById("wizardReviewEmail");
      const wizardReviewSmsItem = document.getElementById("wizardReviewSmsItem");
      const wizardReviewEmailItem = document.getElementById("wizardReviewEmailItem");
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
      if (appDialogModalEl && appDialogModalEl.parentElement !== document.body) {
        document.body.appendChild(appDialogModalEl);
      }
      const appDialogModal = appDialogModalEl ? bootstrap.Modal.getOrCreateInstance(appDialogModalEl, {
        backdrop: "static",
        keyboard: false
      }) : null;
      let appDialogResolver = null;
      let appDialogResult = false;
      let currentWizardStep = 1;
      let furthestWizardStep = 1;
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

      function showAppDialog(options = {}) {
        if (!appDialogModal || !appDialogTitle || !appDialogMessage || !appDialogConfirmBtn || !appDialogCancelBtn) {
          if (options.cancelText) {
            return window.UniversalModal.confirm(options.message || "", { title: options.title || "Confirm Action", confirmLabel: options.confirmText || "Confirm" });
          }
          window.alert(options.message || "");
          return Promise.resolve(true);
        }

        appDialogTitle.textContent = options.title || "Notice";
        appDialogMessage.textContent = options.message || "";
        appDialogConfirmBtn.textContent = options.confirmText || "OK";
        appDialogConfirmBtn.className = "btn " + (options.confirmClass || "btn-primary text-white");
        appDialogCancelBtn.textContent = options.cancelText || "Cancel";
        appDialogCancelBtn.classList.toggle("d-none", !options.cancelText);
        appDialogResult = false;

        return new Promise(function (resolve) {
          appDialogResolver = resolve;
          appDialogModal.show();
        });
      }

      function showAppAlert(message, title = "Notice") {
        return showAppDialog({
          title,
          message,
          confirmText: "OK"
        });
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
                  showAppAlert('Image must be 50MB or less.');
                  continue;
                }
                try {
                  const imageUrl = await uploadEditorImage(file);
                  editorInstance.summernote('insertImage', imageUrl);
                } catch (err) {
                  showAppAlert(err.message || 'Unable to upload image.');
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
        if (smsPreview) {
          smsPreview.value = plain.slice(0, 320);
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
        if (!channelSms || !channelEmail) {
          return;
        }
        const showEmail = !!channelEmail.checked;
        if (emailComposeCard) emailComposeCard.hidden = !showEmail;
        if (deliveryEmailDetails) deliveryEmailDetails.hidden = !showEmail;
        if (emailField) {
          emailField.classList.toggle("is-collapsed", !showEmail);
          emailField.setAttribute("aria-hidden", showEmail ? "false" : "true");
        }
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
            if (field.type === "checkbox" || field.type === "radio") {
              field.checked = false;
            } else if (field.tagName === "SELECT") {
              field.selectedIndex = 0;
            } else {
              field.value = "";
            }
          }
        });
      }

      function updateWizardReview() {
        if (contentType === "faq") {
          return;
        }
        const destinationLabels = [];
        if (contentType === "page") {
          if (document.getElementById("channelGuestPage")?.checked) destinationLabels.push("Guest Page");
          if (document.getElementById("channelWebsite")?.checked) destinationLabels.push("Account Page");
        } else {
          if (channelSms?.checked) destinationLabels.push("SMS");
          if (channelEmail?.checked) destinationLabels.push("Email");
        }
        if (wizardReviewDestinations) {
          wizardReviewDestinations.textContent = destinationLabels.join(" and ") || "No destination selected";
        }

        if (wizardReviewAudience) {
          if (audienceCustom?.checked) {
            const selectedAreas = Array.from(document.querySelectorAll("input[name='area[]']:checked")).map((field) => field.value);
            const selectedRoles = Array.from(document.querySelectorAll("input[name='role_group[]']:checked")).map((field) => field.value);
            const audienceParts = [];
            if (selectedAreas.length) audienceParts.push(selectedAreas.join(", "));
            if (selectedRoles.length) audienceParts.push(selectedRoles.join(", "));
            wizardReviewAudience.textContent = audienceParts.join(" · ") || "Custom audience (no filters selected)";
          } else {
            wizardReviewAudience.textContent = "All Residents";
          }
        }

        if (wizardReviewTitle) {
          wizardReviewTitle.textContent = (sharedTitleInput?.value || "").trim() || "Untitled announcement";
        }
        if (wizardReviewMessage) {
          const message = getPlainTextFromHtml(getEditorCode(sharedEditorEl));
          wizardReviewMessage.textContent = message || "No message added";
        }
        if (wizardReviewSms && wizardReviewSmsItem) {
          wizardReviewSmsItem.hidden = !channelSms?.checked;
          wizardReviewSms.textContent = (smsPreview?.value || "").trim() || "Uses the message content";
        }
        if (wizardReviewEmail && wizardReviewEmailItem) {
          wizardReviewEmailItem.hidden = !channelEmail?.checked;
          wizardReviewEmail.textContent = (emailSubject?.value || "").trim() || "No subject added";
        }
      }

      function clearWizardError() {
        document.querySelectorAll("[data-wizard-error]").forEach((errorBox) => {
          errorBox.textContent = "";
          errorBox.classList.add("d-none");
        });
        sharedTitleInput?.classList.remove("is-invalid");
        sharedEditorEl.next(".note-editor").removeClass("is-invalid");
      }

      function showWizardError(message, field = null) {
        const activePanel = wizardPanels.find((panel) => !panel.hidden);
        const errorBox = activePanel?.querySelector("[data-wizard-error]");
        if (errorBox) {
          errorBox.textContent = message;
          errorBox.classList.remove("d-none");
        }
        if (field) {
          if (field.jquery) {
            field.addClass("is-invalid");
          } else {
            field.classList?.add("is-invalid");
          }
        }
      }

      async function validateWizardStep(step) {
        if (contentType === "faq") {
          return true;
        }
        clearWizardError();
        if (contentType === "page" && step === 1) {
          if (!placementPublic?.checked) {
            showWizardError("Select Announcements before continuing.");
            return false;
          }
          const hasDestination = !!(channelGuestPage?.checked || document.getElementById("channelWebsite")?.checked);
          if (!hasDestination) {
            showWizardError("Select Guest Page, Account Page, or both before continuing.");
            return false;
          }
        }
        const audienceStep = 2;
        if (step === audienceStep && audienceCustom?.checked) {
          const hasCustomFilter = !!document.querySelector("input[name='area[]']:checked, input[name='role_group[]']:checked");
          if (!hasCustomFilter) {
            showWizardError("Choose at least one area or role group for the custom audience.");
            return false;
          }
        }
        if (contentType === "delivery" && step === 1 && !channelSms?.checked && !channelEmail?.checked) {
          showWizardError("Select SMS, Email, or both before continuing.");
          return false;
        }
        if (step === 3) {
          const title = (sharedTitleInput?.value || "").trim();
          const body = getPlainTextFromHtml(getEditorCode(sharedEditorEl));
          if (!title) {
            showWizardError("Enter an announcement title before continuing.", sharedTitleInput);
            sharedTitleInput?.focus();
            return false;
          }
          if (!body) {
            const editorFrame = sharedEditorEl.next(".note-editor");
            showWizardError("Write the announcement message before continuing.", editorFrame);
            editorFrame.find(".note-editable").trigger("focus");
            return false;
          }
        }
        return true;
      }

      function renderWizardStep(shouldScroll = true) {
        if (contentType === "faq") {
          return;
        }
        wizardPanels.forEach((panel) => {
          const panelSteps = String(panel.dataset.wizardPanel || "").split(",").map(Number);
          const isCurrent = panelSteps.includes(currentWizardStep);
          panel.hidden = !isCurrent;
        });
        document.querySelectorAll("[data-wizard-stage]").forEach((section) => {
          section.hidden = Number(section.dataset.wizardStage) !== currentWizardStep;
        });
        document.querySelectorAll("[data-wizard-heading]").forEach((heading) => {
          heading.hidden = Number(heading.dataset.wizardHeading) !== currentWizardStep;
        });
        document.querySelector(".announcement-create-wizard")?.setAttribute("data-current-step", String(currentWizardStep));
        wizardSteps.forEach((stepButton) => {
          const stepNumber = Number(stepButton.dataset.wizardStep);
          const isCurrent = stepNumber === currentWizardStep;
          stepButton.classList.toggle("is-active", isCurrent);
          stepButton.classList.toggle("is-complete", stepNumber < currentWizardStep);
          stepButton.disabled = stepNumber > furthestWizardStep;
          if (isCurrent) {
            stepButton.setAttribute("aria-current", "step");
          } else {
            stepButton.removeAttribute("aria-current");
          }
        });
        if (wizardBackBtn) wizardBackBtn.hidden = currentWizardStep === 1;
        if (wizardActions) wizardActions.classList.toggle("is-final-step", currentWizardStep === 4);
        if (wizardStepHint) {
          const hints = {
            1: "Next: choose the audience",
            2: contentType === "page" ? "Next: write the announcement" : "Next: write the message content",
            3: "Next: review and publish"
          };
          wizardStepHint.textContent = hints[currentWizardStep] || "";
        }
        if (currentWizardStep === 4) updateWizardReview();
        if (shouldScroll) {
          document.querySelector(".announcement-create-wizard")?.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      }

      async function goToWizardStep(targetStep) {
        const nextStep = Math.max(1, Math.min(4, Number(targetStep) || 1));
        if (nextStep > currentWizardStep && !(await validateWizardStep(currentWizardStep))) {
          return;
        }
        currentWizardStep = nextStep;
        furthestWizardStep = Math.max(furthestWizardStep, currentWizardStep);
        clearWizardError();
        renderWizardStep();
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
              if (contentType !== "faq" && currentWizardStep === 3) {
                clearWizardError();
              }
            },
            onImageUpload: async function (files) {
              for (const file of files) {
                if (!file) continue;
                if (file.size > MAX_IMAGE_SIZE_BYTES) {
                  showAppAlert("Image must be 50MB or less.");
                  continue;
                }
                try {
                  const imageUrl = await uploadEditorImage(file);
                  editorInstance.summernote("insertImage", imageUrl);
                } catch (err) {
                  showAppAlert(err.message || "Unable to upload image.");
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

      if (contentType === "faq") {
        setFaqItemTargetCount(faqQuestionTarget ? Number(faqQuestionTarget.value || 1) : 1);
      }

      updateEditorOutputs();

      const createForm = document.querySelector("form.announcement-create-shell");
      if (createForm) {
        createForm.addEventListener("submit", async function (event) {
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
              await showAppAlert("Add at least one FAQ question and answer before saving.");
              return;
            }
            if (faqItems.length > faqMaxItems) {
              event.preventDefault();
              await showAppAlert("You can only save up to 20 FAQ questions in one content item.");
              return;
            }
            const hasIncompleteFaq = faqItems.some((item) => item.question === "" || item.answer === "");
            if (hasIncompleteFaq) {
              event.preventDefault();
              await showAppAlert("Complete both the question and answer for every FAQ entry before saving.");
              return;
            }
            return;
          }

          const dualPlacementActive = isDualPlacementSelected();
          const hasAnnouncementPlacement = !!(placementPublic && placementPublic.checked);
          const hasAnnouncementDestination = !!((channelGuestPage && channelGuestPage.checked) || (document.getElementById("channelWebsite") && document.getElementById("channelWebsite").checked));
          if (contentType === "page" && hasAnnouncementPlacement && !hasAnnouncementDestination) {
            event.preventDefault();
            await showAppAlert("Select Guest Page or Account Page when Announcements is selected.");
            return;
          }
          if (dualPlacementActive) {
            const publicNewsTitle = (publicNewsTitleInput.value || "").trim();
            const publicAnnouncementTitle = (publicAnnouncementTitleInput.value || "").trim();
            const publicNewsBody = getPlainTextFromHtml(getEditorCode(publicNewsEditorEl));
            const publicAnnouncementBody = getPlainTextFromHtml(getEditorCode(publicAnnouncementEditorEl));
            if (publicNewsTitle === "" || publicAnnouncementTitle === "" || publicNewsBody === "" || publicAnnouncementBody === "") {
              event.preventDefault();
              await showAppAlert("Fill in both the Main News and Announcements title and body before submitting.");
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
          if (postConfirmModalEl.parentElement !== document.body) {
            document.body.appendChild(postConfirmModalEl);
          }
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

      if (contentType !== "faq") {
        wizardNextBtn?.addEventListener("click", function () {
          goToWizardStep(currentWizardStep + 1);
        });
        wizardBackBtn?.addEventListener("click", function () {
          goToWizardStep(currentWizardStep - 1);
        });
        wizardSteps.forEach((stepButton) => {
          stepButton.addEventListener("click", function () {
            goToWizardStep(stepButton.dataset.wizardStep);
          });
        });
        document.querySelectorAll("[data-wizard-go]").forEach((button) => {
          button.addEventListener("click", function () {
            goToWizardStep(button.dataset.wizardGo);
          });
        });
        document.querySelectorAll("input[name='channels[]'], input[name='audience_scope'], input[name='area[]'], input[name='role_group[]']").forEach((field) => {
          field.addEventListener("change", updateWizardReview);
        });
        document.querySelectorAll(".announcement-create-wizard input").forEach((field) => {
          field.addEventListener("input", clearWizardError);
          field.addEventListener("change", clearWizardError);
        });
        sharedTitleInput?.addEventListener("input", updateWizardReview);
        emailSubject?.addEventListener("input", updateWizardReview);
        renderWizardStep(false);
        updateWizardReview();
      }

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
              showAppAlert('At least one FAQ item is required.');
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
  <script src="../../JS-Script-Files/Resident-End/dateFieldModal.js?v=20260707-date-proxy-white"></script>
</body>
</html>








