<?php
require_once __DIR__ . "/../includes/admin_guard.php";
require_once __DIR__ . "/../../PhpFiles/General/connection.php";
require_once __DIR__ . "/../../PhpFiles/Admin-End/contentStore.php";
require_once __DIR__ . "/../../PhpFiles/Admin-End/newsContent.php";

$currentUserId = trim((string)($_SESSION['user_id'] ?? ''));
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isSuperAdmin = $sessionRole === 'superadmin';
$editingAnnouncementId = trim((string)($_GET['announcement_id'] ?? ''));
$autoOpenPreview = in_array(strtolower(trim((string)($_GET['open_preview'] ?? ''))), ['1', 'true', 'yes'], true);
$draftInitialState = [
  'announcement_id' => '',
  'title' => '',
  'headline_image_url' => '',
  'body_html' => '',
  'sections' => [],
  'schedule_date' => '',
  'schedule_time' => '',
];

if ($editingAnnouncementId !== '') {
  foreach (announcements_load_all() as $item) {
    if ((string)($item['id'] ?? '') !== $editingAnnouncementId) {
      continue;
    }

    $recordContentType = strtolower(trim((string)($item['content_type'] ?? 'page')));
    $recordStatus = strtolower(trim((string)($item['status'] ?? 'draft')));
    $recordOwnerUserId = trim((string)($item['created_by_user_id'] ?? ''));
    $fallbackOwnerUserId = trim((string)($item['created_by'] ?? ''));
    if ($recordOwnerUserId === '' && strpos($fallbackOwnerUserId, ' - ') === false) {
      $recordOwnerUserId = $fallbackOwnerUserId;
    }
    $isOwnedByCurrentUser = $currentUserId !== '' && $recordOwnerUserId !== '' && $recordOwnerUserId === $currentUserId;

    if ($recordContentType !== 'news' || (!$isSuperAdmin && !$isOwnedByCurrentUser) || $recordStatus === 'archived') {
      break;
    }

    $composedHtml = trim((string)($item['public_news_content_html'] ?? ''));
    if ($composedHtml === '') {
      $composedHtml = trim((string)($item['content_html'] ?? ''));
    }
    $decomposed = ann_news_decompose_html($composedHtml);
    $sections = ann_news_decode_sections_json((string)($item['news_sections_json'] ?? ''));
    if ($sections === [] && !empty($decomposed['sections']) && is_array($decomposed['sections'])) {
      $sections = $decomposed['sections'];
    }

    $headlineImageUrl = trim((string)($item['news_headline_image_url'] ?? ''));
    if ($headlineImageUrl === '') {
      $headlineImageUrl = trim((string)($decomposed['headline_image_url'] ?? ''));
    }

    $publishDateRaw = trim((string)($item['publish_date'] ?? ''));
    $scheduleDate = '';
    $scheduleTime = '';
    if ($publishDateRaw !== '' && $publishDateRaw !== '-') {
      $publishTs = strtotime($publishDateRaw);
      if ($publishTs !== false) {
        $scheduleDate = date('Y-m-d', $publishTs);
        $scheduleTime = date('H:i', $publishTs);
      }
    }

    $draftInitialState = [
      'announcement_id' => (string)($item['id'] ?? ''),
      'title' => (string)($item['title'] ?? ''),
      'headline_image_url' => $headlineImageUrl,
      'body_html' => (string)($decomposed['body_html'] ?? ''),
      'sections' => $sections,
      'schedule_date' => $scheduleDate,
      'schedule_time' => $scheduleTime,
    ];
    break;
  }
}
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

    .news-page-heading {
      max-width: 96ch;
    }

    .news-form-shell {
      max-width: 1340px;
      margin: 0 auto;
      border: 1px solid #e5e7eb;
      border-radius: 24px;
      background: #ffffff;
      box-shadow: 0 12px 28px rgba(15, 23, 42, 0.05);
    }

    .news-form-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 1.5rem;
    }

    .news-form-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 14px;
      border-radius: 999px;
      background: #fff3e6;
      border: 1px solid #fed7aa;
      color: #9a3412;
      font-size: 0.85rem;
      font-weight: 700;
      white-space: nowrap;
    }

    .news-create-subtitle {
      max-width: 72ch;
      color: #667085;
      font-size: 0.82rem;
      line-height: 1.55;
    }

    .news-upload-shell,
    .news-preview-panel {
      border: 1px solid #edf1f5;
      border-radius: 16px;
      background: #ffffff;
      box-shadow: none;
    }

    .news-upload-shell {
      padding: 1.1rem;
    }

    .news-upload-shell--inline {
      padding: 0;
      border: 0;
      border-radius: 0;
      background: transparent;
      box-shadow: none;
      cursor: default;
    }

    .news-form-section {
      padding: 0;
    }

    .news-form-divider,
    .news-builder-divider {
      margin: 1.8rem 0;
      border: 0;
      border-top: 1px solid #e5e7eb;
      opacity: 1;
    }

    .news-builder-divider {
      margin: 1.4rem 0;
    }

    .news-builder-entry:first-child .news-builder-divider {
      display: none;
    }

    .news-builder-section {
      padding: 0;
      border: 0;
      border-radius: 0;
      background: transparent;
      box-shadow: none;
    }

    .news-upload-shell {
      cursor: pointer;
      transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
    }

    .news-upload-shell:not(.has-image):hover,
    .news-upload-shell:not(.has-image).drag-over {
      border-color: #de710c;
      background: #fffaf5;
      box-shadow: 0 0 0 4px rgba(222, 113, 12, 0.08);
    }

    .news-upload-shell:not(.has-image):hover .news-upload-dropzone,
    .news-upload-shell:not(.has-image).drag-over .news-upload-dropzone,
    .news-upload-shell:not(.has-image):hover .news-upload-preview,
    .news-upload-shell:not(.has-image).drag-over .news-upload-preview {
      border-color: #de710c;
      background: #fff7ef;
    }

    .news-upload-shell--inline:not(.has-image):hover,
    .news-upload-shell--inline:not(.has-image).drag-over {
      border-color: transparent;
      background: transparent;
      box-shadow: none;
    }

    .news-upload-shell--inline:not(.has-image):hover .news-upload-dropzone,
    .news-upload-shell--inline:not(.has-image).drag-over .news-upload-dropzone,
    .news-upload-shell--inline:not(.has-image):hover .news-upload-preview,
    .news-upload-shell--inline:not(.has-image).drag-over .news-upload-preview {
      border-color: #de710c;
      background: #fff7ef;
    }

    .news-upload-dropzone {
      min-height: 156px;
      border: 1.5px dashed #c8c8c8;
      border-radius: 16px;
      background: #fff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 18px;
      cursor: pointer;
      transition: border-color 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
    }

    .news-upload-dropzone:not(.has-image):hover,
    .news-upload-dropzone:not(.has-image).drag-over {
      border-color: #de710c;
      background: #fff7ef;
      box-shadow: 0 0 0 4px rgba(222, 113, 12, 0.08);
    }

    .news-upload-dropzone-icon {
      font-size: 1.35rem;
      color: #de710c;
      margin-bottom: 8px;
    }

    .news-upload-dropzone-text {
      font-size: 0.96rem;
      color: #333;
      margin-bottom: 2px;
      font-weight: 600;
    }

    .news-upload-dropzone-subtext {
      font-size: 0.82rem;
      color: #6c757d;
    }

    .news-upload-dropzone-input {
      display: none;
    }

    .news-banner-dropzone {
      position: relative;
      min-height: 290px;
      overflow: hidden;
      width: 100%;
    }

    .news-banner-dropzone-copy {
      position: relative;
      z-index: 1;
      transition: opacity 0.2s ease;
    }

    .news-banner-dropzone.has-image {
      padding: 0;
      border-style: solid;
      border-color: #d7dee7;
      background: #0f172a;
      cursor: default;
    }

    .news-banner-dropzone.has-image::after {
      content: "";
      position: absolute;
      inset: 0;
      background: rgba(15, 23, 42, 0.38);
      opacity: 0;
      transition: opacity 0.2s ease;
      z-index: 2;
    }

    .news-banner-dropzone.has-image:hover::after,
    .news-banner-dropzone.has-image:focus-within::after {
      opacity: 1;
    }

    .news-banner-dropzone.has-image .news-banner-dropzone-copy {
      opacity: 0;
      pointer-events: none;
    }

    .news-banner-preview-image {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: none;
    }

    .news-banner-dropzone.has-image .news-banner-preview-image {
      display: block;
    }

    .news-section-inline-upload {
      margin-top: 0.35rem;
    }

    .news-section-inline-upload .news-banner-dropzone {
      min-height: 250px;
    }

    .news-upload-delete-btn {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%) scale(0.96);
      z-index: 3;
      width: 58px;
      height: 58px;
      border: 0;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.96);
      color: #b42318;
      box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18);
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s ease, transform 0.2s ease;
    }

    .news-banner-dropzone.has-image:hover .news-upload-delete-btn,
    .news-banner-dropzone.has-image:focus-within .news-upload-delete-btn {
      opacity: 1;
      pointer-events: auto;
      transform: translate(-50%, -50%) scale(1);
    }

    .news-upload-delete-btn:hover,
    .news-upload-delete-btn:focus {
      background: #ffffff;
      color: #912018;
    }

    .news-upload-preview,
    .news-section-image-preview {
      display: grid;
      place-items: center;
      min-height: 220px;
      overflow: hidden;
      border-radius: 18px;
      background:
        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
      border: 1px dashed #d7dee7;
      color: #6b7280;
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
      gap: 0;
    }

    .news-section-actions {
      display: flex;
      flex-direction: column;
      gap: 0.7rem;
      margin-top: 0.85rem;
      width: 100%;
    }

    .news-section-action-btn {
      width: 100%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.6rem;
      border: 0;
      border-radius: 12px;
      padding: 0.7rem 1rem;
      font-size: 0.95rem;
      font-weight: 700;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.07);
    }

    .news-section-action-btn:hover,
    .news-section-action-btn:focus,
    .news-section-action-btn:active {
      transform: none;
      box-shadow: 0 8px 18px rgba(15, 23, 42, 0.1);
    }

    .news-section-action-btn--text {
      background: #e8f0ff;
      color: #1d4ed8;
      border: 1px solid #c7d7fe;
    }

    .news-section-action-btn--text:hover,
    .news-section-action-btn--text:focus,
    .news-section-action-btn--text:active {
      background: #dbe8ff;
      color: #1e40af;
    }

    .news-section-action-btn--image {
      background: #fff1db;
      color: #b45309;
      border: 1px solid #f5d7a6;
    }

    .news-section-action-btn--image:hover,
    .news-section-action-btn--image:focus,
    .news-section-action-btn--image:active {
      background: #ffe8c2;
      color: #92400e;
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
      color: #475467;
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.02em;
      text-transform: none;
    }

    .news-form-card-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }

    .news-form-card-title h6 {
      margin: 0;
      font-weight: 700;
      color: #111827;
    }

    .news-form-card-title span {
      color: #6b7280;
      font-size: 0.82rem;
    }

    .news-preview-panel {
      padding: 1.2rem;
    }

    .news-modal-stage[hidden] {
      display: none !important;
    }

    .news-modal-preview-shell .modal-content {
      border: 0;
      border-radius: 28px;
      overflow: hidden;
    }

    .news-modal-preview-shell .modal-header,
    .news-modal-preview-shell .modal-footer {
      border-color: rgba(18, 18, 18, 0.06);
    }

    .news-modal-preview-shell .modal-body {
      background: linear-gradient(180deg, #fffdf9 0%, #fcf8f1 100%);
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

    .news-site-preview {
      --preview-news-ink: #1f2937;
      --preview-news-copy: #4b5563;
      --preview-news-muted: #667085;
      --preview-news-accent: #de710c;
      --preview-news-accent-deep: #b96416;
      min-height: 100%;
    }

    .news-site-preview .newsStoryPrimary {
      min-width: 0;
      max-width: none;
    }

    .news-site-preview .newsStoryToolbar {
      margin-bottom: 22px;
    }

    .news-site-preview .newsBackButton {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      min-height: 42px;
      padding: 10px 16px;
      border-radius: 999px;
      background: #f7efe3;
      color: var(--preview-news-accent-deep);
      font-size: 0.95rem;
      font-weight: 600;
      text-decoration: none;
      pointer-events: none;
      cursor: default;
    }

    .news-site-preview .newsArticleCard {
      padding: clamp(1.2rem, 2vw, 1.85rem);
      border: 1px solid #ece4d8;
      border-radius: 28px;
      background: #ffffff;
      box-shadow: 0 18px 42px rgba(15, 23, 42, 0.06);
    }

    .news-preview-panel--article {
      padding: clamp(1rem, 1.6vw, 1.35rem);
    }

    .news-schedule-panel {
      padding: 1.25rem;
    }

    .news-schedule-panel-copy {
      color: #667085;
      font-size: 0.98rem;
      line-height: 1.65;
      margin-bottom: 1.2rem;
    }

    .news-site-preview .articleTag {
      margin: 0 0 18px;
      color: #1f1f1f;
      font-size: 0.98rem;
      font-weight: 600;
    }

    .news-site-preview .articleHeadline {
      margin: 0 0 16px;
      width: 100%;
      max-width: none;
      color: var(--preview-news-ink);
      font-size: clamp(2rem, 3.4vw, 3.35rem);
      font-weight: 700;
      line-height: 1.04;
      letter-spacing: -0.04em;
      text-wrap: balance;
    }

    .news-site-preview .articleMetaStrip {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 24px;
    }

    .news-site-preview .articleDateBadge {
      display: inline-flex;
      align-items: center;
      min-height: 34px;
      padding: 6px 12px;
      border-radius: 10px;
      background: #f1f1f1;
      color: #4b4b4b;
      font-size: 0.92rem;
      font-weight: 500;
    }

    .news-site-preview .articleImageWrapper {
      width: 100%;
    }

    .news-site-preview .articleHeroWrapper {
      position: relative;
      overflow: hidden;
      margin-bottom: 32px;
      border-radius: 22px;
      background: #f4f1ec;
      aspect-ratio: 16 / 9;
      min-height: 320px;
    }

    .news-site-preview .articleHeroWrapper > * {
      width: 100%;
      height: 100%;
    }

    .news-site-preview .articleHeroWrapper img,
    .news-site-preview .articleHeroWrapper video,
    .news-site-preview .articleHeroWrapper iframe,
    .news-site-preview .articleHeroWrapper picture {
      width: 100%;
      height: 100%;
      display: block;
    }

    .news-site-preview .articleHeroWrapper img,
    .news-site-preview .articleHeroWrapper video {
      object-fit: cover;
      object-position: center;
    }

    .news-site-preview .articleHeroWrapper iframe {
      border: 0;
    }

    .news-site-preview .placeholderImage {
      min-height: 320px;
      background:
        radial-gradient(circle at 20% 20%, rgba(255, 153, 51, 0.16), transparent 22%),
        linear-gradient(135deg, #efe7db 0%, #f8f4ee 100%);
    }

    .news-site-preview .news-preview-placeholder-image {
      display: grid;
      place-items: center;
      padding: 1.5rem;
      color: var(--preview-news-muted);
      text-align: center;
      font-size: 0.94rem;
      line-height: 1.6;
    }

    .news-site-preview .articleBody {
      color: var(--preview-news-copy);
      max-width: none;
      text-align: justify;
      text-justify: inter-word;
    }

    .news-site-preview .articleRichContent {
      word-break: break-word;
    }

    .news-site-preview .articleRichContent > :first-child {
      margin-top: 0 !important;
    }

    .news-site-preview .articleRichContent > :last-child {
      margin-bottom: 0 !important;
    }

    .news-site-preview .articleRichContent :where(p, ul, ol, li, td, th, span, strong, em, b, i, small) {
      color: var(--preview-news-copy) !important;
      background: transparent !important;
    }

    .news-site-preview .articleRichContent :where(h1, h2, h3, h4, h5, h6) {
      margin-top: 1.45em !important;
      margin-bottom: 0.68em !important;
      color: var(--preview-news-ink) !important;
      font-weight: 700 !important;
      line-height: 1.14 !important;
      letter-spacing: -0.03em;
    }

    .news-site-preview .articleRichContent h1 {
      font-size: 2.1rem !important;
    }

    .news-site-preview .articleRichContent h2 {
      font-size: 1.85rem !important;
    }

    .news-site-preview .articleRichContent h3 {
      font-size: 1.55rem !important;
    }

    .news-site-preview .articleRichContent h4,
    .news-site-preview .articleRichContent h5,
    .news-site-preview .articleRichContent h6 {
      font-size: 1.24rem !important;
    }

    .news-site-preview .articleRichContent p,
    .news-site-preview .articleRichContent li {
      font-size: 1.04rem !important;
      line-height: 1.88 !important;
      text-align: justify;
      text-justify: inter-word;
    }

    .news-site-preview .articleRichContent p {
      margin: 0 0 1.2rem !important;
    }

    .news-site-preview .articleRichContent ul,
    .news-site-preview .articleRichContent ol {
      margin: 0 0 1.25rem !important;
      padding-left: 1.45rem !important;
    }

    .news-site-preview .articleRichContent li {
      margin-bottom: 0.42rem !important;
    }

    .news-site-preview .articleRichContent li::marker {
      color: var(--preview-news-accent-deep);
    }

    .news-site-preview .articleRichContent blockquote {
      margin: 1.6rem 0 !important;
      padding: 1.15rem 1.35rem !important;
      border-left: 4px solid var(--preview-news-accent);
      border-radius: 0 18px 18px 0;
      background: #fff8ef;
      color: var(--preview-news-ink);
    }

    .news-site-preview .articleRichContent a {
      color: var(--preview-news-accent-deep) !important;
      text-decoration-color: rgba(185, 100, 22, 0.35);
      text-underline-offset: 3px;
    }

    .news-site-preview .articleRichContent hr {
      margin: 2rem 0 !important;
      border: 0;
      border-top: 1px solid rgba(0, 0, 0, 0.09);
    }

    .news-site-preview .articleRichContent img,
    .news-site-preview .articleRichContent video,
    .news-site-preview .articleRichContent iframe,
    .news-site-preview .articleRichContent picture,
    .news-site-preview .articleRichContent table {
      max-width: 100%;
    }

    .news-site-preview .articleRichContent img,
    .news-site-preview .articleRichContent video {
      display: block;
      width: 100%;
      height: auto;
      margin: 1.6rem auto;
      border-radius: 18px;
    }

    .news-site-preview .articleRichContent figure {
      margin: 1.7rem 0 !important;
    }

    .news-site-preview .articleRichContent figure img {
      margin: 0 auto;
    }

    .news-site-preview .articleRichContent figure figcaption {
      margin-top: 0.75rem;
      color: var(--preview-news-muted) !important;
      font-size: 0.92rem !important;
      line-height: 1.55 !important;
      text-align: center;
    }

    .news-site-preview .articleRichContent iframe {
      display: block;
      width: 100%;
      min-height: 360px;
      margin: 1.5rem 0;
      border: 0;
      border-radius: 18px;
    }

    .news-site-preview .articleRichContent table {
      width: 100% !important;
      display: block;
      overflow-x: auto;
      margin: 1.5rem 0;
      border-collapse: collapse;
      border-radius: 18px;
      background: #ffffff;
      box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
    }

    .news-site-preview .articleRichContent th,
    .news-site-preview .articleRichContent td {
      padding: 12px 14px !important;
      border: 1px solid rgba(0, 0, 0, 0.08);
      text-align: left;
    }

    .news-site-preview .articleRichContent th {
      background: #fff3e4 !important;
      color: var(--preview-news-ink) !important;
      font-weight: 700;
    }

    .news-placeholder-copy {
      color: #667085;
      font-size: 0.95rem;
      line-height: 1.7;
    }

    .news-form-shell .announcement-sticky-actions {
      border-top: 0;
      padding-top: 0.5rem;
      justify-content: flex-end;
    }

    .news-form-shell .announcement-modal-footer-start {
      width: 100%;
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    @media (max-width: 767px) {
      .news-form-header {
        margin-bottom: 1.1rem;
      }

      .news-upload-preview,
      .news-section-image-preview {
        min-height: 180px;
      }

      .news-site-preview .newsArticleCard {
        padding: 1rem;
        border-radius: 22px;
      }

      .news-site-preview .articleHeadline {
        font-size: clamp(1.7rem, 8vw, 2.4rem);
      }

      .news-site-preview .articleHeroWrapper,
      .news-site-preview .placeholderImage {
        min-height: 220px;
      }
    }
  </style>
</head>
<body>
  <div class="d-flex flex-column flex-md-row" style="min-height: 100vh;">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main id="main-display" class="flex-grow-1 p-3 p-md-4 p-xl-5 bg-light">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="news-page-heading">
          <h2 class="mb-1" style="font-family: 'Charis SIL Bold'; color: #DE710C;"><?= $draftInitialState['announcement_id'] !== '' ? 'Edit Draft News' : 'Create News' ?></h2>
          <p class="text-muted mb-0">Build a public news article in stages, then use the modal preview to save as draft, post now, or schedule it for later.</p>
        </div>
      </div>
      <hr><br>

      <form class="announcement-create-shell news-form-shell p-3 p-md-4 p-xl-4" id="newsCreateForm" action="../../PhpFiles/Admin-End/announcementsCreation.php" method="post">
        <input type="hidden" name="announcement_id" id="newsAnnouncementIdInput" value="<?= htmlspecialchars((string)$draftInitialState['announcement_id'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="channel_context" value="public_news">
        <input type="hidden" name="content_type" value="news">
        <input type="hidden" name="channels[]" value="public_news">
        <input type="hidden" name="submit_action" id="newsSubmitActionInput" value="">
        <input type="hidden" name="headline_image_url" id="headlineImageUrlInput" value="">
        <input type="hidden" name="news_body_html" id="newsBodyHtmlInput" value="">
        <input type="hidden" name="news_sections_json" id="newsSectionsJsonInput" value="">
        <input type="hidden" name="content_html" id="newsComposedHtmlInput" value="">
        <input type="hidden" id="newsScheduleDateInput" name="schedule_date" value="">
        <input type="hidden" id="newsScheduleTimeInput" name="schedule_time" value="">

        <div class="news-form-header">
          <div>
            <h5 class="fw-bold mb-1">Public News Publishing Form</h5>
            <p class="text-muted mb-0">Encode the article details here, then preview the public tile and article layout before you save, post, or schedule it.</p>
          </div>
          <div class="news-form-badge">
            <i class="fas fa-newspaper"></i>
            Public News Workflow
          </div>
        </div>

        <div class="row g-4 news-compose-layout">
          <div class="col-12">
            <section class="news-form-section">
              <div class="news-form-card-title">
                <h6>Header Title</h6>
                <span>This headline appears on the news card and inside the full article preview.</span>
              </div>
              <div class="mb-0">
                <label for="newsHeadingInput" class="form-label fw-semibold small">Header Title</label>
                <input type="text" class="form-control announcement-primary-title-input" id="newsHeadingInput" name="title" placeholder="Enter the header title" required>
              </div>
            </section>
            <hr class="news-form-divider">

            <section class="news-form-section">
              <div class="news-form-card-title">
                <h6>News Banner</h6>
                <span>Upload the lead image that will appear in the public tile preview and article hero.</span>
              </div>
              <div class="news-upload-shell news-upload-shell--inline" id="headlineImageShell">
                <label class="form-label fw-semibold small">Upload News Banner</label>
                <div class="news-upload-dropzone news-banner-dropzone" id="headlineImagePreview" role="button" tabindex="0" aria-controls="headlineImageFile" aria-label="Upload news banner">
                  <img src="" alt="News banner preview" class="news-banner-preview-image" id="headlineImagePreviewImage">
                  <div class="news-banner-dropzone-copy" id="headlineImagePrompt">
                    <div class="news-upload-dropzone-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                    <div class="news-upload-dropzone-text">Drag and drop image here</div>
                    <div class="news-upload-dropzone-subtext">or click to choose manually</div>
                    <div class="news-upload-dropzone-subtext mt-1">Accepted: JPG, PNG, WEBP, GIF. Maximum 50MB.</div>
                  </div>
                  <button type="button" class="news-upload-delete-btn" id="btnRemoveHeadlineImage" aria-label="Delete news banner">
                    <i class="fas fa-trash"></i>
                  </button>
                  <input type="file" class="news-upload-dropzone-input" id="headlineImageFile" accept="image/jpeg,image/png,image/webp,image/gif">
                </div>
                <div class="news-upload-status mt-3" id="headlineImageStatus">No news banner uploaded yet.</div>
              </div>
            </section>
            <hr class="news-form-divider">

            <section class="news-form-section">
              <div class="news-form-card-title">
                <h6>News Description</h6>
                <span>Write the main story body here. This becomes the opening article content.</span>
              </div>
              <div class="announcement-editor-panel">
                <div class="announcement-editor-panel-head">
                  <div>
                    <label class="form-label fw-semibold small mb-1">News Description</label>
                    <p class="announcement-editor-helper mb-0">Use this space for the story description. You can still format text, add links, and insert inline images if needed.</p>
                  </div>
                </div>
                <div id="newsBodyEditor"></div>
              </div>
            </section>
            <hr class="news-form-divider">

            <section class="news-form-section">
              <div class="news-form-card-title">
                <h6>Additional Sections</h6>
                <span>Add optional follow-up content blocks only when the article needs more detail.</span>
              </div>
              <div class="news-section-stack" id="newsSectionsContainer"></div>
              <div class="news-placeholder-copy mt-3" id="newsSectionsEmptyState">No additional sections yet. Add one only if the story needs more text or a supporting image.</div>
              <div class="news-section-actions">
                <button type="button" class="btn news-section-action-btn news-section-action-btn--text" id="btnAddTextSection">
                  <i class="fas fa-align-left"></i>&nbsp;Add Text Section
                </button>
                <button type="button" class="btn news-section-action-btn news-section-action-btn--image" id="btnAddImageSection">
                  <i class="fas fa-image"></i>&nbsp;Add Image Section
                </button>
              </div>
            </section>
          </div>
        </div>

        <div class="announcement-sticky-actions mt-4">
          <div class="announcement-modal-footer-start">
            <a href="<?= htmlspecialchars(appUrl('Admin-End/Contents/Contents.php')) ?>?tool=tracker&amp;type_filter=news#tracker-card" class="btn btn-outline-secondary">Manage News</a>
            <button type="button" class="btn btn-primary text-white" id="btnOpenPreviewModalFooter">Next: Preview</button>
          </div>
        </div>
      </form>

      <div class="modal fade news-modal-preview-shell" id="newsPreviewModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header px-4 py-3">
              <div>
                <p class="news-preview-kicker mb-1">Final Step</p>
                <h5 class="modal-title mb-0" id="newsPreviewModalLabel">Preview News Article</h5>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3 p-md-4">
              <div class="news-preview-panel news-preview-panel--article h-100">
                <h6 class="announcement-card-title mb-3">Article Preview</h6>
                <div class="news-site-preview">
                  <div class="newsStoryPrimary">
                    <div class="newsStoryToolbar">
                      <span class="newsBackButton" aria-hidden="true">
                        <i class="fa-solid fa-arrow-left-long" aria-hidden="true"></i>
                        <span>Return to all news</span>
                      </span>
                    </div>

                    <article class="newsArticleCard">
                      <p class="articleTag">Community Update</p>
                      <h2 class="articleHeadline" id="newsModalPreviewHeadline">Your news headline will appear here.</h2>
                      <div class="articleMetaStrip">
                        <span class="articleDateBadge" id="newsModalPreviewDate">Preview only</span>
                      </div>
                      <div class="articleImageWrapper articleHeroWrapper" id="newsModalPreviewHero">
                        <div class="placeholderImage news-preview-placeholder-image">
                          <span>Upload a news banner to preview the article hero.</span>
                        </div>
                      </div>
                      <div class="articleBody articleRichContent" id="newsModalPreviewBody">
                        <p class="news-placeholder-copy">Write the story body to preview the article layout.</p>
                      </div>
                    </article>
                  </div>
                </div>
              </div>
            </div>

            <div class="modal-footer px-4 py-3 justify-content-between">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Return to Editing</button>
              <div class="d-flex flex-wrap gap-2 justify-content-end">
                <button type="button" class="btn btn-warning text-dark" id="btnSaveNewsDraft" data-news-action>Save as Draft</button>
                <?php if ($isSuperAdmin): ?>
                  <button type="button" class="btn btn-primary text-white" id="btnPostNewsNow" data-news-action>Post Now</button>
                  <button type="button" class="btn btn-outline-primary" id="btnOpenScheduleStage" data-news-action>Scheduled Post</button>
                <?php else: ?>
                  <button type="button" class="btn btn-primary text-white" id="btnPostNewsNow" data-news-action>Submit for Review</button>
                  <button type="button" class="btn btn-outline-primary" id="btnOpenScheduleStage" data-news-action>Schedule Submission</button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="modal fade news-modal-preview-shell" id="newsScheduleModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header px-4 py-3">
              <div>
                <p class="news-preview-kicker mb-1">Schedule Post</p>
                <h5 class="modal-title mb-0">Choose Publish Time</h5>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-3 p-md-4">
              <div class="news-preview-panel news-schedule-panel">
                <h6 class="announcement-card-title mb-3">Scheduled Post</h6>
                <p class="news-schedule-panel-copy">Choose when this news article should become available.</p>
                <div class="mb-3">
                  <label for="modalNewsScheduleDateInput" class="form-label fw-semibold">Date</label>
                  <input type="date" class="form-control" id="modalNewsScheduleDateInput">
                </div>
                <div class="mb-0">
                  <label for="modalNewsScheduleTimeInput" class="form-label fw-semibold">Time</label>
                  <input type="time" class="form-control" id="modalNewsScheduleTimeInput">
                </div>
              </div>
            </div>

            <div class="modal-footer px-4 py-3 justify-content-between">
              <button type="button" class="btn btn-outline-secondary" id="btnReturnToPreviewStage">Return to Preview</button>
              <div class="d-flex flex-wrap gap-2 justify-content-end">
                <button type="button" class="btn btn-primary text-white" id="btnScheduleNewsAction" data-news-action><?= $isSuperAdmin ? 'Schedule Post' : 'Schedule Submission' ?></button>
              </div>
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
      const isSuperAdmin = <?= $isSuperAdmin ? 'true' : 'false' ?>;
      const initialDraftState = <?= json_encode($draftInitialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const shouldAutoOpenPreview = <?= $autoOpenPreview ? 'true' : 'false' ?>;

      const formEl = document.getElementById("newsCreateForm");
      const headingInput = document.getElementById("newsHeadingInput");
      const submitActionInput = document.getElementById("newsSubmitActionInput");
      const scheduleDateInput = document.getElementById("newsScheduleDateInput");
      const scheduleTimeInput = document.getElementById("newsScheduleTimeInput");
      const headlineImageShell = document.getElementById("headlineImageShell");
      const headlineImageFileInput = document.getElementById("headlineImageFile");
      const headlineImageUrlInput = document.getElementById("headlineImageUrlInput");
      const headlineImageStatus = document.getElementById("headlineImageStatus");
      const headlineImagePreview = document.getElementById("headlineImagePreview");
      const headlineImagePreviewImage = document.getElementById("headlineImagePreviewImage");
      const headlineImagePrompt = document.getElementById("headlineImagePrompt");
      const removeHeadlineImageBtn = document.getElementById("btnRemoveHeadlineImage");
      const newsBodyHtmlInput = document.getElementById("newsBodyHtmlInput");
      const newsSectionsJsonInput = document.getElementById("newsSectionsJsonInput");
      const newsComposedHtmlInput = document.getElementById("newsComposedHtmlInput");
      const sectionsContainer = document.getElementById("newsSectionsContainer");
      const sectionsEmptyState = document.getElementById("newsSectionsEmptyState");
      const previewHeadline = document.getElementById("newsPreviewHeadline");
      const previewDate = document.getElementById("newsPreviewDate");
      const previewHero = document.getElementById("newsPreviewHero");
      const previewBody = document.getElementById("newsPreviewBody");
      const previewSyncLabel = document.getElementById("newsPreviewSyncLabel");
      const bodyEditorEl = $("#newsBodyEditor");
      const submitButtons = Array.from(document.querySelectorAll("[data-news-action]"));
      const openPreviewModalButtons = [
        document.getElementById("btnOpenPreviewModal"),
        document.getElementById("btnOpenPreviewModalFooter")
      ].filter(Boolean);
      const previewModalEl = document.getElementById("newsPreviewModal");
      const scheduleModalEl = document.getElementById("newsScheduleModal");
      const modalScheduleDateInput = document.getElementById("modalNewsScheduleDateInput");
      const modalScheduleTimeInput = document.getElementById("modalNewsScheduleTimeInput");
      const saveDraftBtn = document.getElementById("btnSaveNewsDraft");
      const postNowBtn = document.getElementById("btnPostNewsNow");
      const openScheduleStageBtn = document.getElementById("btnOpenScheduleStage");
      const returnToPreviewStageBtn = document.getElementById("btnReturnToPreviewStage");
      const scheduleNewsActionBtn = document.getElementById("btnScheduleNewsAction");
      const modalPreviewHeadline = document.getElementById("newsModalPreviewHeadline");
      const modalPreviewDate = document.getElementById("newsModalPreviewDate");
      const modalPreviewHero = document.getElementById("newsModalPreviewHero");
      const modalPreviewBody = document.getElementById("newsModalPreviewBody");

      let sectionCounter = 0;
      let activeUploads = 0;
      let previewModal = null;
      let scheduleModal = null;
      let requireScheduleBeforeSubmit = false;

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

      function bindDropzone(dropzoneEl, inputEl, onFileSelected, options = {}) {
        if (!dropzoneEl || !inputEl || typeof onFileSelected !== "function") {
          return;
        }

        const shouldOpenPicker = typeof options.shouldOpenPicker === "function"
          ? options.shouldOpenPicker
          : function () {
            return true;
          };

        dropzoneEl.addEventListener("click", function () {
          if (!shouldOpenPicker()) {
            return;
          }
          inputEl.click();
        });

        dropzoneEl.addEventListener("keydown", function (event) {
          if (!shouldOpenPicker()) {
            return;
          }
          if (event.key === "Enter" || event.key === " ") {
            event.preventDefault();
            inputEl.click();
          }
        });

        ["dragenter", "dragover"].forEach(function (eventName) {
          dropzoneEl.addEventListener(eventName, function (event) {
            event.preventDefault();
            if (!shouldOpenPicker()) {
              return;
            }
            dropzoneEl.classList.add("drag-over");
          });
        });

        ["dragleave", "dragend", "drop"].forEach(function (eventName) {
          dropzoneEl.addEventListener(eventName, function (event) {
            event.preventDefault();
            dropzoneEl.classList.remove("drag-over");
          });
        });

        dropzoneEl.addEventListener("drop", function (event) {
          if (!shouldOpenPicker()) {
            return;
          }
          const droppedFiles = event.dataTransfer ? event.dataTransfer.files : null;
          if (!droppedFiles || !droppedFiles.length) {
            return;
          }
          onFileSelected(droppedFiles[0]);
        });

        inputEl.addEventListener("change", function () {
          const file = inputEl.files && inputEl.files[0] ? inputEl.files[0] : null;
          if (!file) {
            return;
          }
          onFileSelected(file);
        });
      }

      function setHeadlineImagePreview(imageUrl) {
        const safeUrl = String(imageUrl || "").trim();
        const hasImage = safeUrl !== "";

        if (headlineImagePreview) {
          headlineImagePreview.classList.toggle("has-image", hasImage);
          headlineImagePreview.setAttribute("aria-label", hasImage ? "Uploaded news banner" : "Upload news banner");
        }
        if (headlineImageShell) {
          headlineImageShell.classList.toggle("has-image", hasImage);
        }
        if (headlineImagePreviewImage) {
          headlineImagePreviewImage.src = hasImage ? safeUrl : "";
        }
        if (headlineImagePrompt) {
          headlineImagePrompt.setAttribute("aria-hidden", hasImage ? "true" : "false");
        }
      }

      function clearHeadlineImage() {
        if (headlineImageUrlInput) {
          headlineImageUrlInput.value = "";
        }
        if (headlineImageFileInput) {
          headlineImageFileInput.value = "";
        }
        if (headlineImageStatus) {
          headlineImageStatus.textContent = "News banner removed. Upload another image.";
        }
        setHeadlineImagePreview("");
        renderPreview();
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

      function setScheduleFields(dateValue, timeValue) {
        if (scheduleDateInput) {
          scheduleDateInput.value = String(dateValue || "").trim();
        }
        if (scheduleTimeInput) {
          scheduleTimeInput.value = String(timeValue || "").trim();
        }
      }

      function clearScheduleFields() {
        setScheduleFields("", "");
        if (modalScheduleDateInput) {
          modalScheduleDateInput.value = "";
        }
        if (modalScheduleTimeInput) {
          modalScheduleTimeInput.value = "";
        }
      }

      function syncScheduleModalInputsFromHidden() {
        if (modalScheduleDateInput) {
          modalScheduleDateInput.value = String(scheduleDateInput?.value || "").trim();
        }
        if (modalScheduleTimeInput) {
          modalScheduleTimeInput.value = String(scheduleTimeInput?.value || "").trim();
        }
      }

      function swapModal(currentModal, nextModal) {
        if (!nextModal) {
          currentModal?.hide();
          return;
        }
        if (!currentModal) {
          nextModal.show();
          return;
        }

        const currentModalNode = currentModal._element;
        if (!currentModalNode) {
          currentModal.hide();
          nextModal.show();
          return;
        }

        currentModalNode.addEventListener("hidden.bs.modal", function handleHidden() {
          currentModalNode.removeEventListener("hidden.bs.modal", handleHidden);
          nextModal.show();
        });
        currentModal.hide();
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
          <div class="news-builder-entry" data-section-id="${sectionId}" data-section-type="text">
            <hr class="news-builder-divider">
            <article class="news-builder-section">
              <div class="news-builder-section-head">
                <div>
                  <p class="news-builder-section-kicker">Text Section</p>
                  <h6 class="announcement-card-title mb-0">Additional Story Block</h6>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm" data-remove-section>Remove</button>
              </div>
              <div data-section-editor></div>
            </article>
          </div>
        `;
      }

      function buildImageSectionMarkup(sectionId) {
        return `
          <div class="news-builder-entry" data-section-id="${sectionId}" data-section-type="image">
            <hr class="news-builder-divider">
            <article class="news-builder-section">
              <div class="news-builder-section-head">
                <div>
                  <p class="news-builder-section-kicker">Image Section</p>
                  <h6 class="announcement-card-title mb-0">Supporting Image</h6>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm" data-remove-section>Remove</button>
              </div>
              <div class="news-section-inline-upload">
                <label class="form-label fw-semibold small">Upload Image</label>
                <div class="news-upload-shell news-upload-shell--inline" data-section-image-shell>
                  <div class="news-upload-dropzone news-banner-dropzone" data-section-image-dropzone role="button" tabindex="0" aria-label="Upload supporting image">
                    <img src="" alt="Supporting image preview" class="news-banner-preview-image" data-section-image-preview-image>
                    <div class="news-banner-dropzone-copy" data-section-image-prompt>
                      <div class="news-upload-dropzone-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                      <div class="news-upload-dropzone-text">Drag and drop image here</div>
                      <div class="news-upload-dropzone-subtext">or click to choose manually</div>
                      <div class="news-upload-dropzone-subtext mt-1">Accepted: JPG, PNG, WEBP, GIF. Maximum 50MB.</div>
                    </div>
                    <button type="button" class="news-upload-delete-btn" data-section-image-delete aria-label="Delete supporting image">
                      <i class="fas fa-trash"></i>
                    </button>
                    <input type="file" class="news-upload-dropzone-input" data-section-image-file accept="image/jpeg,image/png,image/webp,image/gif">
                  </div>
                  <input type="hidden" data-section-image-url value="">
                </div>
                <label class="form-label fw-semibold small mt-3">Caption (optional)</label>
                <input type="text" class="form-control" data-section-image-caption placeholder="Add a short caption">
                <div class="news-upload-status mt-3" data-section-image-status>No image uploaded for this section yet.</div>
              </div>
            </article>
          </div>
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

        const imageDropzone = sectionEl.querySelector("[data-section-image-dropzone]");
        const imageShell = sectionEl.querySelector("[data-section-image-shell]");
        const imageFileInput = sectionEl.querySelector("[data-section-image-file]");
        const imageUrlInput = sectionEl.querySelector("[data-section-image-url]");
        const imageCaptionInput = sectionEl.querySelector("[data-section-image-caption]");
        const imageStatusEl = sectionEl.querySelector("[data-section-image-status]");
        const imagePreviewImageEl = sectionEl.querySelector("[data-section-image-preview-image]");
        const imagePromptEl = sectionEl.querySelector("[data-section-image-prompt]");
        const imageDeleteBtn = sectionEl.querySelector("[data-section-image-delete]");

        function setSectionImagePreview(imageUrl) {
          const safeUrl = String(imageUrl || "").trim();
          const hasImage = safeUrl !== "";

          if (imageDropzone) {
            imageDropzone.classList.toggle("has-image", hasImage);
            imageDropzone.setAttribute("aria-label", hasImage ? "Uploaded supporting image" : "Upload supporting image");
          }
          if (imageShell) {
            imageShell.classList.toggle("has-image", hasImage);
          }
          if (imagePreviewImageEl) {
            imagePreviewImageEl.src = hasImage ? safeUrl : "";
          }
          if (imagePromptEl) {
            imagePromptEl.setAttribute("aria-hidden", hasImage ? "true" : "false");
          }
        }

        function clearSectionImage() {
          if (imageUrlInput) {
            imageUrlInput.value = "";
          }
          if (imageFileInput) {
            imageFileInput.value = "";
          }
          if (imageStatusEl) {
            imageStatusEl.textContent = "Supporting image removed. Upload another image.";
          }
          setSectionImagePreview("");
          renderPreview();
        }

        if (imageDropzone && imageFileInput && imageUrlInput && imageStatusEl) {
          setSectionImagePreview(String(imageUrlInput.value || "").trim());
          bindDropzone(imageDropzone, imageFileInput, async function (file) {
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
              setSectionImagePreview(imageUrl);
              renderPreview();
            } catch (error) {
              imageUrlInput.value = "";
              imageStatusEl.textContent = "Unable to upload supporting image.";
              setSectionImagePreview("");
              window.alert(error.message || "Unable to upload image.");
            } finally {
              setUploading(false);
              imageFileInput.value = "";
            }
          }, {
            shouldOpenPicker: function () {
              return String(imageUrlInput.value || "").trim() === "";
            }
          });
        }

        if (imageDeleteBtn) {
          imageDeleteBtn.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();
            clearSectionImage();
          });
        }

        if (imageCaptionInput) {
          imageCaptionInput.addEventListener("input", renderPreview);
        }
      }

      function addSection(type, initialData = null) {
        if (!sectionsContainer) {
          return;
        }
        sectionCounter += 1;
        const markup = type === "image"
          ? buildImageSectionMarkup(sectionCounter)
          : buildTextSectionMarkup(sectionCounter);
        sectionsContainer.insertAdjacentHTML("beforeend", markup);
        const sectionEl = sectionsContainer.lastElementChild;
        if (sectionEl && initialData && type === "image") {
          const imageUrlInput = sectionEl.querySelector("[data-section-image-url]");
          const imageCaptionInput = sectionEl.querySelector("[data-section-image-caption]");
          const imageStatusEl = sectionEl.querySelector("[data-section-image-status]");
          if (imageUrlInput) {
            imageUrlInput.value = String(initialData.image_url || "").trim();
          }
          if (imageCaptionInput) {
            imageCaptionInput.value = String(initialData.caption || "").trim();
          }
          if (imageStatusEl && String(initialData.image_url || "").trim() !== "") {
            imageStatusEl.textContent = "Supporting image uploaded.";
          }
        }
        if (sectionEl) {
          attachSectionEvents(sectionEl);
          if (initialData && type === "text") {
            const editorEl = sectionEl.querySelector("[data-section-editor]");
            if (editorEl && editorEl.dataset.initialized === "true") {
              $(editorEl).summernote("code", String(initialData.body_html || ""));
            }
          }
        }
        updateSectionsEmptyState();
        renderPreview();
      }

      function loadInitialDraftState() {
        if (!initialDraftState || String(initialDraftState.announcement_id || "").trim() === "") {
          return;
        }

        if (headingInput) {
          headingInput.value = String(initialDraftState.title || "").trim();
        }
        if (headlineImageUrlInput) {
          headlineImageUrlInput.value = String(initialDraftState.headline_image_url || "").trim();
        }
        if (headlineImageStatus) {
          headlineImageStatus.textContent = String(initialDraftState.headline_image_url || "").trim() !== ""
            ? "Draft news banner loaded."
            : "No news banner uploaded yet.";
        }
        setScheduleFields(
          String(initialDraftState.schedule_date || "").trim(),
          String(initialDraftState.schedule_time || "").trim()
        );
        if (bodyEditorEl.length) {
          bodyEditorEl.summernote("code", String(initialDraftState.body_html || ""));
        }

        const sections = Array.isArray(initialDraftState.sections) ? initialDraftState.sections : [];
        sections.forEach(function (section) {
          const sectionType = String(section?.type || "").toLowerCase();
          if (sectionType === "text" || sectionType === "image") {
            addSection(sectionType, section);
          }
        });
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

        [previewDate, modalPreviewDate].forEach(function (el) {
          if (el) {
            el.textContent = publishDateText;
          }
        });
        [previewHeadline, modalPreviewHeadline].forEach(function (el) {
          if (el) {
            el.textContent = payload.title || "Your news headline will appear here.";
          }
        });

        [previewHero, modalPreviewHero].forEach(function (el) {
          if (!el) {
            return;
          }
          el.innerHTML = payload.headlineImageUrl
            ? `<img src="${escapeHtml(payload.headlineImageUrl)}" alt="${escapeHtml(payload.title || 'News banner')}">`
            : `<div class="placeholderImage news-preview-placeholder-image"><span>Upload a news banner to preview the article hero.</span></div>`;
        });

        [previewBody, modalPreviewBody].forEach(function (el) {
          if (!el) {
            return;
          }
          el.innerHTML = composedPlain !== ""
            ? previewBodyHtml
            : `<p class="news-placeholder-copy">Write the story body to preview the article layout.</p>`;
        });
      }

      function validateNewsPayload(options = {}) {
        const payload = syncHiddenInputs();
        if (activeUploads > 0) {
          window.alert("Wait for the current image upload to finish before continuing.");
          return null;
        }
        if (payload.title === "") {
          window.alert("Enter the header title before continuing.");
          return null;
        }
        if (payload.headlineImageUrl === "") {
          window.alert("Upload the news banner before continuing.");
          return null;
        }
        if (stripHtml(payload.bodyHtml) === "") {
          window.alert("Write the news description before continuing.");
          return null;
        }
        if (options.requireSchedule) {
          const scheduleDate = String(scheduleDateInput?.value || "").trim();
          const scheduleTime = String(scheduleTimeInput?.value || "").trim();
          if (scheduleDate === "" || scheduleTime === "") {
            window.alert("Choose both the schedule date and time before saving.");
            return null;
          }
        }
        return payload;
      }

      function submitNewsForm(action, options = {}) {
        if (options.clearSchedule) {
          clearScheduleFields();
          renderPreview();
        }
        if (submitActionInput) {
          submitActionInput.value = action;
        }
        requireScheduleBeforeSubmit = !!options.requireSchedule;
        const payload = validateNewsPayload({ requireSchedule: requireScheduleBeforeSubmit });
        if (!payload || !formEl) {
          requireScheduleBeforeSubmit = false;
          if (submitActionInput) {
            submitActionInput.value = "";
          }
          return;
        }
        formEl.requestSubmit();
      }

      async function handleHeadlineImageUpload(file) {
        if (!file) {
          return;
        }
        if (file.size > MAX_IMAGE_SIZE_BYTES) {
          window.alert("Image must be 50MB or less.");
          headlineImageFileInput.value = "";
          return;
        }

        if (headlineImageStatus) {
          headlineImageStatus.textContent = "Uploading news banner...";
        }
        try {
          setUploading(true);
          const imageUrl = await uploadImageFile(file);
          if (headlineImageUrlInput) {
            headlineImageUrlInput.value = imageUrl;
          }
          if (headlineImageStatus) {
            headlineImageStatus.textContent = "News banner uploaded.";
          }
          setHeadlineImagePreview(imageUrl);
          renderPreview();
        } catch (error) {
          if (headlineImageUrlInput) {
            headlineImageUrlInput.value = "";
          }
          if (headlineImageStatus) {
            headlineImageStatus.textContent = "Unable to upload news banner.";
          }
          setHeadlineImagePreview("");
          window.alert(error.message || "Unable to upload image.");
        } finally {
          setUploading(false);
          headlineImageFileInput.value = "";
        }
      }

      initEditor(bodyEditorEl, "Write the main news description here...");
      loadInitialDraftState();
      updateSectionsEmptyState();
      setHeadlineImagePreview(String(headlineImageUrlInput?.value || "").trim());
      renderPreview();

      [previewModalEl, scheduleModalEl].forEach(function (modalEl) {
        if (modalEl && modalEl.parentElement !== document.body) {
          document.body.appendChild(modalEl);
        }
      });

      if (previewModalEl && window.bootstrap?.Modal) {
        previewModal = new bootstrap.Modal(previewModalEl);
      }
      if (scheduleModalEl && window.bootstrap?.Modal) {
        scheduleModal = new bootstrap.Modal(scheduleModalEl);
      }

      bindDropzone(headlineImagePreview, headlineImageFileInput, handleHeadlineImageUpload, {
        shouldOpenPicker: function () {
          return String(headlineImageUrlInput?.value || "").trim() === "";
        }
      });
      removeHeadlineImageBtn?.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        clearHeadlineImage();
      });
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

      openPreviewModalButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          const payload = validateNewsPayload();
          if (!payload) {
            return;
          }
          renderPreview();
          previewModal?.show();
        });
      });

      openScheduleStageBtn?.addEventListener("click", function () {
        const payload = validateNewsPayload();
        if (!payload) {
          return;
        }
        syncScheduleModalInputsFromHidden();
        swapModal(previewModal, scheduleModal);
      });

      returnToPreviewStageBtn?.addEventListener("click", function () {
        swapModal(scheduleModal, previewModal);
      });

      modalScheduleDateInput?.addEventListener("change", function () {
        setScheduleFields(modalScheduleDateInput.value, modalScheduleTimeInput?.value || "");
      });
      modalScheduleTimeInput?.addEventListener("change", function () {
        setScheduleFields(modalScheduleDateInput?.value || "", modalScheduleTimeInput.value);
      });

      saveDraftBtn?.addEventListener("click", function () {
        submitNewsForm("draft", { clearSchedule: true });
      });

      postNowBtn?.addEventListener("click", function () {
        submitNewsForm(isSuperAdmin ? "approved" : "pending", { clearSchedule: true });
      });

      scheduleNewsActionBtn?.addEventListener("click", function () {
        setScheduleFields(modalScheduleDateInput?.value || "", modalScheduleTimeInput?.value || "");
        submitNewsForm(isSuperAdmin ? "approved" : "pending", { requireSchedule: true });
      });

      if (formEl) {
        formEl.addEventListener("submit", function (event) {
          const submitAction = String(submitActionInput?.value || "").trim();
          if (submitAction === "") {
            event.preventDefault();
            return;
          }
          const payload = syncHiddenInputs();
          if (activeUploads > 0) {
            event.preventDefault();
            window.alert("Wait for the current image upload to finish before saving the news article.");
            return;
          }
          if (payload.title === "") {
            event.preventDefault();
            window.alert("Enter the header title before saving.");
            return;
          }
          if (payload.headlineImageUrl === "") {
            event.preventDefault();
            window.alert("Upload the news banner before saving.");
            return;
          }
          if (stripHtml(payload.bodyHtml) === "") {
            event.preventDefault();
            window.alert("Write the news description before saving.");
            return;
          }
          if (requireScheduleBeforeSubmit) {
            const scheduleDate = String(scheduleDateInput?.value || "").trim();
            const scheduleTime = String(scheduleTimeInput?.value || "").trim();
            if (scheduleDate === "" || scheduleTime === "") {
              event.preventDefault();
              window.alert("Choose both the schedule date and time before saving.");
              requireScheduleBeforeSubmit = false;
              return;
            }
          }
          requireScheduleBeforeSubmit = false;
        });
      }

      if (shouldAutoOpenPreview) {
        window.setTimeout(function () {
          const payload = validateNewsPayload();
          if (!payload) {
            return;
          }
          renderPreview();
          previewModal?.show();
        }, 150);
      }
    })();
  </script>
</body>
</html>
