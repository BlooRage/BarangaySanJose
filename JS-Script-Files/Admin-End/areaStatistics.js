(() => {
  const mainDisplay = document.getElementById('main-display');
  if (!mainDisplay) return;

  const endpoint = mainDisplay.dataset.endpoint || '../../PhpFiles/Admin-End/areaStatisticsData.php';
  const fixedScope = mainDisplay.dataset.pageScope || 'barangay';
  const moduleSelect = document.getElementById('moduleSelect');
  const dateFromInput = document.getElementById('dateFrom');
  const dateToInput = document.getElementById('dateTo');
  const statusSelect = document.getElementById('statusSelect');
  const resetBtn = document.querySelector('.area-reset-btn');
  const applyBtn = document.getElementById('btnApplyAreaFilters');
  const loadingOverlay = document.getElementById('areaLoadingOverlay');
  const widgetList = document.getElementById('widgetCustomizerList');
  const resetWidgetBtn = document.getElementById('btnResetWidgetLayout');
  const widgetNodes = Array.from(document.querySelectorAll('[data-widget]'));
  const widgetStorageKey = `area_widget_layout:${fixedScope.replace(/\s+/g, '_').toLowerCase()}:${window.location.pathname.toLowerCase()}`;
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let hasInitializedLayout = false;
  let appliedFilterSnapshot = '';
  let isLoading = false;

  const chartPalette = {
    orange: '#de710c',
    amber: '#f59f00',
    sky: '#1c7ed6',
    emerald: '#2f9e44',
    rose: '#e03131',
    grid: 'rgba(32, 35, 41, 0.08)'
  };

  Chart.defaults.font.family = "'Geist', sans-serif";
  Chart.defaults.color = '#495057';

  function defaultDateRange() {
    const now = new Date();
    const to = now.toISOString().slice(0, 10);
    const fromDate = new Date(now.getFullYear(), now.getMonth() - 5, 1);
    const from = fromDate.toISOString().slice(0, 10);
    return { from, to };
  }

  function setDefaults() {
    const defaults = defaultDateRange();
    if (dateFromInput && !dateFromInput.value) dateFromInput.value = defaults.from;
    if (dateToInput && !dateToInput.value) dateToInput.value = defaults.to;
    if (moduleSelect && !moduleSelect.value) moduleSelect.value = 'all';
    if (statusSelect && !statusSelect.value) statusSelect.value = 'all';
  }

  function getCurrentFilterState() {
    return JSON.stringify({
      module: moduleSelect?.value || 'all',
      status: statusSelect?.value || 'all',
      dateFrom: dateFromInput?.value || '',
      dateTo: dateToInput?.value || ''
    });
  }

  function updateApplyButtonState() {
    if (!applyBtn) return;
    applyBtn.disabled = isLoading || getCurrentFilterState() === appliedFilterSnapshot;
  }

  function markFiltersApplied() {
    appliedFilterSnapshot = getCurrentFilterState();
    updateApplyButtonState();
  }

  function setLoadingState(nextLoading) {
    isLoading = nextLoading;
    mainDisplay.classList.toggle('is-loading', nextLoading);
    if (loadingOverlay) {
      loadingOverlay.setAttribute('aria-hidden', nextLoading ? 'false' : 'true');
    }

    if (applyBtn) {
      applyBtn.textContent = nextLoading ? 'Applying...' : 'Apply Filters';
    }

    [moduleSelect, dateFromInput, dateToInput, statusSelect, resetBtn].forEach((input) => {
      if (!input) return;
      input.disabled = nextLoading;
    });

    updateApplyButtonState();
  }

  function startPageAnimation() {
    document.body.classList.remove('area-widgets-ready');
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        document.body.classList.add('area-widgets-ready');
      });
    });
  }

  function buildBarChart(canvas) {
    return new Chart(canvas, {
      type: 'bar',
      data: { labels: [], datasets: [{ data: [], backgroundColor: 'rgba(28, 126, 214, 0.82)', borderRadius: 10, borderSkipped: false }] },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: chartPalette.grid } }
        }
      }
    });
  }

  function buildDonutChart(canvas) {
    return new Chart(canvas, {
      type: 'doughnut',
      data: {
        labels: [],
        datasets: [{
          data: [],
          backgroundColor: [chartPalette.sky, chartPalette.rose],
          borderColor: '#fff',
          borderWidth: 4
        }]
      },
      options: {
        maintainAspectRatio: true,
        aspectRatio: 1,
        cutout: '62%',
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } } }
      }
    });
  }

  function buildLineChart(canvas) {
    return new Chart(canvas, {
      type: 'line',
      data: {
        labels: [],
        datasets: [{
          label: 'Volume',
          data: [],
          borderColor: chartPalette.orange,
          backgroundColor: 'rgba(222, 113, 12, 0.14)',
          fill: true,
          tension: 0.35,
          pointRadius: 4,
          pointHoverRadius: 5
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: chartPalette.grid } }
        }
      }
    });
  }

  const charts = {
    module: buildBarChart(document.querySelector('.area-module-chart')),
    demographic: buildDonutChart(document.querySelector('.area-demographic-chart')),
    trend: buildLineChart(document.querySelector('.area-trend-chart'))
  };

  function readWidgetState() {
    try {
      const raw = window.localStorage.getItem(widgetStorageKey);
      return raw ? JSON.parse(raw) : {};
    } catch {
      return {};
    }
  }

  function writeWidgetState(state) {
    try {
      window.localStorage.setItem(widgetStorageKey, JSON.stringify(state));
    } catch {
      // Ignore storage failures.
    }
  }

  function collapseWidget(node) {
    node.getAnimations().forEach((animation) => animation.cancel());
    node.style.display = '';
    node.style.maxHeight = `${node.scrollHeight}px`;
    window.requestAnimationFrame(() => {
      node.classList.add('area-widget-hidden');
      node.style.maxHeight = '0px';
    });
  }

  function expandWidget(node) {
    node.getAnimations().forEach((animation) => animation.cancel());
    node.style.display = '';
    node.classList.remove('area-widget-hidden');
    node.style.transform = '';
    node.style.opacity = '';
    node.style.marginTop = '';
    node.style.marginBottom = '';
    node.style.paddingTop = '';
    node.style.paddingBottom = '';
    node.style.borderWidth = '';
    node.style.maxHeight = '0px';
    window.requestAnimationFrame(() => {
      updateDynamicLayouts();
      node.style.maxHeight = `${node.scrollHeight}px`;
    });
  }

  function finalizeWidgetTransition(event) {
    const node = event.currentTarget;
    if (event.propertyName !== 'max-height') return;
    if (node.classList.contains('area-widget-hidden')) {
      node.style.maxHeight = '0px';
      node.style.display = 'none';
    } else {
      node.style.maxHeight = 'none';
      node.style.display = '';
      node.style.transform = '';
      node.style.opacity = '';
      node.style.marginTop = '';
      node.style.marginBottom = '';
      node.style.paddingTop = '';
      node.style.paddingBottom = '';
      node.style.borderWidth = '';
    }
    updateDynamicLayouts();
  }

  function applyWidgetState() {
    const state = readWidgetState();
    widgetNodes.forEach((node) => {
      const widgetId = node.dataset.widget;
      const visible = state[widgetId] !== false;
      const alreadyHidden = node.classList.contains('area-widget-hidden');
      if (visible && alreadyHidden) {
        expandWidget(node);
      } else if (!visible && !alreadyHidden) {
        collapseWidget(node);
      } else if (!visible && alreadyHidden) {
        node.style.display = 'none';
      } else if (visible) {
        node.style.display = '';
      }
    });
    updateDynamicLayouts();
  }

  function visibleGridItems(grid) {
    return Array.from(grid.querySelectorAll(':scope > .area-grid-item')).filter((item) => !item.classList.contains('area-widget-hidden'));
  }

  function clearDynamicSpans(items) {
    items.forEach((item) => {
      delete item.dataset.dynamicSpan;
    });
  }

  function setDynamicSpan(item, span) {
    item.dataset.dynamicSpan = String(span);
  }

  function resizeChartsSoon() {
    window.requestAnimationFrame(() => {
      Object.values(charts).forEach((chart) => {
        if (!chart) return;
        chart.resize();
        chart.update('none');
      });
    });
  }

  function captureWidgetRects() {
    const rects = new Map();
    widgetNodes.forEach((node) => {
      if (node.style.display === 'none' || node.classList.contains('area-widget-hidden')) return;
      rects.set(node, node.getBoundingClientRect());
    });
    return rects;
  }

  function animateLayoutShift(beforeRects) {
    if (prefersReducedMotion || !hasInitializedLayout) return;

    window.requestAnimationFrame(() => {
      widgetNodes.forEach((node) => {
        if (node.style.display === 'none' || node.classList.contains('area-widget-hidden')) return;

        const before = beforeRects.get(node);
        if (!before) return;

        const after = node.getBoundingClientRect();
        const deltaX = before.left - after.left;
        const deltaY = before.top - after.top;
        const scaleX = before.width > 0 ? before.width / Math.max(after.width, 1) : 1;
        const scaleY = before.height > 0 ? before.height / Math.max(after.height, 1) : 1;

        if (Math.abs(deltaX) < 1 && Math.abs(deltaY) < 1 && Math.abs(scaleX - 1) < 0.01 && Math.abs(scaleY - 1) < 0.01) {
          return;
        }

        node.animate(
          [
            {
              transform: `translate(${deltaX}px, ${deltaY}px) scale(${scaleX}, ${scaleY})`,
              transformOrigin: 'top left'
            },
            {
              transform: 'translate(0, 0) scale(1, 1)',
              transformOrigin: 'top left'
            }
          ],
          {
            duration: 520,
            easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
            fill: 'both'
          }
        );
      });
    });
  }

  function layoutCardGrid(grid) {
    const items = Array.from(grid.querySelectorAll(':scope > .area-grid-item'));
    clearDynamicSpans(items);

    const visible = visibleGridItems(grid);
    for (let index = 0; index < visible.length;) {
      const remaining = visible.length - index;
      const rowSize = Math.min(4, remaining);
      const span = rowSize === 1 ? 12 : rowSize === 2 ? 6 : rowSize === 3 ? 4 : 3;

      for (let rowIndex = 0; rowIndex < rowSize; rowIndex += 1) {
        setDynamicSpan(visible[index + rowIndex], span);
      }

      index += rowSize;
    }
  }

  function widgetKind(item) {
    if (item.classList.contains('area-grid-item--full')) return 'full';
    if (item.classList.contains('area-grid-item--wide')) return 'wide';
    return 'regular';
  }

  function layoutContentGrid(grid) {
    const items = Array.from(grid.querySelectorAll(':scope > .area-grid-item'));
    clearDynamicSpans(items);

    const visible = visibleGridItems(grid);
    for (let index = 0; index < visible.length;) {
      const current = visible[index];
      const currentKind = widgetKind(current);
      const next = visible[index + 1];
      const nextKind = next ? widgetKind(next) : null;
      const third = visible[index + 2];
      const thirdKind = third ? widgetKind(third) : null;

      if (currentKind === 'full') {
        setDynamicSpan(current, 12);
        index += 1;
        continue;
      }

      if (currentKind === 'wide') {
        if (next && nextKind === 'regular') {
          setDynamicSpan(current, 8);
          setDynamicSpan(next, 4);
          index += 2;
          continue;
        }

        setDynamicSpan(current, 12);
        index += 1;
        continue;
      }

      if (next && nextKind === 'regular') {
        if (third && thirdKind === 'regular') {
          setDynamicSpan(current, 4);
          setDynamicSpan(next, 4);
          setDynamicSpan(third, 4);
          index += 3;
          continue;
        }

        setDynamicSpan(current, 6);
        setDynamicSpan(next, 6);
        index += 2;
        continue;
      }

      setDynamicSpan(current, 12);
      index += 1;
    }
  }

  function layoutSpotlightSections() {
    document.querySelectorAll('.area-spotlight').forEach((spotlight) => {
      const copy = spotlight.querySelector('.area-spotlight-copy');
      const metrics = spotlight.querySelector('.area-spotlight-metrics');
      const metricItems = metrics
        ? Array.from(metrics.children).filter((item) => item.style.display !== 'none' && !item.classList.contains('area-widget-hidden'))
        : [];
      const copyVisible = !!(copy && copy.style.display !== 'none' && !copy.classList.contains('area-widget-hidden'));

      spotlight.dataset.copyVisible = copyVisible ? 'true' : 'false';
      spotlight.dataset.metricCount = String(metricItems.length);
      spotlight.dataset.layout = copyVisible && metricItems.length > 0
        ? 'hero-metrics'
        : copyVisible
          ? 'hero-only'
          : 'metrics-only';

      if (!metrics) return;

      const columnCount = metricItems.length <= 1 ? 1 : 2;
      metrics.style.gridTemplateColumns = `repeat(${columnCount}, minmax(0, 1fr))`;
    });
  }

  function updateDynamicLayouts() {
    const beforeRects = captureWidgetRects();

    layoutSpotlightSections();

    document.querySelectorAll('.area-dashboard-grid').forEach((grid) => {
      if (grid.classList.contains('area-dashboard-grid--cards')) {
        layoutCardGrid(grid);
        return;
      }

      if (grid.classList.contains('area-dashboard-grid--content')) {
        layoutContentGrid(grid);
      }
    });

    resizeChartsSoon();
    animateLayoutShift(beforeRects);
    hasInitializedLayout = true;
  }

  function buildWidgetCustomizer() {
    if (!widgetList) return;
    const state = readWidgetState();
    widgetList.innerHTML = widgetNodes.map((node) => {
      const widgetId = node.dataset.widget || '';
      const label = node.dataset.widgetLabel || widgetId;
      const checked = state[widgetId] !== false ? 'checked' : '';
      return `
        <div class="area-widget-option">
          <label for="widget-toggle-${widgetId}">${label}</label>
          <div class="form-check form-switch m-0">
            <input class="form-check-input area-widget-toggle" type="checkbox" role="switch" id="widget-toggle-${widgetId}" data-widget-target="${widgetId}" ${checked}>
          </div>
        </div>
      `;
    }).join('');

    widgetList.querySelectorAll('.area-widget-toggle').forEach((input) => {
      input.addEventListener('change', () => {
        const nextState = readWidgetState();
        nextState[input.dataset.widgetTarget] = input.checked;
        writeWidgetState(nextState);
        applyWidgetState();
      });
    });
  }

  function params() {
    const p = new URLSearchParams();
    p.set('scope', fixedScope);
    p.set('module', moduleSelect?.value || 'all');
    p.set('status', statusSelect?.value || 'all');
    p.set('date_from', dateFromInput?.value || '');
    p.set('date_to', dateToInput?.value || '');
    return p;
  }

  function renderTable(rows) {
    const tableBody = document.querySelector('.area-summary-table tbody');
    if (!tableBody) return;
    if (!rows.length) {
      tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No records found for the selected filters.</td></tr>';
      return;
    }
    tableBody.innerHTML = rows.map((row) => `
      <tr>
        <td>${row.module}</td>
        <td>${Number(row.total || 0).toLocaleString()}</td>
        <td>${Number(row.active_pending || 0).toLocaleString()}</td>
        <td>${Number(row.completed_resolved || 0).toLocaleString()}</td>
        <td>${row.notes || '-'}</td>
      </tr>
    `).join('');
  }

  function renderHighlights(items) {
    const container = document.querySelector('.area-highlight-list');
    if (!container) return;
    container.innerHTML = (items || []).map((item) => `
      <div class="area-highlight-item">
        <span class="area-highlight-label">${item.label || '-'}</span>
        <strong class="area-highlight-value">${item.value || '-'}</strong>
      </div>
    `).join('');
  }

  function renderCards(payload) {
    document.querySelectorAll('[data-stat="population"]').forEach((el) => el.textContent = Number(payload.cards?.population || 0).toLocaleString());
    document.querySelectorAll('[data-stat="households"]').forEach((el) => el.textContent = Number(payload.cards?.households || 0).toLocaleString());
    document.querySelectorAll('[data-stat="documents"]').forEach((el) => el.textContent = Number(payload.cards?.documents || 0).toLocaleString());
    document.querySelectorAll('[data-stat="cases"]').forEach((el) => el.textContent = Number(payload.cards?.cases || 0).toLocaleString());
  }

  async function loadData() {
    setLoadingState(true);
    try {
      const response = await fetch(`${endpoint}?${params().toString()}`, { credentials: 'same-origin' });
      if (!response.ok) throw new Error(`Request failed with status ${response.status}`);
      const payload = await response.json();

      renderCards(payload);

      charts.module.data.labels = payload.module_chart?.labels || [];
      charts.module.data.datasets[0].data = payload.module_chart?.values || [];
      charts.module.update();

      charts.demographic.data.labels = payload.demographics?.labels || [];
      charts.demographic.data.datasets[0].data = payload.demographics?.values || [];
      charts.demographic.update();

      charts.trend.data.labels = payload.trend?.labels || [];
      charts.trend.data.datasets[0].data = payload.trend?.values || [];
      charts.trend.update();

      renderHighlights(payload.highlights || []);
      renderTable(payload.table || []);
      markFiltersApplied();
    } catch (error) {
      renderHighlights([
        { label: 'Load error', value: 'Failed to fetch area statistics.' },
        { label: 'Details', value: error instanceof Error ? error.message : 'Unknown error' }
      ]);
      renderTable([]);
    } finally {
      setLoadingState(false);
    }
  }

  setDefaults();
  appliedFilterSnapshot = getCurrentFilterState();
  updateApplyButtonState();
  widgetNodes.forEach((node) => node.addEventListener('transitionend', finalizeWidgetTransition));
  buildWidgetCustomizer();
  applyWidgetState();
  startPageAnimation();
  loadData();

  [moduleSelect, dateFromInput, dateToInput, statusSelect].forEach((input) => {
    if (!input) return;
    input.addEventListener('change', updateApplyButtonState);
    input.addEventListener('input', updateApplyButtonState);
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && applyBtn && !applyBtn.disabled) {
        event.preventDefault();
        loadData();
      }
    });
  });

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      const defaults = defaultDateRange();
      if (moduleSelect) moduleSelect.value = 'all';
      if (statusSelect) statusSelect.value = 'all';
      if (dateFromInput) dateFromInput.value = defaults.from;
      if (dateToInput) dateToInput.value = defaults.to;
      updateApplyButtonState();
    });
  }

  if (applyBtn) {
    applyBtn.addEventListener('click', loadData);
  }

  if (resetWidgetBtn) {
    resetWidgetBtn.addEventListener('click', () => {
      try {
        window.localStorage.removeItem(widgetStorageKey);
      } catch {
        // Ignore storage failures.
      }
      buildWidgetCustomizer();
      applyWidgetState();
    });
  }
})();
