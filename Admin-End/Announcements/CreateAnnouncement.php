<?php
require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/connection.php";

$deliveryChannel = strtolower(trim((string)($_GET['channel'] ?? 'all')));
if (!in_array($deliveryChannel, ['all', 'website', 'public', 'public_news', 'sms', 'email'], true)) {
  $deliveryChannel = 'all';
}
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isSuperAdmin = $sessionRole === 'superadmin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Announcement</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../../summernote-0.9.0-dist/summernote-lite.min.css?v=20260307-2" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css?v=20260311-34">
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Create Announcement</h2>
        <a href="Announcements.php<?= $deliveryChannel !== 'all' ? '?channel=' . urlencode($deliveryChannel) : '' ?>" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-arrow-left me-1"></i> Back to List
        </a>
      </div>
      <hr><br>

      <section class="announcement-create-guide mb-4">
        <div class="announcement-create-guide-copy">
          <div class="announcement-create-guide-kicker">Before You Start</div>
          <h5 class="announcement-section-title mb-1">Content Tips</h5>
          <p class="announcement-compose-subtitle mb-2">Write the main title and body that will be shown in your selected placements and delivery channels.</p>
          <p class="announcement-create-guide-text mb-0">Use a clear headline, a short opening summary, and bullet points when you need residents to follow instructions quickly.</p>
        </div>
        <div class="announcement-create-guide-grid">
          <div class="announcement-guide-block">
            <h6 class="announcement-guide-title">Editor Tools</h6>
            <ul class="announcement-guide-list">
              <li>Rich text formatting for clearer layout</li>
              <li>Image uploads up to 25 MB</li>
              <li>Ready for guest and account views</li>
            </ul>
          </div>
          <div class="announcement-guide-block">
            <h6 class="announcement-guide-title">Writing Tips</h6>
            <ul class="announcement-guide-list">
              <li>Start with a clear headline</li>
              <li>Add a short opening summary</li>
              <li>Use bullet points for instructions</li>
            </ul>
          </div>
        </div>
      </section>

      <form class="announcement-create-shell p-3 p-md-4 shadow-sm" action="../../PhpFiles/Admin-End/announcementsCreation.php" method="post">
        <input type="hidden" name="channel_context" value="<?= htmlspecialchars($deliveryChannel) ?>">
        <div class="row g-4">
          <div class="col-12">
            <section class="announcement-section-card">
              <h5 class="announcement-section-title">Distribution and Audience Setup</h5>
              <div class="announcement-config-grid">
                <div class="announcement-config-panel">
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
                  <div id="dualPlacementNotice" class="alert alert-light border mt-3 mb-0 d-none" role="status">
                    Create a separate News Section version and Announcements version below.
                  </div>
                </div>

                <div class="announcement-config-panel">
                  <h6 class="announcement-card-title">Audience and Publishing</h6>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="audience_scope" id="audienceAll" value="all" checked>
                    <label class="form-check-label" for="audienceAll">All Residents</label>
                  </div>
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="audience_scope" id="audienceCustom" value="custom">
                    <label class="form-check-label" for="audienceCustom">Custom Audience</label>
                  </div>

                  <div id="customAudienceFields" class="row g-2 d-none">
                    <div class="col-12">
                      <label class="form-label mb-1">Area</label>
                      <select class="form-select" name="area">
                        <option value="">Select Area</option>
                        <option>Area 1</option>
                        <option>Area 2</option>
                        <option>Area 3</option>
                        <option>Area 4</option>
                        <option>Area 5</option>
                        <option>Area 6</option>
                      </select>
                    </div>
                    <div class="col-12">
                      <label class="form-label mb-1">Role Group</label>
                      <select class="form-select" name="role_group">
                        <option value="">Select Group</option>
                        <option>Officials</option>
                        <option>Employees</option>
                        <option>Residents</option>
                      </select>
                    </div>
                  </div>

                  <div class="announcement-create-divider"></div>
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label mb-1">Schedule Date (optional)</label>
                      <input type="date" class="form-control" name="schedule_date">
                    </div>
                    <div class="col-12">
                      <label class="form-label mb-1">Schedule Time (optional)</label>
                      <input type="time" class="form-control" name="schedule_time">
                    </div>
                  </div>
                </div>

                <div class="announcement-config-panel">
                  <h6 class="announcement-card-title">Additional Delivery</h6>
                  <div id="announcementDestinationsGroup">
                    <div class="form-check mb-3">
                      <input class="form-check-input channel-checkbox" type="checkbox" value="public" id="channelGuestPage" name="channels[]" <?= $deliveryChannel === 'public' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                      <label class="form-check-label" for="channelGuestPage">Guest Page</label>
                    </div>

                    <div class="form-check mb-3">
                      <input class="form-check-input channel-checkbox" type="checkbox" value="website" id="channelWebsite" name="channels[]" <?= $deliveryChannel === 'website' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                      <label class="form-check-label" for="channelWebsite">Account Page</label>
                    </div>
                  </div>

                  <div class="announcement-channel-item">
                    <div class="form-check mb-0">
                      <input class="form-check-input channel-checkbox" type="checkbox" value="sms" id="channelSms" name="channels[]" <?= $deliveryChannel === 'sms' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                      <label class="form-check-label" for="channelSms">SMS</label>
                    </div>
                    <div id="smsField" class="channel-field channel-field-collapsible is-collapsed" aria-hidden="true">
                      <label for="smsPreview" class="form-label mb-1">SMS Preview (plain text)</label>
                      <textarea id="smsPreview" class="form-control" rows="3" readonly></textarea>
                      <small id="smsCounter" class="text-muted">0 / 160 characters</small>
                    </div>
                  </div>

                  <div class="announcement-channel-item">
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
            </section>
          </div>

          <div class="col-12">
            <div id="sharedContentFields">
              <section class="announcement-section-card">
                <div class="mb-3 announcement-primary-title-wrap">
                  <label for="announcementTitle" class="form-label fw-semibold">Title</label>
                  <input id="announcementTitle" name="title" type="text" class="form-control announcement-primary-title-input" placeholder="Enter announcement title" required>
                </div>

                <div class="announcement-create-divider"></div>

                <div class="announcement-editor-panel">
                  <div class="announcement-editor-panel-head">
                    <div>
                      <label class="form-label fw-semibold mb-1">Body</label>
                      <p class="announcement-editor-helper mb-0">Use headings, lists, and short paragraphs so the announcement stays readable in both news and announcement views.</p>
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
                            <label class="form-label fw-semibold mb-1">Body</label>
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
                            <label class="form-label fw-semibold mb-1">Body</label>
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
            <a href="Announcements.php<?= $deliveryChannel !== 'all' ? '?channel=' . urlencode($deliveryChannel) : '' ?>" class="btn btn-outline-secondary">Close</a>
          </div>
        </div>
      </form>

      <?php if ($isSuperAdmin): ?>
        <div class="modal fade" id="modalSuperAdminPostConfirm" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header border-0 pb-0 bg-white">
                <h5 class="modal-title w-100 text-center text-dark">Confirm Post Announcement</h5>
              </div>
              <hr class="my-0">
              <div class="modal-body text-center">
                <p class="mb-0">Are you sure this announcement is ready to post?</p>
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
      const sharedEditorEl = $("#announcementEditor");
      const publicNewsEditorEl = $("#publicNewsEditor");
      const publicAnnouncementEditorEl = $("#publicAnnouncementEditor");
      const fullToolbar = [
        ["style", ["style"]],
        ["font", ["bold", "italic", "underline", "clear"]],
        ["fontname", ["fontname"]],
        ["fontsize", ["fontsize"]],
        ["color", ["color"]],
        ["para", ["ul", "ol", "paragraph"]],
        ["table", ["table"]],
        ["insert", ["link", "picture", "video"]],
        ["view", ["fullscreen", "codeview", "help"]]
      ];

      function getPlainTextFromHtml(html) {
        const temp = document.createElement("div");
        temp.innerHTML = html;
        return (temp.textContent || temp.innerText || "").trim();
      }

      function isDualPlacementSelected() {
        return !!(placementPublicNews && placementPublicNews.checked && placementPublic && placementPublic.checked);
      }

      function updateEditorOutputs() {
        const sharedHtml = sharedEditorEl.summernote("code");
        const publicNewsHtml = publicNewsEditorEl.summernote("code");
        const publicAnnouncementHtml = publicAnnouncementEditorEl.summernote("code");
        const dualPlacementActive = isDualPlacementSelected();
        const sharedPlain = getPlainTextFromHtml(sharedHtml);
        const publicNewsPlain = getPlainTextFromHtml(publicNewsHtml);
        const publicAnnouncementPlain = getPlainTextFromHtml(publicAnnouncementHtml);
        const sidebarOnlyMode = !dualPlacementActive && placementPublic && placementPublic.checked && (!placementPublicNews || !placementPublicNews.checked);

        contentInput.value = dualPlacementActive ? publicNewsHtml : sharedHtml;
        publicNewsContentInput.value = publicNewsHtml;
        publicAnnouncementContentInput.value = publicAnnouncementHtml;

        if (dualPlacementActive) {
          sharedTitleInput.value = (publicNewsTitleInput.value || publicAnnouncementTitleInput.value || "").trim();
        }

        const previewSource = dualPlacementActive ? publicNewsHtml : sharedHtml;
        const plain = getPlainTextFromHtml(previewSource);
        smsPreview.value = plain;
        smsCounter.textContent = plain.length + " / 160 characters";

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
        const showSms = !!channelSms.checked;
        const showEmail = !!channelEmail.checked;
        smsField.classList.toggle("is-collapsed", !showSms);
        smsField.setAttribute("aria-hidden", showSms ? "false" : "true");
        emailField.classList.toggle("is-collapsed", !showEmail);
        emailField.setAttribute("aria-hidden", showEmail ? "false" : "true");
      }

      function togglePlacementGuidance() {
        const hasNewsPlacement = placementPublicNews && placementPublicNews.checked;
        const hasAnnouncementPlacement = placementPublic && placementPublic.checked;
        const dualPlacementActive = hasNewsPlacement && hasAnnouncementPlacement;
        if (dualPlacementNotice) {
          dualPlacementNotice.classList.toggle("d-none", !dualPlacementActive);
          dualPlacementNotice.textContent = dualPlacementActive
            ? "Create a separate main news version and sidebar announcement version below."
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

      function toggleAudienceFields() {
        customAudienceFields.classList.toggle("d-none", audienceAll.checked);
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
        editorInstance.summernote(buildEditorConfig(placeholder, editorInstance));
        const toolbarGroups = editorInstance.next(".note-editor").find(".note-toolbar .note-btn-group").length;
        if (toolbarGroups <= 1) {
          editorInstance.summernote("destroy");
          editorInstance.summernote(buildEditorConfig(placeholder, editorInstance));
        }
      }

      initEditor(sharedEditorEl, "Write your announcement here...");
      initEditor(publicNewsEditorEl, "Write the main news content here...");
      initEditor(publicAnnouncementEditorEl, "Write the sidebar announcement content here...");
      applyToolbarTooltips();
      updateEditorOutputs();

      const createForm = document.querySelector("form.announcement-create-shell");
      if (createForm) {
        createForm.addEventListener("submit", function (event) {
          const dualPlacementActive = isDualPlacementSelected();
          const hasAnnouncementPlacement = !!(placementPublic && placementPublic.checked);
          const hasAnnouncementDestination = !!((channelGuestPage && channelGuestPage.checked) || (document.getElementById("channelWebsite") && document.getElementById("channelWebsite").checked));
          if (hasAnnouncementPlacement && !hasAnnouncementDestination) {
            event.preventDefault();
            alert("Select Guest Page or Account Page when Announcements is selected.");
            return;
          }
          if (dualPlacementActive) {
            const publicNewsTitle = (publicNewsTitleInput.value || "").trim();
            const publicAnnouncementTitle = (publicAnnouncementTitleInput.value || "").trim();
            const publicNewsBody = getPlainTextFromHtml(publicNewsEditorEl.summernote("code"));
            const publicAnnouncementBody = getPlainTextFromHtml(publicAnnouncementEditorEl.summernote("code"));
            if (publicNewsTitle === "" || publicAnnouncementTitle === "" || publicNewsBody === "" || publicAnnouncementBody === "") {
              event.preventDefault();
              alert("Fill in both the Main News and Sidebar Announcement title and body before submitting.");
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
    })();
  </script>
</body>
</html>
