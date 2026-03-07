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
  <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css?v=20260307-6">
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
                <div id="editorToolbar" class="ql-toolbar ql-snow">
                  <span class="ql-formats">
                    <select class="ql-font">
                      <option selected></option>
                      <option value="arial"></option>
                      <option value="times-new-roman"></option>
                      <option value="georgia"></option>
                      <option value="trebuchet-ms"></option>
                      <option value="tahoma"></option>
                      <option value="verdana"></option>
                      <option value="courier-new"></option>
                      <option value="lucida-sans"></option>
                      <option value="impact"></option>
                    </select>
                    <select class="ql-size">
                      <option value="12px">12</option>
                      <option value="14px" selected>14</option>
                      <option value="16px">16</option>
                      <option value="18px">18</option>
                      <option value="20px">20</option>
                      <option value="24px">24</option>
                      <option value="28px">28</option>
                      <option value="32px">32</option>
                    </select>
                  </span>
                  <span class="ql-formats">
                    <select class="ql-header">
                      <option value="1"></option>
                      <option value="2"></option>
                      <option selected></option>
                    </select>
                  </span>
                  <span class="ql-formats">
                    <button class="ql-bold"></button>
                    <button class="ql-italic"></button>
                    <button class="ql-underline"></button>
                  </span>
                  <span class="ql-formats">
                    <button class="ql-list" value="ordered"></button>
                    <button class="ql-list" value="bullet"></button>
                    <button class="ql-align" value=""></button>
                    <button class="ql-align" value="center"></button>
                    <button class="ql-align" value="right"></button>
                    <button class="ql-align" value="justify"></button>
                  </span>
                  <span class="ql-formats">
                    <select class="ql-color"></select>
                    <select class="ql-background"></select>
                  </span>
                  <span class="ql-formats">
                    <button class="ql-link"></button>
                    <button class="ql-image"></button>
                    <button class="ql-clean"></button>
                  </span>
                </div>
                <div id="announcementEditor" class="announcement-editor"></div>
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
  <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
  <script>
    (function () {
      const Font = Quill.import("formats/font");
      Font.whitelist = [
        "arial",
        "times-new-roman",
        "georgia",
        "trebuchet-ms",
        "tahoma",
        "verdana",
        "courier-new",
        "lucida-sans",
        "impact"
      ];
      Quill.register(Font, true);
      const Size = Quill.import("attributors/style/size");
      Size.whitelist = ["12px", "14px", "16px", "18px", "20px", "24px", "28px", "32px"];
      Quill.register(Size, true);

      const quill = new Quill("#announcementEditor", {
        modules: { toolbar: "#editorToolbar" },
        theme: "snow",
        placeholder: "Write your announcement here..."
      });
      const MAX_IMAGE_SIZE_BYTES = 5 * 1024 * 1024;
      const toolbar = quill.getModule("toolbar");

      const contentInput = document.getElementById("announcementContent");
      const smsPreview = document.getElementById("smsPreview");
      const smsCounter = document.getElementById("smsCounter");
      const channelSms = document.getElementById("channelSms");
      const channelEmail = document.getElementById("channelEmail");
      const smsField = document.getElementById("smsField");
      const emailField = document.getElementById("emailField");
      const audienceAll = document.getElementById("audienceAll");
      const customAudienceFields = document.getElementById("customAudienceFields");

      function updateEditorOutputs() {
        const html = quill.root.innerHTML;
        const plain = (quill.getText() || "").trim();
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
          [".ql-font", "Font Style"],
          [".ql-size", "Font Size"],
          [".ql-header", "Heading Level"],
          [".ql-bold", "Bold"],
          [".ql-italic", "Italic"],
          [".ql-underline", "Underline"],
          [".ql-list[value='ordered']", "Numbered List"],
          [".ql-list[value='bullet']", "Bullet List"],
          [".ql-align[value='']", "Align Left"],
          [".ql-align[value='center']", "Align Center"],
          [".ql-align[value='right']", "Align Right"],
          [".ql-align[value='justify']", "Justify"],
          [".ql-color", "Text Color"],
          [".ql-background", "Highlight Color"],
          [".ql-link", "Insert Link"],
          [".ql-image", "Insert Image"],
          [".ql-clean", "Clear Formatting"]
        ];

        tooltips.forEach(([selector, label]) => {
          document.querySelectorAll("#editorToolbar " + selector).forEach((el) => {
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
        if (!response.ok || !payload.success || !payload.url) {
          throw new Error(payload.message || "Image upload failed.");
        }

        return payload.url;
      }

      function handleImageInsert() {
        const input = document.createElement("input");
        input.setAttribute("type", "file");
        input.setAttribute("accept", "image/png,image/jpeg,image/jpg,image/webp,image/gif");
        input.click();

        input.onchange = async () => {
          const file = input.files && input.files[0];
          if (!file) return;
          if (file.size > MAX_IMAGE_SIZE_BYTES) {
            alert("Image must be 5MB or less.");
            return;
          }

          try {
            const imageUrl = await uploadEditorImage(file);
            const range = quill.getSelection(true) || { index: quill.getLength(), length: 0 };
            quill.insertEmbed(range.index, "image", imageUrl, "user");
            quill.setSelection(range.index + 1, 0, "silent");
            updateEditorOutputs();
          } catch (err) {
            alert(err.message || "Unable to upload image.");
          }
        };
      }

      quill.on("text-change", updateEditorOutputs);
      if (toolbar) {
        toolbar.addHandler("image", handleImageInsert);
      }
      applyToolbarTooltips();
      updateEditorOutputs();

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
