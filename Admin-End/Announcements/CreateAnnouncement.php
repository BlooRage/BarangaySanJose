<?php
require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/connection.php";

$deliveryChannel = strtolower(trim((string)($_GET['channel'] ?? 'all')));
if (!in_array($deliveryChannel, ['all', 'website', 'sms', 'email'], true)) {
  $deliveryChannel = 'all';
}
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
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css?v=20260307-24">
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

      <form class="announcement-create-shell p-3 p-md-4 shadow-sm" action="../../PhpFiles/Admin-End/announcementsCreation.php" method="post">
        <input type="hidden" name="channel_context" value="<?= htmlspecialchars($deliveryChannel) ?>">
        <div class="row g-4">
          <div class="col-12">
            <section class="announcement-section-card">
              <h5 class="announcement-section-title">Content</h5>
              <div class="mb-3">
                <label for="announcementTitle" class="form-label fw-semibold">Announcement Title</label>
                <input id="announcementTitle" name="title" type="text" class="form-control" placeholder="Enter announcement title" required>
              </div>

              <div class="mb-2">
                <label class="form-label fw-semibold">Announcement Body</label>
                <div id="announcementEditor"></div>
                <input type="hidden" id="announcementContent" name="content_html">
              </div>
              <small class="text-muted">Use bullets, numbering, and formatting for clearer notices.</small>
            </section>
          </div>

          <div class="col-12 col-lg-6">
            <section class="announcement-section-card h-100">
              <h5 class="announcement-section-title">Delivery Channels</h5>

              <div class="form-check">
                <input class="form-check-input channel-checkbox" type="checkbox" value="website" id="channelWebsite" name="channels[]" <?= $deliveryChannel === 'website' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                <label class="form-check-label" for="channelWebsite">Website</label>
              </div>
              <div class="form-check">
                <input class="form-check-input channel-checkbox" type="checkbox" value="sms" id="channelSms" name="channels[]" <?= $deliveryChannel === 'sms' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                <label class="form-check-label" for="channelSms">SMS</label>
              </div>
              <div class="form-check mb-3">
                <input class="form-check-input channel-checkbox" type="checkbox" value="email" id="channelEmail" name="channels[]" <?= $deliveryChannel === 'email' || $deliveryChannel === 'all' ? 'checked' : '' ?>>
                <label class="form-check-label" for="channelEmail">Email</label>
              </div>

              <div id="smsField" class="channel-field d-none">
                <label for="smsPreview" class="form-label mb-1">SMS Preview (plain text)</label>
                <textarea id="smsPreview" class="form-control" rows="3" readonly></textarea>
                <small id="smsCounter" class="text-muted">0 / 160 characters</small>
              </div>

              <div id="emailField" class="channel-field d-none mt-3">
                <label for="emailSubject" class="form-label mb-1">Email Subject</label>
                <input id="emailSubject" type="text" class="form-control" name="email_subject" placeholder="Enter email subject">
              </div>
            </section>
          </div>

          <div class="col-12 col-lg-6">
            <section class="announcement-section-card h-100">
              <h5 class="announcement-section-title">Audience</h5>
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
            </section>
          </div>

          <div class="col-12">
            <section class="announcement-section-card">
              <h5 class="announcement-section-title">Publish Settings</h5>
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label class="form-label mb-1">Schedule Date (optional)</label>
                  <input type="date" class="form-control" name="schedule_date">
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label mb-1">Schedule Time (optional)</label>
                  <input type="time" class="form-control" name="schedule_time">
                </div>
              </div>
            </section>
          </div>
        </div>

        <div class="announcement-sticky-actions d-flex flex-wrap justify-content-end gap-2 mt-4">
          <a href="Announcements.php<?= $deliveryChannel !== 'all' ? '?channel=' . urlencode($deliveryChannel) : '' ?>" class="btn btn-outline-secondary">Cancel</a>
          <button type="submit" name="submit_action" value="draft" class="btn btn-warning text-dark">Save Draft</button>
          <button type="submit" name="submit_action" value="pending" class="btn btn-primary text-white">Submit for Review</button>
        </div>
      </form>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="../../summernote-0.9.0-dist/summernote-lite.min.js?v=20260307-2"></script>
  <script>
    (function () {
      const MAX_IMAGE_SIZE_BYTES = 5 * 1024 * 1024;

      const contentInput = document.getElementById("announcementContent");
      const smsPreview = document.getElementById("smsPreview");
      const smsCounter = document.getElementById("smsCounter");
      const channelSms = document.getElementById("channelSms");
      const channelEmail = document.getElementById("channelEmail");
      const smsField = document.getElementById("smsField");
      const emailField = document.getElementById("emailField");
      const audienceAll = document.getElementById("audienceAll");
      const customAudienceFields = document.getElementById("customAudienceFields");
      const editorEl = $("#announcementEditor");
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

      function updateEditorOutputs() {
        const html = editorEl.summernote("code");
        const temp = document.createElement("div");
        temp.innerHTML = html;
        const plain = (temp.textContent || temp.innerText || "").trim();
        contentInput.value = html;
        smsPreview.value = plain;
        smsCounter.textContent = plain.length + " / 160 characters";
      }

      function toggleChannelFields() {
        smsField.classList.toggle("d-none", !channelSms.checked);
        emailField.classList.toggle("d-none", !channelEmail.checked);
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

      editorEl.summernote({
        placeholder: "Write your announcement here...",
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
                alert("Image must be 5MB or less.");
                continue;
              }
              try {
                const imageUrl = await uploadEditorImage(file);
                editorEl.summernote("insertImage", imageUrl);
              } catch (err) {
                alert(err.message || "Unable to upload image.");
              }
            }
            updateEditorOutputs();
          }
        }
      });
      const toolbarGroups = editorEl.next(".note-editor").find(".note-toolbar .note-btn-group").length;
      if (toolbarGroups <= 1) {
        editorEl.summernote("destroy");
        editorEl.summernote({
          placeholder: "Write your announcement here...",
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
                  alert("Image must be 5MB or less.");
                  continue;
                }
                try {
                  const imageUrl = await uploadEditorImage(file);
                  editorEl.summernote("insertImage", imageUrl);
                } catch (err) {
                  alert(err.message || "Unable to upload image.");
                }
              }
              updateEditorOutputs();
            }
          }
        });
      }
      applyToolbarTooltips();
      updateEditorOutputs();

      const createForm = document.querySelector("form.announcement-create-shell");
      if (createForm) {
        createForm.addEventListener("submit", function () {
          updateEditorOutputs();
        });
      }

      document.querySelectorAll(".channel-checkbox").forEach((el) => {
        el.addEventListener("change", toggleChannelFields);
      });
      toggleChannelFields();

      document.querySelectorAll("input[name='audience_scope']").forEach((el) => {
        el.addEventListener("change", toggleAudienceFields);
      });
      toggleAudienceFields();
    })();
  </script>
</body>
</html>
