<?php
if (!isset($baseUrl)) {
  $scriptName = str_replace("\\", "/", (string)($_SERVER['SCRIPT_NAME'] ?? ''));
  $residentSegmentPos = strpos($scriptName, '/Resident-End/');
  $baseUrl = '';
  if ($residentSegmentPos !== false) {
    $baseUrl = substr($scriptName, 0, $residentSegmentPos);
  } else {
    $baseUrl = dirname($scriptName);
  }
  $baseUrl = rtrim((string)$baseUrl, '/');
  if ($baseUrl === '.' || $baseUrl === '/') {
    $baseUrl = '';
  }
}

$allowUnregistered = false;
require_once __DIR__ . "/includes/resident_access_guard.php";
require_once __DIR__ . "/../PhpFiles/Admin-End/announcementAudience.php";
require_once __DIR__ . "/../PhpFiles/Resident-End/residentScheduleFeed.php";

$viewerContext = ann_audience_fetch_resident_context($conn, (string)($_SESSION['user_id'] ?? ''));
$scheduleItems = resident_schedule_collect_all($conn, (string)($_SESSION['user_id'] ?? ''), $viewerContext);

$initialMonthDate = date('Y-m-01');
$foundUpcomingSchedule = false;
foreach ($scheduleItems as $item) {
  if (!empty($item['is_upcoming'])) {
    $initialMonthDate = date('Y-m-01', (int)$item['timestamp']);
    $foundUpcomingSchedule = true;
    break;
  }
}
if (!$foundUpcomingSchedule && $scheduleItems) {
  $latestSchedule = end($scheduleItems);
  if (is_array($latestSchedule) && !empty($latestSchedule['timestamp'])) {
    $initialMonthDate = date('Y-m-01', (int)$latestSchedule['timestamp']);
  }
}

$calendarPayload = array_map(static function (array $item): array {
  return [
    'id' => (string)($item['id'] ?? ''),
    'kind' => (string)($item['kind'] ?? ''),
    'kind_label' => (string)($item['kind_label'] ?? ''),
    'date_iso' => (string)($item['date_iso'] ?? ''),
    'datetime_iso' => (string)($item['datetime_iso'] ?? ''),
    'timestamp' => (int)($item['timestamp'] ?? 0),
    'is_upcoming' => !empty($item['is_upcoming']),
    'title' => (string)($item['title'] ?? ''),
    'summary' => (string)($item['summary'] ?? ''),
    'meta' => (string)($item['meta'] ?? ''),
    'status_label' => (string)($item['status_label'] ?? ''),
    'status_bucket' => (string)($item['status_bucket'] ?? ''),
    'href' => (string)($item['href'] ?? ''),
  ];
}, $scheduleItems);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/Images/favicon_sanjose.png?v=20260211">
  <title>Resident Calendar - Barangay San Jose</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../CSS-Styles/Resident-End-CSS/residentDashboard.css">
  <style>
    :root {
      --calendar-ink: #172132;
      --calendar-muted: #667085;
      --calendar-muted-soft: #95a0b2;
      --calendar-accent: #de710c;
      --calendar-accent-soft: #fff3e6;
      --calendar-accent-line: #f2d3b8;
      --calendar-secondary: #2b3545;
      --calendar-secondary-soft: #edf1f5;
      --calendar-surface: #ffffff;
      --calendar-surface-soft: #fffdfb;
      --calendar-surface-soft-2: #fff7ed;
      --calendar-line: #f2d3b8;
      --calendar-shadow: 0 10px 24px rgba(17, 24, 39, 0.08);
    }

    body {
      background: #f8f9fa;
    }

    .calendar-page-header {
      margin-bottom: 1.5rem;
    }

    .calendar-page-title {
      font-family: 'Charis SIL Bold', serif;
      font-size: clamp(2.35rem, 4.8vw, 4rem);
      color: var(--calendar-accent);
      line-height: 1.04;
      margin: 0;
    }

    .calendar-page-rule {
      margin: 1rem 0 1.15rem;
      border: 0;
      border-top: 1px solid #d6d8dc;
      opacity: 1;
    }

    .calendar-copy {
      color: var(--calendar-muted);
      font-size: 1.02rem;
      line-height: 1.72;
      margin: 0;
      max-width: 80rem;
    }

    .calendar-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.08fr) minmax(360px, 0.92fr);
      gap: 1.4rem;
      align-items: stretch;
    }

    .calendar-panel,
    .agenda-panel {
      min-width: 0;
      display: flex;
    }

    .calendar-card,
    .agenda-card {
      background: var(--calendar-surface);
      border: 1px solid var(--calendar-line);
      border-radius: 18px;
      box-shadow: var(--calendar-shadow);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      width: 100%;
      height: 100%;
      position: relative;
    }

    .calendar-card-head,
    .agenda-card-head {
      padding: 1.15rem 1.25rem 1rem;
      border-bottom: 1px solid var(--calendar-line);
      background: #ffffff;
      min-height: 108px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .calendar-card-title,
    .agenda-card-title {
      font-size: 1.18rem;
      font-weight: 800;
      color: var(--calendar-ink);
      margin: 0;
    }

    .calendar-card-copy,
    .agenda-card-copy {
      color: var(--calendar-muted);
      font-size: 0.95rem;
      margin: 0.35rem 0 0;
      line-height: 1.5;
    }

    .calendar-card-body,
    .agenda-card-body {
      padding: 1.2rem 1.25rem 1.25rem;
      flex: 1 1 auto;
    }

    .calendar-card-body {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .agenda-card-body {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      min-height: 0;
    }

    .calendar-toolbar {
      display: flex;
      justify-content: center;
      margin-bottom: 0;
    }

    .calendar-month-switcher {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.9rem;
      width: min(100%, 22rem);
      padding: 0;
    }

    .calendar-nav-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid var(--calendar-line);
      background: #ffffff;
      color: var(--calendar-accent);
      border-radius: 12px;
      font-weight: 700;
      box-shadow: none;
      transition: background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease, transform 0.18s ease, box-shadow 0.18s ease;
    }

    .calendar-nav-btn {
      width: 42px;
      height: 42px;
      flex: 0 0 auto;
    }

    .calendar-nav-btn:hover {
      background: var(--calendar-accent-soft);
      border-color: var(--calendar-accent);
      color: var(--calendar-accent);
      transform: translateY(-1px);
      box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
    }

    .calendar-month {
      font-family: 'Charis SIL Bold', serif;
      font-size: 1.25rem;
      font-weight: 800;
      color: var(--calendar-ink);
      margin: 0;
      text-align: center;
      flex: 1 1 auto;
      letter-spacing: 0.01em;
      padding: 0;
      background: transparent;
    }

    .calendar-weekdays,
    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 0.45rem;
    }

    .calendar-weekdays {
      margin-bottom: 0.5rem;
      padding: 0 0.1rem;
    }

    .calendar-weekdays span {
      text-align: center;
      font-size: 0.76rem;
      font-weight: 700;
      color: var(--calendar-muted-soft);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .calendar-grid {
      flex: 1 1 auto;
      align-content: stretch;
      grid-auto-rows: minmax(78px, 1fr);
      min-height: 0;
      gap: 0.65rem;
    }

    .resident-calendar-day {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 0;
      height: 100%;
      padding: 0.55rem 0.45rem;
      border: 1px solid #f3e5d4;
      border-radius: 18px;
      background: #ffffff;
      position: relative;
      text-align: left;
      color: #1f2937;
      transition: background-color 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
    }

    .resident-calendar-day:hover {
      background: #ffffff;
      border-color: #fc8d3d;
      box-shadow: 0 10px 22px rgba(17, 24, 39, 0.08);
      transform: translateY(-1px);
    }

    .resident-calendar-day.is-selected {
      background: var(--calendar-accent-soft);
      border-color: var(--calendar-accent);
      box-shadow:
        0 12px 22px rgba(17, 24, 39, 0.1);
    }

    .resident-calendar-day.is-outside {
      background: #fafafa;
      color: #c1c7d0;
    }

    .resident-calendar-day.has-items {
      border-color: #f2d3b8;
    }

    .resident-calendar-day.is-today {
      border-color: rgba(43, 53, 69, 0.26);
      box-shadow: 0 0 0 3px rgba(43, 53, 69, 0.07);
    }

    .resident-calendar-day__number {
      display: block;
      font-size: 0.95rem;
      font-weight: 800;
      line-height: 1;
      text-align: center;
    }

    .resident-calendar-day__count {
      position: absolute;
      top: 0.45rem;
      right: 0.45rem;
      min-width: 20px;
      height: 20px;
      padding: 0 0.35rem;
      border-radius: 999px;
      background: #fff3e6;
      color: var(--calendar-accent);
      font-size: 0.72rem;
      font-weight: 800;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 4px 10px rgba(217, 119, 6, 0.08);
    }

    .resident-calendar-day__dots {
      position: absolute;
      left: 0.45rem;
      bottom: 0.45rem;
      display: flex;
      gap: 0.28rem;
    }

    .resident-calendar-day__dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
    }

    .resident-calendar-day__dot--appointment {
      background: var(--calendar-secondary);
    }

    .resident-calendar-day__dot--announcement {
      background: var(--calendar-accent);
    }

    .calendar-legend {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 0.95rem;
      padding-top: 0.9rem;
      border-top: 1px solid #f2e5d7;
      margin-top: auto;
    }

    .calendar-legend-item {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--calendar-muted);
    }

    .calendar-legend-dot {
      width: 9px;
      height: 9px;
      border-radius: 50%;
      flex: 0 0 auto;
    }

    .calendar-legend-dot--appointment {
      background: var(--calendar-secondary);
    }

    .calendar-legend-dot--announcement {
      background: var(--calendar-accent);
    }

    .agenda-summary {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.75rem;
      margin-bottom: 0;
      padding-bottom: 0.95rem;
      border-bottom: 1px solid #f2e5d7;
    }

    .agenda-summary-label {
      font-size: 0.94rem;
      font-weight: 700;
      color: var(--calendar-ink);
      margin: 0;
    }

    .agenda-summary-note {
      font-size: 0.84rem;
      color: var(--calendar-muted);
      margin: 0;
    }

    .agenda-list {
      display: grid;
      gap: 0.8rem;
      flex: 1 1 auto;
      min-height: 0;
      overflow: auto;
      padding-right: 0.1rem;
      align-content: start;
    }

    .agenda-list::-webkit-scrollbar {
      width: 8px;
    }

    .agenda-list::-webkit-scrollbar-thumb {
      background: rgba(217, 119, 6, 0.18);
      border-radius: 999px;
    }

    .agenda-list::-webkit-scrollbar-track {
      background: transparent;
    }

    .agenda-item {
      display: block;
      border: 1px solid #f2d3b8;
      border-radius: 18px;
      background: #ffffff;
      padding: 1rem 1.05rem;
      text-decoration: none;
      color: inherit;
      overflow: hidden;
      box-shadow: none;
      transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
      position: relative;
    }

    .agenda-item--appointment {
      background: #ffffff;
    }

    .agenda-item--announcement {
      background: #ffffff;
    }

    .agenda-item:hover {
      transform: translateY(-2px);
      border-color: #fc8d3d;
      background: #ffffff;
      box-shadow: 0 16px 30px rgba(17, 24, 39, 0.12);
    }

    .agenda-item-row {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 0.6rem;
      margin-bottom: 0.45rem;
    }

    .agenda-item-date {
      font-size: 0.78rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: var(--calendar-accent);
    }

    .agenda-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 26px;
      padding: 0.2rem 0.65rem;
      border-radius: 999px;
      font-size: 0.74rem;
      font-weight: 700;
      white-space: nowrap;
      flex-shrink: 0;
    }

    .agenda-pill--appointment {
      background: var(--calendar-secondary-soft);
      color: var(--calendar-secondary);
    }

    .agenda-pill--announcement {
      background: var(--calendar-accent-soft);
      color: var(--calendar-accent);
    }

    .agenda-pill--approved {
      background: #e9f8ee;
      color: #1b7f46;
    }

    .agenda-pill--pending {
      background: #fff4dd;
      color: #9d5a13;
    }

    .agenda-pill--info {
      background: var(--calendar-secondary-soft);
      color: var(--calendar-secondary);
    }

    .agenda-pill--archived {
      background: #f6e6e8;
      color: #9c3042;
    }

    .agenda-item-title {
      font-size: 1.02rem;
      font-weight: 800;
      color: var(--calendar-ink);
      line-height: 1.3;
      margin-bottom: 0.28rem;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .agenda-item-copy {
      font-size: 0.9rem;
      color: var(--calendar-muted);
      line-height: 1.45;
      margin-bottom: 0.35rem;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .agenda-item-meta {
      font-size: 0.82rem;
      color: #7b8494;
      overflow-wrap: anywhere;
      word-break: break-word;
    }

    .agenda-status {
      margin-top: 0.45rem;
    }

    .agenda-empty {
      border: 1px dashed #ecc9a4;
      border-radius: 18px;
      background: #fffaf4;
      padding: 1.45rem 1.2rem;
      text-align: center;
      color: #667085;
    }

    .agenda-empty i {
      font-size: 1.55rem;
      color: #de710c;
      margin-bottom: 0.75rem;
    }

    @media (max-width: 991.98px) {
      .calendar-layout {
        grid-template-columns: 1fr;
      }

      .calendar-panel,
      .agenda-panel {
        display: block;
      }

      .calendar-card,
      .agenda-card {
        min-height: 0;
      }

      .agenda-list {
        overflow: visible;
        padding-right: 0;
      }
    }

    @media (max-width: 767.98px) {
      .calendar-page-title {
        font-size: clamp(1.55rem, 7.2vw, 2.1rem);
        line-height: 1.08;
      }

      .calendar-page-rule {
        margin: 0.75rem 0 0.9rem;
      }

      .calendar-copy {
        font-size: 0.88rem;
        line-height: 1.55;
      }

      .calendar-card-body,
      .agenda-card-body,
      .calendar-card-head,
      .agenda-card-head {
        padding-left: 0.95rem;
        padding-right: 0.95rem;
      }

      .calendar-toolbar {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0;
      }

      .calendar-month-switcher {
        width: 100%;
        gap: 0.6rem;
        padding: 0.42rem 0.5rem;
        border-radius: 18px;
      }

      .calendar-month {
        min-width: 0;
        flex: 1 1 auto;
        font-size: 0.98rem;
        padding: 0.7rem 0.75rem;
      }

      .calendar-grid {
        grid-auto-rows: minmax(64px, 1fr);
      }

      .resident-calendar-day {
        border-radius: 14px;
      }

      .calendar-legend {
        justify-content: flex-start;
        gap: 0.75rem;
      }

      .calendar-card,
      .agenda-card {
        border-radius: 18px;
      }
    }
  </style>
</head>
<body>
  <div class="d-flex min-vh-100">
    <?php include __DIR__ . '/includes/resident_sidebar.php'; ?>

    <header id="mobile-header">
      <div class="d-flex align-items-center px-3 py-2 shadow-sm bg-white">
        <div class="d-flex align-items-center gap-2">
          <button class="btn" id="btn-burger" type="button">
            <i class="fa-solid fa-bars fa-lg"></i>
          </button>
          <img src="../Images/San_Jose_LOGO.jpg" alt="Logo" style="width:32px;height:32px">
          <span class="logo-name">Barangay San Jose</span>
        </div>
      </div>
    </header>

    <main id="div-mainDisplay" class="main-content flex-grow-1 p-4 p-md-5 bg-light">
      <section class="calendar-page-header">
        <h1 class="calendar-page-title">Resident Calendar</h1>
        <hr class="calendar-page-rule">
        <p class="calendar-copy">Track the dates that matter from one place. This page currently records resident appointment schedules and official announcement posting dates. When the barangay starts storing event-specific announcement dates, they can be added here too.</p>
      </section>

      <section class="calendar-layout">
        <div class="calendar-panel">
          <div class="calendar-card">
            <div class="calendar-card-head">
              <h2 class="calendar-card-title">Month View</h2>
              <p class="calendar-card-copy">Browse appointment schedules and announcement posting dates in one monthly view.</p>
            </div>
            <div class="calendar-card-body">
              <div class="calendar-toolbar">
                <div class="calendar-month-switcher">
                  <button type="button" class="calendar-nav-btn" id="residentCalendarPrev" aria-label="Previous month">
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  <h3 class="calendar-month" id="residentCalendarMonthLabel">-</h3>
                  <button type="button" class="calendar-nav-btn" id="residentCalendarNext" aria-label="Next month">
                    <i class="fa-solid fa-chevron-right"></i>
                  </button>
                </div>
              </div>

              <div class="calendar-weekdays">
                <span>Sun</span>
                <span>Mon</span>
                <span>Tue</span>
                <span>Wed</span>
                <span>Thu</span>
                <span>Fri</span>
                <span>Sat</span>
              </div>

              <div class="calendar-grid" id="residentCalendarGrid" aria-live="polite"></div>

              <div class="calendar-legend" aria-label="Calendar legend">
                <span class="calendar-legend-item">
                  <span class="calendar-legend-dot calendar-legend-dot--appointment" aria-hidden="true"></span>
                  Appointment
                </span>
                <span class="calendar-legend-item">
                  <span class="calendar-legend-dot calendar-legend-dot--announcement" aria-hidden="true"></span>
                  Announcement
                </span>
              </div>
            </div>
          </div>
        </div>

        <aside class="agenda-panel">
          <div class="agenda-card">
            <div class="agenda-card-head">
              <h2 class="agenda-card-title" id="residentAgendaTitle">Upcoming Records</h2>
              <p class="agenda-card-copy" id="residentAgendaCopy">The next dates for your resident schedule will appear here.</p>
            </div>
            <div class="agenda-card-body">
              <div class="agenda-summary">
                <div>
                  <p class="agenda-summary-label mb-0" id="residentAgendaSummaryLabel">Showing the next available records</p>
                  <p class="agenda-summary-note" id="residentAgendaSummaryNote">Appointments use their saved schedule. Announcements use their posting date.</p>
                </div>
              </div>
              <div class="agenda-list" id="residentAgendaList"></div>
            </div>
          </div>
        </aside>
      </section>
    </main>
  </div>

  <script>
    const burgerBtn = document.getElementById("btn-burger");
    const sidebar = document.getElementById("div-sidebarWrapper");

    if (burgerBtn && sidebar) {
      burgerBtn.addEventListener("click", () => {
        sidebar.classList.toggle("show");
      });
    }

    (() => {
      const scheduleItems = <?= json_encode($calendarPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
      const calendarGrid = document.getElementById("residentCalendarGrid");
      const monthLabel = document.getElementById("residentCalendarMonthLabel");
      const agendaTitle = document.getElementById("residentAgendaTitle");
      const agendaCopy = document.getElementById("residentAgendaCopy");
      const agendaSummaryLabel = document.getElementById("residentAgendaSummaryLabel");
      const agendaSummaryNote = document.getElementById("residentAgendaSummaryNote");
      const agendaList = document.getElementById("residentAgendaList");
      const prevBtn = document.getElementById("residentCalendarPrev");
      const nextBtn = document.getElementById("residentCalendarNext");

      if (!calendarGrid || !monthLabel || !agendaTitle || !agendaCopy || !agendaSummaryLabel || !agendaSummaryNote || !agendaList || !prevBtn || !nextBtn) {
        return;
      }

      const initialMonth = <?= json_encode($initialMonthDate, JSON_UNESCAPED_SLASHES) ?>;
      const today = new Date();
      const todayStart = new Date(today.getFullYear(), today.getMonth(), today.getDate());
      const state = {
        selectedDate: "",
        currentMonth: new Date(initialMonth + "T00:00:00"),
      };

      function formatIso(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
      }

      function formatReadableDate(isoDate) {
        const date = new Date(isoDate + "T00:00:00");
        return date.toLocaleDateString(undefined, { month: "long", day: "numeric", year: "numeric" });
      }

      function escapeHtml(value) {
        return String(value ?? "").replace(/[&<>"']/g, (character) => {
          const replacements = {
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            "\"": "&quot;",
            "'": "&#39;",
          };
          return replacements[character] || character;
        });
      }

      function agendaKindClass(kind) {
        return kind === "appointment" ? "appointment" : "announcement";
      }

      function agendaStatusClass(bucket) {
        const allowed = new Set(["approved", "pending", "info", "archived"]);
        return allowed.has(bucket) ? bucket : "pending";
      }

      function filteredItems() {
        return scheduleItems;
      }

      function itemsByDate(items) {
        const map = new Map();
        items.forEach((item) => {
          if (!map.has(item.date_iso)) {
            map.set(item.date_iso, []);
          }
          map.get(item.date_iso).push(item);
        });
        map.forEach((entries) => {
          entries.sort((a, b) => a.timestamp - b.timestamp);
        });
        return map;
      }

      function renderCalendar() {
        const visibleItems = filteredItems();
        const grouped = itemsByDate(visibleItems);
        const year = state.currentMonth.getFullYear();
        const month = state.currentMonth.getMonth();
        monthLabel.textContent = state.currentMonth.toLocaleDateString(undefined, { month: "long", year: "numeric" });
        calendarGrid.innerHTML = "";

        const firstDay = new Date(year, month, 1);
        const startWeekday = firstDay.getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        const totalCells = Math.ceil((startWeekday + daysInMonth) / 7) * 7;

        for (let cellIndex = 0; cellIndex < totalCells; cellIndex += 1) {
          let dateObj;
          let isOutside = false;

          if (cellIndex < startWeekday) {
            dateObj = new Date(year, month - 1, daysInPrevMonth - startWeekday + cellIndex + 1);
            isOutside = true;
          } else if (cellIndex >= startWeekday + daysInMonth) {
            dateObj = new Date(year, month + 1, cellIndex - (startWeekday + daysInMonth) + 1);
            isOutside = true;
          } else {
            dateObj = new Date(year, month, cellIndex - startWeekday + 1);
          }

          const iso = formatIso(dateObj);
          const dateItems = grouped.get(iso) || [];
          const button = document.createElement("button");
          button.type = "button";
          button.className = "resident-calendar-day";
          const isToday = iso === formatIso(todayStart);
          if (isOutside) {
            button.classList.add("is-outside");
          }
          if (dateItems.length) {
            button.classList.add("has-items");
          }
          if (isToday) {
            button.classList.add("is-today");
          }
          if (state.selectedDate === iso) {
            button.classList.add("is-selected");
          }

          const number = document.createElement("span");
          number.className = "resident-calendar-day__number";
          number.textContent = String(dateObj.getDate());
          button.appendChild(number);

          if (dateItems.length) {
            const count = document.createElement("span");
            count.className = "resident-calendar-day__count";
            count.textContent = String(dateItems.length);
            button.appendChild(count);

            const dots = document.createElement("span");
            dots.className = "resident-calendar-day__dots";
            const kinds = Array.from(new Set(dateItems.map((item) => item.kind)));
            kinds.forEach((kind) => {
              const dot = document.createElement("span");
              dot.className = `resident-calendar-day__dot resident-calendar-day__dot--${kind}`;
              dots.appendChild(dot);
            });
            button.appendChild(dots);
          }

          button.addEventListener("click", () => {
            state.selectedDate = iso;
            state.currentMonth = new Date(dateObj.getFullYear(), dateObj.getMonth(), 1);
            renderCalendar();
            renderAgenda();
          });

          calendarGrid.appendChild(button);
        }
      }

      function renderAgenda() {
        const visibleItems = filteredItems();
        let agendaItems = [];

        if (state.selectedDate !== "") {
          agendaItems = visibleItems.filter((item) => item.date_iso === state.selectedDate).sort((a, b) => a.timestamp - b.timestamp);
          agendaTitle.textContent = formatReadableDate(state.selectedDate);
          agendaCopy.textContent = "Detailed records scheduled for the selected date.";
          agendaSummaryLabel.textContent = agendaItems.length
            ? `${agendaItems.length} record${agendaItems.length === 1 ? "" : "s"} on this date`
            : "No records on this date";
        } else {
          const upcoming = visibleItems.filter((item) => item.timestamp >= Math.floor(todayStart.getTime() / 1000)).sort((a, b) => a.timestamp - b.timestamp);
          agendaItems = upcoming.length ? upcoming.slice(0, 8) : visibleItems.slice().sort((a, b) => b.timestamp - a.timestamp).slice(0, 8);
          agendaTitle.textContent = upcoming.length ? "Upcoming Records" : "Recent Records";
          agendaCopy.textContent = upcoming.length
            ? "The next appointments and official announcement dates for your resident account."
            : "No upcoming records are scheduled yet, so the latest saved dates are shown instead.";
          agendaSummaryLabel.textContent = upcoming.length
            ? `Showing the next ${agendaItems.length} record${agendaItems.length === 1 ? "" : "s"}`
            : `Showing the latest ${agendaItems.length} record${agendaItems.length === 1 ? "" : "s"}`;
        }

        agendaSummaryNote.textContent = "Appointments use their saved schedule. Announcements use their posting date.";

        agendaList.innerHTML = "";

        if (!agendaItems.length) {
          const empty = document.createElement("div");
          empty.className = "agenda-empty";
          empty.innerHTML = `
            <i class="fa-regular fa-calendar-xmark"></i>
            <h3 class="h6 mb-2">Nothing to show here yet</h3>
            <p class="mb-0">Try another month or filter, or check back once new resident records are available.</p>
          `;
          agendaList.appendChild(empty);
          return;
        }

        agendaItems.forEach((item) => {
          const card = document.createElement("a");
          card.className = "agenda-item";
          card.href = item.href || "#";
          const itemDate = new Date(item.datetime_iso.replace(" ", "T"));
          const kindClass = agendaKindClass(item.kind);
          const statusClass = agendaStatusClass(item.status_bucket);
          card.classList.add(`agenda-item--${kindClass}`);

          card.innerHTML = `
            <div class="agenda-item-row">
              <span class="agenda-item-date">${escapeHtml(itemDate.toLocaleDateString(undefined, { month: "short", day: "numeric" }))}</span>
              <span class="agenda-pill agenda-pill--${kindClass}">${escapeHtml(item.kind_label)}</span>
            </div>
            <div class="agenda-item-title">${escapeHtml(item.title)}</div>
            <div class="agenda-item-copy">${escapeHtml(item.summary)}</div>
            <div class="agenda-item-meta">${escapeHtml(item.meta)}</div>
            <div class="agenda-status">
              <span class="agenda-pill agenda-pill--${statusClass}">${escapeHtml(item.status_label)}</span>
            </div>
          `;

          agendaList.appendChild(card);
        });
      }

      prevBtn.addEventListener("click", () => {
        state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() - 1, 1);
        renderCalendar();
      });

      nextBtn.addEventListener("click", () => {
        state.currentMonth = new Date(state.currentMonth.getFullYear(), state.currentMonth.getMonth() + 1, 1);
        renderCalendar();
      });

      renderCalendar();
      renderAgenda();
    })();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
