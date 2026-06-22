<?php
require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/connection.php";

$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isSuperAdmin = $sessionRole === 'superadmin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="../../Images/favicon_sanjose.png?v=20260211">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create News</title>

  <script src="https://kit.fontawesome.com/3482e00999.js" crossorigin="anonymous"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../../summernote-0.9.0-dist/summernote-lite.min.css?v=20260307-2" rel="stylesheet">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/AdminDashboardStyle.css">
  <link rel="stylesheet" href="../../CSS-Styles/Admin-End-CSS/ContentManagementStyle.css?v=20260323-38">
  <style>
    .news-compose-layout {
      align-items: start;
    }

    .news-create-card,
    .news-preview-card {
      border-radius: 24px;
    }

    .news-create-subtitle {
      max-width: 72ch;
      color: #667085;
      font-size: 0.98rem;
      line-height: 1.75;
    }

    .news-upload-shell,
    .news-builder-section,
    .news-preview-panel {
      border: 1px solid rgba(222, 113, 12, 0.14);
      border-radius: 20px;
      background: linear-gradient(180deg, #ffffff 0%, #fffaf5 100%);
      box-shadow: 0 16px 40px rgba(18, 18, 18, 0.05);
    }

    .news-upload-shell,
    .news-builder-section {
      padding: 1.1rem;
    }

    .news-upload-preview,
    .news-section-image-preview {
      display: grid;
      place-items: center;
      min-height: 220px;
      overflow: hidden;
      border-radius: 18px;
      background:
        radial-gradient(circle at 20% 20%, rgba(222, 113, 12, 0.16), transparent 24%),
        linear-gradient(135deg, #efe5d9 0%, #faf6f0 100%);
      color: #7c746c;
      text-align: center;
      font-size: 0.95rem;
    }

    .news-upload-preview img,
    .news-section-image-preview img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: cover;
    }

    .news-upload-status {
      color: #7c746c;
      font-size: 0.9rem;
    }

    .news-section-stack {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .news-section-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
    }

    .news-builder-section-head {
      display: flex;
      justify-content: space-between;
      gap: 1rem;
      align-items: center;
      margin-bottom: 0.9rem;
    }

    .news-builder-section-kicker {
      margin: 0;
      color: #de710c;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .news-preview-column {
      position: sticky;
      top: 1.5rem;
    }

    .news-preview-panel {
      padding: 1.2rem;
    }

    .news-preview-meta {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 1rem;
    }

    .news-preview-kicker {
      margin: 0;
      color: #de710c;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    .news-preview-sync {
      color: #667085;
      font-size: 0.82rem;
      font-weight: 600;
    }

    .news-tile-preview {
      overflow: hidden;
      margin-bottom: 1.1rem;
      border: 1px solid rgba(0, 0, 0, 0.06);
      border-radius: 22px;
      background: #ffffff;
    }

    .news-tile-media {
      display: grid;
      place-items: center;
      min-height: 180px;
      background:
        radial-gradient(circle at 20% 20%, rgba(222, 113, 12, 0.18), transparent 25%),
        linear-gradient(135deg, #ece3d7 0%, #faf5ee 100%);
      color: #7c746c;
      text-align: center;
    }

    .news-tile-media img {
      width: 100%;
      height: 180px;
      display: block;
      object-fit: cover;
    }

    .news-tile-body {
      padding: 1rem 1rem 1.1rem;
    }

    .news-preview-date {
      display: inline-flex;
      align-items: center;
      min-height: 32px;
      padding: 5px 10px;
      border-radius: 10px;
      background: #f3f4f6;
      color: #4b5563;
      font-size: 0.82rem;
      font-weight: 600;
    }

    .news-tile-title {
      margin: 0.85rem 0 0;
      color: #111827;
      font-size: 1.08rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .news-article-preview {
      overflow: hidden;
      border: 1px solid rgba(0, 0, 0, 0.06);
      border-radius: 24px;
      background: #ffffff;
    }

    .news-article-copy {
      padding: 1.35rem;
    }

    .news-article-tag {
      margin: 0 0 0.85rem;
      color: #111827;
      font-size: 0.96rem;
      font-weight: 600;
    }

    .news-article-title {
      margin: 0 0 1rem;
      color: #101828;
      font-size: clamp(2rem, 3vw, 3rem);
      font-weight: 700;
      line-height: 1.02;
      letter-spacing: -0.04em;
    }

    .news-article-hero {
      overflow: hidden;
      margin: 1.35rem 0 1.4rem;
      border-radius: 22px;
      background:
        radial-gradient(circle at 20% 20%, rgba(222, 113, 12, 0.18), transparent 25%),
        linear-gradient(135deg, #ece3d7 0%, #faf5ee 100%);
    }

    .news-article-hero img {
      width: 100%;
      max-height: 460px;
      display: block;
      object-fit: cover;
    }

    .news-article-body {
      color: #475467;
      line-height: 1.82;
      word-break: break-word;
    }

    .news-article-body > :first-child {
      margin-top: 0 !important;
    }

    .news-article-body > :last-child {
      margin-bottom: 0 !important;
    }

    .news-article-body img {
      width: 100%;
      height: auto;
      display: block;
      margin: 1.2rem 0;
      border-radius: 18px;
    }

    .news-article-body figure {
      margin: 1.3rem 0;
    }

    .news-article-body figcaption {
      margin-top: 0.6rem;
      color: #667085;
      font-size: 0.9rem;
      text-align: center;
    }

    .news-article-body h1,
    .news-article-body h2,
    .news-article-body h3,
    .news-article-body h4,
    .news-article-body h5,
    .news-article-body h6 {
      margin-top: 1.3rem;
      margin-bottom: 0.6rem;
      color: #111827;
      line-height: 1.18;
    }

    .news-article-body p,
    .news-article-body li {
      color: #475467;
      line-height: 1.82;
    }

    .news-placeholder-copy {
      color: #667085;
      font-size: 0.95rem;
      line-height: 1.7;
    }

    @media (max-width: 1199px) {
      .news-preview-column {
        position: static;
      }
    }

    @media (max-width: 767px) {
      .news-upload-preview,
      .news-section-image-preview {
        min-height: 180px;
      }

      .news-article-copy {
        padding: 1rem;
      }

      .news-article-title {
        font-size: clamp(1.7rem, 8vw, 2.4rem);
      }
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
          <h2 class="mb-1" style="font-family: 'Charis SIL Bold'; color: #DE710C;">Create News</h2>
          <p class="text-muted mb-0">Publish a full news article with its own headline image, body, extra sections, and a public-page preview.</p>
        </div>
      </div>
      <hr><br>

      <section class="announcement-create-guide mb-4">
        <div class="announcement-create-guide-copy">
          <div class="announcement-create-guide-kicker">News Workflow</div>
          <h5 class="announcement-section-title mb-1">Build The Story First</h5>
          <p class="announcement-compose-subtitle mb-2">This news form is separate from page announcements so articles can have a cleaner image-led layout.</p>
          <p class="announcement-create-guide-text mb-0">Start with the headline image and main story, then add extra text or image sections. Use the preview to see how the article and tile will appear once posted.</p>
        </div>
        <div class="announcement-create-guide-grid">
          <div class="announcement-guide-block">
            <h6 class="announcement-guide-title">Suggested Flow</h6>
            <ul class="announcement-guide-list">
              <li>Upload the headline image first</li>
              <li>Add the news heading and main body</li>
              <li>Insert extra text or image sections only when needed</li>
            </ul>
          </div>
          <div class="announcement-guide-block">
            <h6 class="announcement-guide-title">Before Posting</h6>
            <ul class="announcement-guide-list">
              <li>Check the tile preview and full article preview</li>
              <li>Use clear section breaks and short paragraphs</li>
              <li>Schedule the publish date only if the story should go live later</li>
            </ul>
          </div>
        </div>
      </section>

      <form class="announcement-create-shell p-3 p-md-4 shadow-sm" id="newsCreateForm" action="../../PhpFiles/Admin-End/announcementsCreation.php" method="post">
        <input type="hidden" name="channel_context" value="public_news">
        <input type="hidden" name="content_type" value="news">
        <input type="hidden" name="channels[]" value="public_news">
        <input type="hidden" name="headline_image_url" id="headlineImageUrlInput" value="">
        <input type="hidden" name="news_body_html" id="newsBodyHtmlInput" value="">
        <input type="hidden" name="news_sections_json" id="newsSectionsJsonInput" value="">
        <input type="hidden" name="content_html" id="newsComposedHtmlInput" value="">

        <div class="row g-4 news-compose-layout">
          <div class="col-xl-7">
            <section class="announcement-section-card news-create-card mb-4">
              <h5 class="announcement-section-title">Headline Image</h5>
              <p class="news-create-subtitle mb-3">This becomes the lead image for the article and the tile preview on the public news page.</p>
              <div class="news-upload-shell">
                <div class="row g-3 align-items-start">
                  <div class="col-lg-5">
                    <label for="headlineImageFile" class="form-label fw-semibold">Upload Headline Image</label>
                    <input type="file" class="form-control" id="headlineImageFile" accept="image/jpeg,image/png,image/webp,image/gif">
                    <div class="form-text">Accepted: JPG, PNG, WEBP, GIF. Maximum 50MB.</div>
                    <div class="news-upload-status mt-3" id="headlineImageStatus">No headline image uploaded yet.</div>
                  </div>
                  <div class="col-lg-7">
                    <div class="news-upload-preview" id="headlineImagePreview">
                      <span>Headline image preview will appear here.</span>
                    </div>
                  </div>
                </div>
              </div>
            </section>

            <section class="announcement-section-card news-create-card mb-4">
              <h5 class="announcement-section-title">Story Details</h5>
              <p class="news-create-subtitle mb-3">Write the main story first. This becomes the opening section of the article.</p>
              <div class="mb-3">
                <label for="newsHeadingInput" class="form-label fw-semibold">Heading</label>
                <input type="text" class="form-control announcement-primary-title-input" id="newsHeadingInput" name="title" placeholder="Enter the news headline" required>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label for="newsScheduleDateInput" class="form-label fw-semibold">Schedule Date (optional)</label>
                  <input type="date" class="form-control" id="newsScheduleDateInput" name="schedule_date">
                </div>
                <div class="col-md-6">
                  <label for="newsScheduleTimeInput" class="form-label fw-semibold">Schedule Time (optional)</label>
                  <input type="time" class="form-control" id="newsScheduleTimeInput" name="schedule_time">
                </div>
              </div>
              <div class="announcement-editor-panel">
                <div class="announcement-editor-panel-head">
                  <div>
                    <label class="form-label fw-semibold mb-1">Main Body</label>
                    <p class="announcement-editor-helper mb-0">Use the main body for the opening article text. You can still format, link, and insert inline images if needed.</p>
                  </div>
                </div>
                <div id="newsBodyEditor"></div>
              </div>
            </section>

            <section class="announcement-section-card news-create-card">
              <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                  <h5 class="announcement-section-title mb-1">Additional Sections</h5>
                  <p class="news-create-subtitle mb-0">Add extra text blocks or supporting images after the main story.</p>
                </div>
                <div class="news-section-toolbar">
                  <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddTextSection">
                    <i class="fas fa-align-left"></i>&nbsp;Add Text Section
                  </button>
                  <button type="button" class="btn btn-outline-warning btn-sm" id="btnAddImageSection">
                    <i class="fas fa-image"></i>&nbsp;Add Image Section
                  </button>
                </div>
              </div>
              <div class="news-section-stack" id="newsSectionsContainer"></div>
              <div class="news-placeholder-copy mt-3" id="newsSectionsEmptyState">No additional sections yet. Add one only if the story needs more text or a supporting image.</div>
            </section>
          </div>

          <div class="col-xl-5">
            <aside class="news-preview-column">
              <section class="announcement-section-card news-preview-card">
                <div class="news-preview-meta">
                  <div>
                    <p class="news-preview-kicker">Live Preview</p>
                    <h5 class="announcement-section-title mb-1">Posted Page View</h5>
                  </div>
                  <div class="news-preview-sync" id="newsPreviewSyncLabel">Updates automatically</div>
                </div>

                <div class="news-preview-panel mb-3">
                  <h6 class="announcement-card-title mb-3">News Tile Preview</h6>
                  <div class="news-tile-preview">
                    <div class="news-tile-media" id="newsTilePreviewMedia"></div>
                    <div class="news-tile-body">
                      <span class="news-preview-date" id="newsTilePreviewDate">Preview only</span>
                      <h4 class="news-tile-title" id="newsTilePreviewTitle">Your news headline will appear here.</h4>
                    </div>
                  </div>
                </div>

                <div class="news-preview-panel">
                  <h6 class="announcement-card-title mb-3">Article Preview</h6>
                  <article class="news-article-preview">
                    <div class="news-article-copy">
                      <p class="news-article-tag">Community Update</p>
                      <h2 class="news-article-title" id="newsPreviewHeadline">Your news headline will appear here.</h2>
                      <span class="news-preview-date" id="newsPreviewDate">Preview only</span>
                      <div class="news-article-hero" id="newsPreviewHero">
                        <div class="news-upload-preview" style="min-height: 240px;">
                          <span>Upload a headline image to preview the article hero.</span>
                        </div>
                      </div>
                      <div class="news-article-body" id="newsPreviewBody">
                        <p class="news-placeholder-copy">Write the story body to preview the article layout.</p>
                      </div>
                    </div>
                  </article>
                </div>
              </section>
            </aside>
          </div>
        </div>

        <div class="announcement-sticky-actions mt-4">
          <div class="announcement-modal-footer-start">
            <button type="button" class="btn btn-outline-secondary" id="btnScrollPreview">Preview Story</button>
            <button type="submit" name="submit_action" value="draft" class="btn btn-warning text-dark" data-news-submit>Save as Draft</button>
            <?php if ($isSuperAdmin): ?>
              <button type="submit" id="btnPostNews" name="submit_action" value="approved" class="btn btn-primary text-white" data-news-submit>Post News</button>
            <?php else: ?>
              <button type="submit" name="submit_action" value="pending" class="btn btn-primary text-white" data-news-submit>Submit for Review</button>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="../../summernote-0.9.0-dist/summernote-lite.min.js?v=20260307-2"></script>
  <script>
    (function () {
      const MAX_IMAGE_SIZE_BYTES = 50 * 1024 * 1024;
      const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;

      const formEl = document.getElementById("newsCreateForm");
      const headingInput = document.getElementById("newsHeadingInput");
      const scheduleDateInput = document.getElementById("newsScheduleDateInput");
      const scheduleTimeInput = document.getElementById("newsScheduleTimeInput");
      const headlineImageFileInput = document.getElementById("headlineImageFile");
      const headlineImageUrlInput = document.getElementById("headlineImageUrlInput");
      const headlineImageStatus = document.getElementById("headlineImageStatus");
      const headlineImagePreview = document.getElementById("headlineImagePreview");
      const newsBodyHtmlInput = document.getElementById("newsBodyHtmlInput");
      const newsSectionsJsonInput = document.getElementById("newsSectionsJsonInput");
      const newsComposedHtmlInput = document.getElementById("newsComposedHtmlInput");
      const sectionsContainer = document.getElementById("newsSectionsContainer");
      const sectionsEmptyState = document.getElementById("newsSectionsEmptyState");
      const tilePreviewMedia = document.getElementById("newsTilePreviewMedia");
      const tilePreviewDate = document.getElementById("newsTilePreviewDate");
      const tilePreviewTitle = document.getElementById("newsTilePreviewTitle");
      const previewHeadline = document.getElementById("newsPreviewHeadline");
      const previewDate = document.getElementById("newsPreviewDate");
      const previewHero = document.getElementById("newsPreviewHero");
      const previewBody = document.getElementById("newsPreviewBody");
      const previewSyncLabel = document.getElementById("newsPreviewSyncLabel");
      const bodyEditorEl = $("#newsBodyEditor");
      const submitButtons = Array.from(document.querySelectorAll("[data-news-submit]"));

      let sectionCounter = 0;
      let activeUploads = 0;

      function escapeHtml(value) {
        return String(value || "")
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#39;");
      }

      function stripHtml(html) {
        const temp = document.createElement("div");
        temp.innerHTML = String(html || "");
        return (temp.textContent || temp.innerText || "").replace(/\s+/g, " ").trim();
      }

      function getBodyHtml() {
        return bodyEditorEl.length ? bodyEditorEl.summernote("code") : "";
      }

      function setUploading(isUploading) {
        activeUploads += isUploading ? 1 : -1;
        if (activeUploads < 0) {
          activeUploads = 0;
        }
        submitButtons.forEach((btn) => {
          btn.disabled = activeUploads > 0;
        });
        if (previewSyncLabel) {
          previewSyncLabel.textContent = activeUploads > 0 ? "Uploading image..." : "Updates automatically";
        }
      }

      async function uploadImageFile(file) {
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

      function buildEditorConfig(editorInstance, placeholderText) {
        return {
          placeholder: placeholderText,
          height: 220,
          minHeight: 180,
          dialogsInBody: true,
          toolbar: [
            ["style", ["style"]],
            ["font", ["bold", "italic", "underline", "clear"]],
            ["para", ["ul", "ol", "paragraph"]],
            ["insert", ["link", "picture"]],
            ["view", ["codeview", "help"]]
          ],
          callbacks: {
            onChange: function () {
              renderPreview();
            },
            onImageUpload: async function (files) {
              for (const file of files) {
                if (!file) {
                  continue;
                }
                if (file.size > MAX_IMAGE_SIZE_BYTES) {
                  window.alert("Image must be 50MB or less.");
                  continue;
                }
                try {
                  setUploading(true);
                  const imageUrl = await uploadImageFile(file);
                  editorInstance.summernote("insertImage", imageUrl);
                } catch (error) {
                  window.alert(error.message || "Unable to upload image.");
                } finally {
                  setUploading(false);
                }
              }
              renderPreview();
            }
          }
        };
      }

      function initEditor(editorInstance, placeholderText) {
        if (!editorInstance.length) {
          return;
        }
        editorInstance.summernote(buildEditorConfig(editorInstance, placeholderText));
      }

      function formatPreviewDate() {
        const scheduleDate = String(scheduleDateInput?.value || "").trim();
        const scheduleTime = String(scheduleTimeInput?.value || "").trim();
        if (scheduleDate !== "") {
          const value = scheduleTime !== "" ? `${scheduleDate}T${scheduleTime}` : `${scheduleDate}T00:00`;
          const parsed = new Date(value);
          if (!Number.isNaN(parsed.getTime())) {
            return parsed.toLocaleDateString(undefined, {
              month: "long",
              day: "numeric",
              year: "numeric"
            });
          }
        }
        return "Preview only";
      }

      function setMediaPreview(container, imageUrl, placeholderText) {
        if (!container) {
          return;
        }
        if (imageUrl) {
          container.innerHTML = `<img src="${escapeHtml(imageUrl)}" alt="Uploaded preview image">`;
          return;
        }
        container.innerHTML = `<span>${escapeHtml(placeholderText)}</span>`;
      }

      function buildTextSectionMarkup(sectionId) {
        return `
          <article class="news-builder-section" data-section-id="${sectionId}" data-section-type="text">
            <div class="news-builder-section-head">
              <div>
                <p class="news-builder-section-kicker">Text Section</p>
                <h6 class="announcement-card-title mb-0">Additional Story Block</h6>
              </div>
              <button type="button" class="btn btn-outline-danger btn-sm" data-remove-section>Remove</button>
            </div>
            <div data-section-editor></div>
          </article>
        `;
      }

      function buildImageSectionMarkup(sectionId) {
        return `
          <article class="news-builder-section" data-section-id="${sectionId}" data-section-type="image">
            <div class="news-builder-section-head">
              <div>
                <p class="news-builder-section-kicker">Image Section</p>
                <h6 class="announcement-card-title mb-0">Supporting Image</h6>
              </div>
              <button type="button" class="btn btn-outline-danger btn-sm" data-remove-section>Remove</button>
            </div>
            <div class="row g-3 align-items-start">
              <div class="col-lg-5">
                <label class="form-label fw-semibold">Upload Image</label>
                <input type="file" class="form-control" data-section-image-file accept="image/jpeg,image/png,image/webp,image/gif">
                <input type="hidden" data-section-image-url value="">
                <label class="form-label fw-semibold mt-3">Caption (optional)</label>
                <input type="text" class="form-control" data-section-image-caption placeholder="Add a short caption">
                <div class="news-upload-status mt-3" data-section-image-status>No image uploaded for this section yet.</div>
              </div>
              <div class="col-lg-7">
                <div class="news-section-image-preview" data-section-image-preview>
                  <span>Supporting image preview will appear here.</span>
                </div>
              </div>
            </div>
          </article>
        `;
      }

      function updateSectionsEmptyState() {
        if (!sectionsEmptyState || !sectionsContainer) {
          return;
        }
        sectionsEmptyState.classList.toggle("d-none", sectionsContainer.children.length > 0);
      }

      function attachSectionEvents(sectionEl) {
        const removeBtn = sectionEl.querySelector("[data-remove-section]");
        if (removeBtn) {
          removeBtn.addEventListener("click", function () {
            const editorEl = sectionEl.querySelector("[data-section-editor]");
            if (editorEl && editorEl.dataset.initialized === "true") {
              $(editorEl).summernote("destroy");
            }
            sectionEl.remove();
            updateSectionsEmptyState();
            renderPreview();
          });
        }

        if (sectionEl.dataset.sectionType === "text") {
          const editorEl = sectionEl.querySelector("[data-section-editor]");
          if (editorEl) {
            const editorInstance = $(editorEl);
            initEditor(editorInstance, "Write an additional text section...");
            editorEl.dataset.initialized = "true";
          }
          return;
        }

        const imageFileInput = sectionEl.querySelector("[data-section-image-file]");
        const imageUrlInput = sectionEl.querySelector("[data-section-image-url]");
        const imageCaptionInput = sectionEl.querySelector("[data-section-image-caption]");
        const imageStatusEl = sectionEl.querySelector("[data-section-image-status]");
        const imagePreviewEl = sectionEl.querySelector("[data-section-image-preview]");

        if (imageFileInput && imageUrlInput && imageStatusEl && imagePreviewEl) {
          imageFileInput.addEventListener("change", async function () {
            const file = imageFileInput.files && imageFileInput.files[0] ? imageFileInput.files[0] : null;
            if (!file) {
              return;
            }
            if (file.size > MAX_IMAGE_SIZE_BYTES) {
              window.alert("Image must be 50MB or less.");
              imageFileInput.value = "";
              return;
            }

            imageStatusEl.textContent = "Uploading supporting image...";
            try {
              setUploading(true);
              const imageUrl = await uploadImageFile(file);
              imageUrlInput.value = imageUrl;
              imageStatusEl.textContent = "Supporting image uploaded.";
              setMediaPreview(imagePreviewEl, imageUrl, "Supporting image preview will appear here.");
              renderPreview();
            } catch (error) {
              imageUrlInput.value = "";
              imageStatusEl.textContent = "Unable to upload supporting image.";
              setMediaPreview(imagePreviewEl, "", "Supporting image preview will appear here.");
              window.alert(error.message || "Unable to upload image.");
            } finally {
              setUploading(false);
              imageFileInput.value = "";
            }
          });
        }

        if (imageCaptionInput) {
          imageCaptionInput.addEventListener("input", renderPreview);
        }
      }

      function addSection(type) {
        if (!sectionsContainer) {
          return;
        }
        sectionCounter += 1;
        const markup = type === "image"
          ? buildImageSectionMarkup(sectionCounter)
          : buildTextSectionMarkup(sectionCounter);
        sectionsContainer.insertAdjacentHTML("beforeend", markup);
        const sectionEl = sectionsContainer.lastElementChild;
        if (sectionEl) {
          attachSectionEvents(sectionEl);
        }
        updateSectionsEmptyState();
        renderPreview();
      }

      function collectSections() {
        if (!sectionsContainer) {
          return [];
        }

        return Array.from(sectionsContainer.querySelectorAll("[data-section-id]")).map(function (sectionEl) {
          const type = sectionEl.getAttribute("data-section-type") || "";
          if (type === "text") {
            const editorEl = sectionEl.querySelector("[data-section-editor]");
            const bodyHtml = editorEl ? $(editorEl).summernote("code") : "";
            if (stripHtml(bodyHtml) === "") {
              return null;
            }
            return {
              type: "text",
              body_html: bodyHtml
            };
          }

          const imageUrl = String(sectionEl.querySelector("[data-section-image-url]")?.value || "").trim();
          const caption = String(sectionEl.querySelector("[data-section-image-caption]")?.value || "").trim();
          if (imageUrl === "") {
            return null;
          }
          return {
            type: "image",
            image_url: imageUrl,
            caption: caption
          };
        }).filter(Boolean);
      }

      function buildComposedNewsHtml(title, headlineImageUrl, bodyHtml, sections) {
        const parts = [];
        const safeTitle = escapeHtml(title || "News image");
        if (headlineImageUrl) {
          parts.push(`<figure class="news-headline-figure"><img src="${escapeHtml(headlineImageUrl)}" alt="${safeTitle}"></figure>`);
        }
        if (stripHtml(bodyHtml) !== "") {
          parts.push(bodyHtml);
        }
        sections.forEach(function (section) {
          if (section.type === "text" && stripHtml(section.body_html || "") !== "") {
            parts.push(`<section class="news-extra-block news-extra-block--text">${section.body_html}</section>`);
            return;
          }
          if (section.type === "image" && section.image_url) {
            parts.push(
              `<figure class="news-extra-block news-extra-block--image"><img src="${escapeHtml(section.image_url)}" alt="${safeTitle}">`
              + (section.caption ? `<figcaption>${escapeHtml(section.caption)}</figcaption>` : "")
              + `</figure>`
            );
          }
        });
        return parts.join("\n");
      }

      function buildPreviewBodyHtml(sections, bodyHtml, title) {
        const parts = [];
        const safeTitle = escapeHtml(title || "News image");
        if (stripHtml(bodyHtml) !== "") {
          parts.push(bodyHtml);
        }
        sections.forEach(function (section) {
          if (section.type === "text" && stripHtml(section.body_html || "") !== "") {
            parts.push(`<section class="news-extra-block news-extra-block--text">${section.body_html}</section>`);
            return;
          }
          if (section.type === "image" && section.image_url) {
            parts.push(
              `<figure class="news-extra-block news-extra-block--image"><img src="${escapeHtml(section.image_url)}" alt="${safeTitle}">`
              + (section.caption ? `<figcaption>${escapeHtml(section.caption)}</figcaption>` : "")
              + `</figure>`
            );
          }
        });
        return parts.join("\n");
      }

      function syncHiddenInputs() {
        const title = String(headingInput?.value || "").trim();
        const headlineImageUrl = String(headlineImageUrlInput?.value || "").trim();
        const bodyHtml = getBodyHtml();
        const sections = collectSections();
        const composedHtml = buildComposedNewsHtml(title, headlineImageUrl, bodyHtml, sections);

        if (newsBodyHtmlInput) {
          newsBodyHtmlInput.value = bodyHtml;
        }
        if (newsSectionsJsonInput) {
          newsSectionsJsonInput.value = JSON.stringify(sections);
        }
        if (newsComposedHtmlInput) {
          newsComposedHtmlInput.value = composedHtml;
        }

        return {
          title,
          headlineImageUrl,
          bodyHtml,
          sections,
          composedHtml
        };
      }

      function renderPreview() {
        const payload = syncHiddenInputs();
        const publishDateText = formatPreviewDate();
        const previewBodyHtml = buildPreviewBodyHtml(payload.sections, payload.bodyHtml, payload.title);
        const composedPlain = stripHtml(previewBodyHtml);

        if (tilePreviewDate) {
          tilePreviewDate.textContent = publishDateText;
        }
        if (previewDate) {
          previewDate.textContent = publishDateText;
        }
        if (tilePreviewTitle) {
          tilePreviewTitle.textContent = payload.title || "Your news headline will appear here.";
        }
        if (previewHeadline) {
          previewHeadline.textContent = payload.title || "Your news headline will appear here.";
        }

        setMediaPreview(tilePreviewMedia, payload.headlineImageUrl, "Upload a headline image to preview the tile.");
        if (previewHero) {
          previewHero.innerHTML = payload.headlineImageUrl
            ? `<img src="${escapeHtml(payload.headlineImageUrl)}" alt="${escapeHtml(payload.title || 'Headline image')}">`
            : `<div class="news-upload-preview" style="min-height: 240px;"><span>Upload a headline image to preview the article hero.</span></div>`;
        }

        if (previewBody) {
          previewBody.innerHTML = composedPlain !== ""
            ? previewBodyHtml
            : `<p class="news-placeholder-copy">Write the story body to preview the article layout.</p>`;
        }
      }

      async function handleHeadlineImageUpload() {
        const file = headlineImageFileInput?.files && headlineImageFileInput.files[0] ? headlineImageFileInput.files[0] : null;
        if (!file) {
          return;
        }
        if (file.size > MAX_IMAGE_SIZE_BYTES) {
          window.alert("Image must be 50MB or less.");
          headlineImageFileInput.value = "";
          return;
        }

        if (headlineImageStatus) {
          headlineImageStatus.textContent = "Uploading headline image...";
        }
        try {
          setUploading(true);
          const imageUrl = await uploadImageFile(file);
          if (headlineImageUrlInput) {
            headlineImageUrlInput.value = imageUrl;
          }
          if (headlineImageStatus) {
            headlineImageStatus.textContent = "Headline image uploaded.";
          }
          setMediaPreview(headlineImagePreview, imageUrl, "Headline image preview will appear here.");
          renderPreview();
        } catch (error) {
          if (headlineImageUrlInput) {
            headlineImageUrlInput.value = "";
          }
          if (headlineImageStatus) {
            headlineImageStatus.textContent = "Unable to upload headline image.";
          }
          setMediaPreview(headlineImagePreview, "", "Headline image preview will appear here.");
          window.alert(error.message || "Unable to upload image.");
        } finally {
          setUploading(false);
          headlineImageFileInput.value = "";
        }
      }

      initEditor(bodyEditorEl, "Write the opening news story here...");
      updateSectionsEmptyState();
      renderPreview();

      if (headlineImageFileInput) {
        headlineImageFileInput.addEventListener("change", handleHeadlineImageUpload);
      }
      if (headingInput) {
        headingInput.addEventListener("input", renderPreview);
      }
      if (scheduleDateInput) {
        scheduleDateInput.addEventListener("change", renderPreview);
      }
      if (scheduleTimeInput) {
        scheduleTimeInput.addEventListener("change", renderPreview);
      }

      document.getElementById("btnAddTextSection")?.addEventListener("click", function () {
        addSection("text");
      });
      document.getElementById("btnAddImageSection")?.addEventListener("click", function () {
        addSection("image");
      });
      document.getElementById("btnScrollPreview")?.addEventListener("click", function () {
        document.querySelector(".news-preview-card")?.scrollIntoView({ behavior: "smooth", block: "start" });
      });

      if (formEl) {
        formEl.addEventListener("submit", function (event) {
          const payload = syncHiddenInputs();
          if (activeUploads > 0) {
            event.preventDefault();
            window.alert("Wait for the current image upload to finish before saving the news article.");
            return;
          }
          if (payload.title === "") {
            event.preventDefault();
            window.alert("Enter the news heading before saving.");
            return;
          }
          if (payload.headlineImageUrl === "") {
            event.preventDefault();
            window.alert("Upload the headline image before saving.");
            return;
          }
          if (stripHtml(payload.bodyHtml) === "") {
            event.preventDefault();
            window.alert("Write the main news body before saving.");
            return;
          }
        });
      }

      const postBtn = document.getElementById("btnPostNews");
      if (isSuperAdmin && postBtn) {
        postBtn.addEventListener("click", function (event) {
          if (!window.confirm("Are you sure this news article is ready to post?")) {
            event.preventDefault();
          }
        });
      }
    })();
  </script>
</body>
</html>
